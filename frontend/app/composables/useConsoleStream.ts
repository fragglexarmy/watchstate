import { computed } from 'vue';
import { useStorage } from '@vueuse/core';
import { useState } from '#app';
import { fetchEventSource } from '@microsoft/fetch-event-source';
import { request, parse_api_response } from '~/utils';
import type { GenericError, GenericResponse } from '~/types';

type ConsoleChunk = {
  id: string;
  value: string;
};

type ConsoleStreamRunResult =
  | { status: 'started' }
  | { status: 'blocked' }
  | { status: 'error'; message: string };

type ConsoleStreamState = {
  status: 'idle' | 'starting' | 'streaming' | 'error';
  command: string;
  displayCommand: string;
  exitCode: number;
  token: string | null;
  error: string;
  chunks: Array<ConsoleChunk>;
  streamedIds: Array<string>;
};

const MAX_CHUNKS = 4000;

const makeInitialState = (): ConsoleStreamState => ({
  status: 'idle',
  command: '',
  displayCommand: '',
  exitCode: 0,
  token: null,
  error: '',
  chunks: [],
  streamedIds: [],
});

const appendChunk = (state: ConsoleStreamState, value: string): void => {
  state.chunks.push({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
    value,
  });

  if (state.chunks.length > MAX_CHUNKS) {
    state.chunks.splice(0, state.chunks.length - MAX_CHUNKS);
  }
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

export function useConsoleStream() {
  const token = useStorage<string>('token', '');
  const commandHistory = useStorage<Array<string>>('executedCommands', []);
  const state = useState<ConsoleStreamState>('console-stream-state', makeInitialState);

  const isActive = computed(
    () => 'starting' === state.value.status || 'streaming' === state.value.status,
  );

  const stopStream = (): void => {
    if (!streamController) {
      return;
    }

    streamController.abort();
    streamController = null;
  };

  const setError = (message: string): void => {
    const activeController = streamController;
    streamController = null;

    if (activeController && !activeController.signal.aborted) {
      activeController.abort();
    }

    state.value.status = 'error';
    state.value.error = message;
    state.value.token = null;
  };

  const finalizeRun = (): void => {
    state.value.status = 'idle';
    state.value.token = null;
    state.value.error = '';
    streamController = null;
  };

  const clearOutput = (): void => {
    state.value.chunks = [];
    state.value.streamedIds = [];
  };

  const resetFlushedOutput = (): void => {
    state.value.streamedIds = [];
  };

  const markChunkFlushed = (id: string): void => {
    if (state.value.streamedIds.includes(id)) {
      return;
    }

    state.value.streamedIds.push(id);

    if (state.value.streamedIds.length > MAX_CHUNKS) {
      state.value.streamedIds.splice(0, state.value.streamedIds.length - MAX_CHUNKS);
    }
  };

  const unflushedChunks = computed<Array<ConsoleChunk>>(() =>
    state.value.chunks.filter((chunk) => !state.value.streamedIds.includes(chunk.id)),
  );

  const closeStreamView = (): void => {
    stopStream();
    state.value.status = 'idle';
    state.value.token = null;
    state.value.error = '';
  };

  const startRun = async (
    command: string,
    displayCommand: string,
    historyCommand: string = '',
  ): Promise<ConsoleStreamRunResult> => {
    stopStream();

    state.value.status = 'starting';
    state.value.command = command;
    state.value.displayCommand = displayCommand;
    state.value.exitCode = 0;
    state.value.error = '';

    if (historyCommand.trim()) {
      appendChunk(state.value, `(${state.value.exitCode}) ~ ${displayCommand}\n`);
    }

    let commandToken = '';

    try {
      const response = await request('/system/command', {
        method: 'POST',
        body: JSON.stringify({ command }),
      });

      const json = await parse_api_response<{ token: string }>(response);

      if ('error' in json) {
        const message = `${json.error.code}: ${json.error.message}`;
        setError(message);
        return { status: 'error', message };
      }

      if (201 !== response.status) {
        const message = 'Command request was rejected.';
        setError(message);
        return { status: 'error', message };
      }

      commandToken = json.token;
      state.value.token = commandToken;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Unknown error occurred';
      setError(message);
      return { status: 'error', message };
    }

    const controller = new AbortController();
    streamController = controller;
    let didReceiveClose = false;

    void fetchEventSource(`/v1/api/system/command/${commandToken}`, {
      signal: controller.signal,
      headers: { Authorization: `Token ${token.value}` },
      onopen: async (response: Response): Promise<void> => {
        if (streamController !== controller) {
          return;
        }

        if (response.ok) {
          state.value.status = 'streaming';
          commandHistory.value = rememberCommand(commandHistory.value, historyCommand);
          return;
        }

        const json = await parse_api_response<GenericResponse>(response);

        if ('error' in json) {
          const errorJson = json as GenericError;
          const message = `${errorJson.error.code}: ${errorJson.error.message}`;
          setError(message);
          throw new Error(message);
        }

        if (400 === response.status) {
          const message = '400: Unable to open command stream.';
          setError(message);
          throw new Error(message);
        }

        const message = `${response.status}: Command stream failed to open.`;
        setError(message);
        throw new Error(message);
      },
      onmessage: async (evt: { event: string; data: string }): Promise<void> => {
        if (streamController !== controller) {
          return;
        }

        switch (evt.event) {
          case 'data': {
            const eventData = JSON.parse(evt.data) as { data: string };
            appendChunk(state.value, eventData.data);
            break;
          }
          case 'exit_code':
            state.value.exitCode = parseInt(evt.data);
            break;
          case 'close':
            didReceiveClose = true;
            appendChunk(state.value, `\n(${state.value.exitCode}) ~ `);
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

        if ('error' === state.value.status) {
          return;
        }

        appendChunk(state.value, `\n(${state.value.exitCode}) ~ `);
        finalizeRun();
      },
      onerror: (error: unknown): void => {
        if (streamController !== controller || controller.signal.aborted) {
          return;
        }

        if ('error' === state.value.status) {
          return;
        }

        const message = error instanceof Error ? error.message : 'Command stream failed.';
        setError(message);
      },
    }).catch((error: unknown) => {
      if (streamController !== controller || controller.signal.aborted) {
        return;
      }

      if ('error' === state.value.status) {
        return;
      }

      const message = error instanceof Error ? error.message : 'Command stream failed.';
      setError(message);
    });

    return { status: 'started' };
  };

  return {
    commandHistory,
    state,
    isActive,
    unflushedChunks,
    clearOutput,
    closeStreamView,
    markChunkFlushed,
    resetFlushedOutput,
    startRun,
    stopStream,
  };
}
