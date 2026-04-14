import { computed, ref } from 'vue';
import { useStorage } from '@vueuse/core';
import { fetchEventSource } from '@microsoft/fetch-event-source';
import { request, parse_api_response } from '~/utils';
import type { GenericError, GenericResponse } from '~/types';

type ConsoleEventMessage = {
  id: string;
  event: string;
  data: string;
};

type PersistedConsoleSession = {
  token: string;
  command: string;
  displayCommand: string;
  lastSequence: number;
  resumeSequence: number;
};

type PersistedConsoleView = {
  command: string;
  displayCommand: string;
  exitCode: number;
  lastSequence: number;
  chunks: Array<string>;
};

type ConsoleStreamRunResult =
  | { status: 'started' }
  | { status: 'blocked' }
  | { status: 'error'; message: string };

type ConsoleStreamState = {
  status: 'idle' | 'starting' | 'streaming' | 'reconnecting' | 'error';
  command: string;
  displayCommand: string;
  exitCode: number;
  completedAt: number;
  token: string | null;
  error: string;
  chunks: Array<string>;
  lastSequence: number;
};

const MAX_CHUNKS = 4000;
const RESTORE_EXPIRED = '__restore_expired__';
const isExpiredStreamStatus = (status: number): boolean => [400, 404].includes(status);
const ACTIVE_SESSION_STORAGE_KEY = 'consoleActiveSession';
const ACTIVE_VIEW_STORAGE_KEY = 'consoleActiveView';

class FatalConsoleStreamError extends Error {}

const getConsoleStorage = (): Storage | null => {
  if ('undefined' === typeof window) {
    return null;
  }

  return window.localStorage;
};

const readConsoleStorageRaw = (key: string): string | null => {
  return getConsoleStorage()?.getItem(key) ?? null;
};

const readConsoleStorage = <T>(key: string): T | null => {
  const raw = readConsoleStorageRaw(key);
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
};

const writeConsoleStorage = (key: string, value: unknown): void => {
  const storage = getConsoleStorage();
  if (!storage) {
    return;
  }

  if (null === value) {
    storage.removeItem(key);
    return;
  }

  storage.setItem(key, JSON.stringify(value));
};

const normalizeConsoleChunk = (input: unknown): string | null => {
  if ('string' === typeof input) {
    return input;
  }

  if (!input || 'object' !== typeof input) {
    return null;
  }

  const value = Reflect.get(input, 'value');
  return 'string' === typeof value ? value : null;
};

const normalizePersistedConsoleSession = (input: unknown): PersistedConsoleSession | null => {
  if (!input || 'object' !== typeof input) {
    return null;
  }

  const token = Reflect.get(input, 'token');
  const command = Reflect.get(input, 'command');
  const displayCommand = Reflect.get(input, 'displayCommand');
  const lastSequence = Reflect.get(input, 'lastSequence');
  const resumeSequence = Reflect.get(input, 'resumeSequence');

  if (
    'string' !== typeof token ||
    'string' !== typeof command ||
    'string' !== typeof displayCommand ||
    'number' !== typeof lastSequence ||
    'number' !== typeof resumeSequence
  ) {
    return null;
  }

  return {
    token,
    command,
    displayCommand,
    lastSequence,
    resumeSequence,
  };
};

const normalizePersistedConsoleView = (input: unknown): PersistedConsoleView | null => {
  if (!input || 'object' !== typeof input) {
    return null;
  }

  const command = Reflect.get(input, 'command');
  const displayCommand = Reflect.get(input, 'displayCommand');
  const exitCode = Reflect.get(input, 'exitCode');
  const lastSequence = Reflect.get(input, 'lastSequence');
  const chunks = Reflect.get(input, 'chunks');

  if (
    'string' !== typeof command ||
    'string' !== typeof displayCommand ||
    'number' !== typeof exitCode ||
    'number' !== typeof lastSequence ||
    !Array.isArray(chunks)
  ) {
    return null;
  }

  return {
    command,
    displayCommand,
    exitCode,
    lastSequence,
    chunks: chunks
      .map((chunk) => normalizeConsoleChunk(chunk))
      .filter((chunk): chunk is string => null !== chunk),
  };
};

const makeInitialState = (): ConsoleStreamState => ({
  status: 'idle',
  command: '',
  displayCommand: '',
  exitCode: 0,
  completedAt: 0,
  token: null,
  error: '',
  chunks: [],
  lastSequence: 0,
});

const trimChunks = (state: ConsoleStreamState): void => {
  if (state.chunks.length > MAX_CHUNKS) {
    state.chunks.splice(0, state.chunks.length - MAX_CHUNKS);
  }
};

const appendChunk = (state: ConsoleStreamState, value: string): void => {
  state.chunks.push(value);
  trimChunks(state);
};

let streamController: AbortController | null = null;

const rememberCommand = (history: Array<string>, command: string): Array<string> => {
  if (!command.trim()) {
    return history;
  }

  const next = history.filter((item) => item !== command);
  next.push(command);

  if (next.length > 30) {
    next.splice(0, next.length - 30);
  }

  return next;
};

const normalizeSequence = (value: string): number => {
  if (!value || !/^\d+$/.test(value)) {
    return 0;
  }

  return Number.parseInt(value, 10);
};

export function useConsoleStream() {
  const token = useStorage<string>('token', '');
  const commandHistory = useStorage<Array<string>>('executedCommands', []);
  const activeSession = ref<PersistedConsoleSession | null>(null);
  const persistedView = ref<PersistedConsoleView | null>(null);
  const state = ref<ConsoleStreamState>(makeInitialState());

  const isActive = computed(() =>
    ['starting', 'streaming', 'reconnecting'].includes(state.value.status),
  );

  const stopStream = (): void => {
    if (!streamController) {
      return;
    }

    streamController.abort();
    streamController = null;
  };

  const setPersistedSession = (session: PersistedConsoleSession | null): void => {
    activeSession.value = session;
    writeConsoleStorage(ACTIVE_SESSION_STORAGE_KEY, session);
  };

  const setPersistedView = (view: PersistedConsoleView | null): void => {
    persistedView.value = view;
    writeConsoleStorage(ACTIVE_VIEW_STORAGE_KEY, view);
  };

  const hydratePersistedState = (): void => {
    setPersistedSession(
      normalizePersistedConsoleSession(readConsoleStorage(ACTIVE_SESSION_STORAGE_KEY)),
    );
    setPersistedView(normalizePersistedConsoleView(readConsoleStorage(ACTIVE_VIEW_STORAGE_KEY)));
  };

  const clearPersistedSession = (): void => {
    setPersistedSession(null);
  };

  const clearPersistedView = (): void => {
    setPersistedView(null);
  };

  const syncPersistedView = (): void => {
    if (!activeSession.value) {
      return;
    }

    setPersistedView({
      command: state.value.command,
      displayCommand: state.value.displayCommand,
      exitCode: state.value.exitCode,
      lastSequence: state.value.lastSequence,
      chunks: [...state.value.chunks],
    });
  };

  const appendStateChunk = (value: string): void => {
    appendChunk(state.value, value);
    syncPersistedView();
  };

  const setFatalError = (message: string, clearSession: boolean = true): void => {
    const activeController = streamController;
    streamController = null;

    if (activeController && !activeController.signal.aborted) {
      activeController.abort();
    }

    if (clearSession) {
      clearPersistedSession();
      clearPersistedView();
    }

    state.value.status = 'error';
    state.value.error = message;
    state.value.completedAt = 0;
    state.value.token = null;
  };

  const finalizeRun = (): void => {
    clearPersistedSession();
    clearPersistedView();
    state.value.status = 'idle';
    state.value.token = null;
    state.value.error = '';
    state.value.completedAt = Date.now();
    streamController = null;
  };

  const syncSequence = (sequence: number): void => {
    if (sequence < 1) {
      return;
    }

    state.value.lastSequence = sequence;

    if (!activeSession.value) {
      return;
    }

    setPersistedSession({
      ...activeSession.value,
      lastSequence: sequence,
    });

    syncPersistedView();
  };

  const clearOutput = (): void => {
    state.value.chunks = [];

    if (!activeSession.value) {
      return;
    }

    setPersistedSession({
      ...activeSession.value,
      resumeSequence: activeSession.value.lastSequence,
    });

    syncPersistedView();
  };

  const bufferedChunks = computed<Array<string>>(() => state.value.chunks.slice());

  const appendOutput = (value: string): void => {
    appendStateChunk(value);
  };

  const closeStreamView = (): void => {
    stopStream();
    clearPersistedSession();
    clearPersistedView();
    state.value.status = 'idle';
    state.value.token = null;
    state.value.error = '';
    state.value.completedAt = 0;
  };

  const connectToStream = async (
    mode: 'start' | 'restore',
    historyCommand: string = '',
  ): Promise<ConsoleStreamRunResult> => {
    const session = activeSession.value;
    if (!session) {
      const message = 'Command session is missing.';
      setFatalError(message);
      return { status: 'error', message };
    }

    stopStream();

    const controller = new AbortController();
    const headers: Record<string, string> = {
      Authorization: `Token ${token.value}`,
    };

    const hasBufferedOutput = state.value.chunks.length > 0;
    const cursor = hasBufferedOutput ? session.lastSequence : session.resumeSequence;
    if (cursor > 0) {
      headers['Last-Event-ID'] = String(cursor);
    }

    streamController = controller;
    let didReceiveClose = false;
    let didRememberHistory = false;

    void fetchEventSource(`/v1/api/system/command/${session.token}`, {
      signal: controller.signal,
      headers,
      onopen: async (response: Response): Promise<void> => {
        if (streamController !== controller) {
          return;
        }

        if (response.ok) {
          state.value.status = 'streaming';
          state.value.error = '';
          state.value.token = session.token;

          if ('start' === mode && !didRememberHistory) {
            commandHistory.value = rememberCommand(commandHistory.value, historyCommand);
            didRememberHistory = true;
          }

          return;
        }

        const json = await parse_api_response<GenericResponse>(response);
        let message = `${response.status}: Command stream failed to open.`;

        if ('error' in json) {
          const errorJson = json as GenericError;
          message = `${errorJson.error.code}: ${errorJson.error.message}`;
        } else if (400 === response.status) {
          message = '400: Unable to open command stream.';
        }

        if ('restore' === mode && isExpiredStreamStatus(response.status)) {
          clearPersistedSession();
          clearPersistedView();
          state.value.status = 'idle';
          state.value.token = null;
          state.value.error = '';
          throw new FatalConsoleStreamError(RESTORE_EXPIRED);
        }

        setFatalError(message);
        throw new FatalConsoleStreamError(message);
      },
      onmessage: async (evt: ConsoleEventMessage): Promise<void> => {
        if (streamController !== controller) {
          return;
        }

        syncSequence(normalizeSequence(evt.id));

        switch (evt.event) {
          case 'data': {
            const eventData = JSON.parse(evt.data) as { data: string };
            appendStateChunk(eventData.data);
            break;
          }
          case 'exit_code': {
            const exitCode = Number.parseInt(evt.data, 10);
            state.value.exitCode = Number.isNaN(exitCode) ? 0 : exitCode;
            syncPersistedView();
            break;
          }
          case 'close':
            didReceiveClose = true;
            finalizeRun();
            break;
          default:
            break;
        }
      },
      onclose: (): void => {
        if (streamController !== controller || didReceiveClose) {
          return;
        }

        state.value.status = 'reconnecting';
        state.value.error = '';
        throw new Error('Command stream closed unexpectedly.');
      },
      onerror: (error: unknown): number | undefined => {
        if (streamController !== controller || controller.signal.aborted) {
          return;
        }

        if (error instanceof FatalConsoleStreamError) {
          throw error;
        }

        if (!activeSession.value) {
          const message = error instanceof Error ? error.message : 'Command stream failed.';
          setFatalError(message);
          throw new FatalConsoleStreamError(message);
        }

        state.value.status = 'reconnecting';
        state.value.error = '';
        return 1000;
      },
    }).catch((error: unknown) => {
      if (streamController !== controller || controller.signal.aborted) {
        return;
      }

      streamController = null;

      if (error instanceof FatalConsoleStreamError) {
        if (RESTORE_EXPIRED === error.message) {
          return;
        }

        if ('error' !== state.value.status) {
          setFatalError(error.message);
        }

        return;
      }

      if (activeSession.value) {
        state.value.status = 'reconnecting';
        state.value.error = '';
        return;
      }

      const message = error instanceof Error ? error.message : 'Command stream failed.';
      setFatalError(message);
    });

    return { status: 'started' };
  };

  const startRun = async (
    command: string,
    displayCommand: string,
    historyCommand: string = '',
  ): Promise<ConsoleStreamRunResult> => {
    stopStream();
    clearPersistedSession();
    clearPersistedView();

    state.value.status = 'starting';
    state.value.command = command;
    state.value.displayCommand = displayCommand;
    state.value.exitCode = 0;
    state.value.error = '';
    state.value.lastSequence = 0;
    state.value.completedAt = 0;

    try {
      const response = await request('/system/command', {
        method: 'POST',
        body: JSON.stringify({ command }),
      });

      const json = await parse_api_response<{ token: string }>(response);

      if ('error' in json) {
        const message = `${json.error.code}: ${json.error.message}`;
        setFatalError(message);
        return { status: 'error', message };
      }

      if (201 !== response.status) {
        const message = 'Command request was rejected.';
        setFatalError(message);
        return { status: 'error', message };
      }

      setPersistedSession({
        token: json.token,
        command,
        displayCommand,
        lastSequence: 0,
        resumeSequence: 0,
      });

      state.value.token = json.token;
      syncPersistedView();
      return await connectToStream('start', historyCommand);
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Unknown error occurred';
      setFatalError(message);
      return { status: 'error', message };
    }
  };

  const restoreRun = async (): Promise<boolean> => {
    hydratePersistedState();

    const session = normalizePersistedConsoleSession(activeSession.value);
    const view = normalizePersistedConsoleView(persistedView.value);

    setPersistedSession(session);
    setPersistedView(view);

    if (isActive.value || streamController || !session?.token) {
      return false;
    }

    state.value.command = view?.command ?? session.command;
    state.value.displayCommand = view?.displayCommand ?? session.displayCommand;
    state.value.token = session.token;
    state.value.exitCode = view?.exitCode ?? 0;
    state.value.error = '';
    state.value.chunks = view ? [...view.chunks] : [];
    state.value.lastSequence = view?.lastSequence ?? session.lastSequence;
    state.value.status = 'reconnecting';
    state.value.completedAt = 0;

    await connectToStream('restore');
    return true;
  };

  const stopCommand = async (): Promise<void> => {
    if (!activeSession.value) {
      finalizeRun();
      return;
    }

    try {
      const response = await request(`/system/command/${activeSession.value.token}`, {
        method: 'DELETE',
      });
      const json = await parse_api_response<GenericResponse>(response);

      if ('error' in json) {
        const message = `${json.error.code}: ${json.error.message}`;
        setFatalError(message, false);

        if (404 === response.status) {
          finalizeRun();
        }
      }
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Failed to stop the active command.';
      setFatalError(message, false);
    }
  };

  return {
    commandHistory,
    state,
    bufferedChunks,
    appendOutput,
    isActive,
    clearOutput,
    closeStreamView,
    restoreRun,
    startRun,
    stopCommand,
    stopStream,
  };
}
