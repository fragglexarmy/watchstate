<template>
  <main class="w-full min-w-0 max-w-full space-y-4">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
      <div class="min-w-0 space-y-1">
        <div
          class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
        >
          <UIcon :name="pageShell.icon" class="size-4" />
          <span>{{ pageShell.sectionLabel }}</span>
          <span>/</span>
          <span>{{ pageShell.pageLabel }}</span>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-eraser"
          aria-label="Clear terminal output"
          @click="clearOutput"
        >
          Clear output
        </UButton>
      </div>
    </div>

    <div class="overflow-hidden border border-default bg-neutral-950/95 shadow-sm">
      <div ref="outputConsole" class="min-h-[55vh] max-h-[55vh] overflow-hidden" />
    </div>

    <div class="rounded-md border border-default bg-default shadow-sm">
      <div
        class="flex flex-col gap-3 border-b border-default bg-muted/10 px-4 py-3 lg:flex-row lg:items-start lg:justify-between"
      >
        <div class="min-w-0 flex-1 space-y-1">
          <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2 text-sm font-semibold text-highlighted">
              <UIcon name="i-lucide-send" class="size-4 shrink-0 text-toned" />
              <span>Command</span>
            </div>

            <UButton
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-lucide-circle-help"
              class="shrink-0"
              :disabled="isLoading"
              @click="showHelp"
            >
              Help
            </UButton>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs text-toned">
            <p>
              <template v-if="allEnabled">
                Shell commands are available when prefixed with <code>$</code>.
              </template>
              <template v-else>
                Shell commands stay disabled unless <code>WS_CONSOLE_ENABLE_ALL</code> is enabled.
              </template>
            </p>
            <UBadge :color="streamStatusColor" variant="soft" size="sm">
              <span v-if="streamStatusSpinning" class="inline-flex items-center gap-1.5">
                <UIcon :name="streamStatusIcon" class="size-3.5 animate-spin" />
                <span>{{ streamStatusLabel }}</span>
              </span>
              <span v-else class="inline-flex items-center gap-1.5">
                <UIcon :name="streamStatusIcon" class="size-3.5" />
                <span>{{ streamStatusLabel }}</span>
              </span>
            </UBadge>
          </div>
        </div>
      </div>

      <div class="space-y-3 px-4 py-4">
        <UAlert
          v-if="streamState.error"
          color="error"
          variant="soft"
          icon="i-lucide-triangle-alert"
          title="Command stream failed"
          :description="streamState.error"
        />

        <UAlert
          v-if="hasPrefix"
          color="warning"
          variant="soft"
          icon="i-lucide-triangle-alert"
          title="Remove the prefix"
          description="Use the command directly, for example `db:list --output yaml`."
        />

        <UAlert
          v-if="hasPlaceholder"
          color="warning"
          variant="soft"
          icon="i-lucide-triangle-alert"
          title="Placeholder values found"
        >
          <template #description>
            <p class="text-sm text-default">
              Replace <code>[...]</code> with the intended value if applicable before running the
              command.
            </p>
          </template>
        </UAlert>

        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
          <UInput
            ref="commandInput"
            v-model="command"
            type="text"
            size="lg"
            aria-label="Command"
            :placeholder="`system:tasks ${allEnabled ? 'or $ ls' : ''}`"
            autocomplete="off"
            :disabled="isLoading"
            :icon="isLoading ? 'i-lucide-loader-circle' : 'i-lucide-terminal'"
            :ui="isLoading ? { leadingIcon: 'animate-spin' } : undefined"
            class="ws-console-input w-full"
            @keydown.enter="RunCommand"
          />

          <div class="flex flex-wrap items-center justify-end gap-2 xl:self-end">
            <UPopover :content="{ side: 'top', align: 'end', sideOffset: 8 }">
              <UButton
                color="neutral"
                variant="outline"
                size="lg"
                icon="i-lucide-history"
                trailing-icon="i-lucide-chevron-up"
                class="flex-1 justify-center sm:flex-none sm:min-w-36"
              >
                History
              </UButton>

              <template #content>
                <UCard
                  class="w-[min(92vw,42rem)] border border-default/70 shadow-sm"
                  :ui="historyCardUi"
                >
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm font-semibold text-highlighted">
                      <UIcon name="i-lucide-history" class="size-4 text-toned" />
                      <span>Command history</span>
                    </div>

                    <UButton
                      color="neutral"
                      variant="outline"
                      size="sm"
                      icon="i-lucide-trash"
                      :disabled="commandHistory.length < 1"
                      @click="clearHistory"
                    >
                      Clear history
                    </UButton>
                  </div>

                  <UAlert
                    v-if="commandHistory.length < 1"
                    color="info"
                    variant="soft"
                    icon="i-lucide-clock-3"
                    title="Command history is empty"
                  />

                  <div
                    v-else
                    class="max-h-96 overflow-auto rounded-lg border border-default bg-default"
                  >
                    <table class="w-full text-sm">
                      <tbody class="divide-y divide-default">
                        <tr v-for="item in commandHistory" :key="item" class="hover:bg-muted/20">
                          <td class="px-3 py-3 align-middle">
                            <button
                              type="button"
                              class="block w-full text-left font-mono text-xs text-default hover:text-highlighted"
                              @click="loadCommand(item)"
                            >
                              {{ item }}
                            </button>
                          </td>

                          <td class="w-12 px-3 py-3 text-center align-middle whitespace-nowrap">
                            <UButton
                              color="neutral"
                              variant="ghost"
                              size="xs"
                              icon="i-lucide-x"
                              square
                              @click="removeFromHistory(item)"
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </UCard>
              </template>
            </UPopover>

            <UButton
              v-if="isLoading"
              color="neutral"
              variant="outline"
              size="lg"
              icon="i-lucide-power"
              class="flex-1 justify-center sm:flex-none sm:min-w-36"
              @click="closeOutput"
            >
              Close output
            </UButton>

            <UButton
              v-else
              color="primary"
              variant="solid"
              size="lg"
              icon="i-lucide-send"
              :disabled="hasPrefix || !hasRunnableCommand"
              class="flex-1 justify-center sm:flex-none sm:min-w-36"
              @click="RunCommand"
            >
              Run command
            </UButton>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.ws-console-input :deep(input) {
  font-family: 'JetBrains Mono', monospace;
}

.xterm {
  padding: 0.5rem !important;
}

.xterm-viewport {
  background-color: #1f2229 !important;
}
</style>

<script setup lang="ts">
import '@xterm/xterm/css/xterm.css';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useHead, useRoute, useRouter } from '#app';
import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { request, disableOpacity, enableOpacity, notification, parse_api_response } from '~/utils';
import type { EnvVar } from '~/types';
import { useDialog } from '~/composables/useDialog';
import { useConsoleStream } from '~/composables/useConsoleStream';

useHead({ title: 'Console' });

const pageShell = requireTopLevelPageShell('console');

const route = useRoute();
const fromCommand: string =
  route.query.cmd && 'string' === typeof route.query.cmd ? atob(route.query.cmd) : '';

type ConsoleInputRef = {
  inputRef?: HTMLInputElement | null;
};

let flushFrame: number | null = null;
let fitFrame: number | null = null;
let terminalResizeObserver: ResizeObserver | null = null;
const terminal = ref<Terminal | null>(null);
const terminalFit = ref<FitAddon | null>(null);
const command = ref<string>(fromCommand);
const outputConsole = ref<HTMLElement | null>(null);
const commandInput = ref<ConsoleInputRef | null>(null);
const {
  commandHistory,
  state: streamState,
  unflushedChunks,
  clearOutput: clearStreamOutput,
  closeStreamView,
  markChunkFlushed,
  resetFlushedOutput,
  restoreRun,
  startRun,
} = useConsoleStream();

const isLoading = computed(() =>
  ['starting', 'streaming', 'reconnecting'].includes(streamState.value.status),
);

const streamStatusLabel = computed(() => {
  switch (streamState.value.status) {
    case 'starting':
      return 'Starting';
    case 'streaming':
      return 'Streaming';
    case 'reconnecting':
      return 'Reconnecting';
    case 'error':
      return 'Failed';
    default:
      return 'Idle';
  }
});

const streamStatusColor = computed(() => {
  switch (streamState.value.status) {
    case 'starting':
    case 'streaming':
    case 'reconnecting':
      return 'info';
    case 'error':
      return 'error';
    default:
      return 'neutral';
  }
});

const streamStatusIcon = computed(() => {
  switch (streamState.value.status) {
    case 'starting':
    case 'streaming':
    case 'reconnecting':
      return 'i-lucide-loader-circle';
    case 'error':
      return 'i-lucide-triangle-alert';
    default:
      return 'i-lucide-circle-dot';
  }
});

const streamStatusSpinning = computed(() => {
  return ['starting', 'streaming', 'reconnecting'].includes(streamState.value.status);
});

const hasPrefix = computed(
  () => command.value.startsWith('console') || command.value.startsWith('docker'),
);
const hasPlaceholder = computed(() => command.value && command.value.match(/\[.*]/));
const hasRunnableCommand = computed(() => Boolean(command.value.trim()));
const allEnabled = ref<boolean>(false);

const historyCardUi = {
  body: 'space-y-3 p-4',
};

const focusCommandInput = (): void => {
  commandInput.value?.inputRef?.focus({ preventScroll: true });
};

const scheduleTerminalFit = (): void => {
  if (!terminal.value || !terminalFit.value) {
    return;
  }

  if (fitFrame) {
    return;
  }

  fitFrame = window.requestAnimationFrame(() => {
    fitFrame = null;

    if (!terminal.value || !terminalFit.value) {
      return;
    }

    terminalFit.value.fit();
  });
};

const restoreBufferedTerminalOutput = (): void => {
  if (streamState.value.chunks.length < 1) {
    return;
  }

  resetFlushedOutput();
  scheduleFlush();
  scheduleTerminalFit();

  window.requestAnimationFrame(() => {
    scheduleTerminalFit();
  });
};

const bindTerminalResizeObserver = (): void => {
  if (!outputConsole.value || 'undefined' === typeof ResizeObserver) {
    return;
  }

  terminalResizeObserver?.disconnect();
  terminalResizeObserver = new ResizeObserver(() => {
    scheduleTerminalFit();
  });
  terminalResizeObserver.observe(outputConsole.value);
};

const flushTerminal = (): void => {
  if (!terminal.value || unflushedChunks.value.length < 1) {
    return;
  }

  const pending = [...unflushedChunks.value];
  const text = pending.map((chunk) => chunk.value).join('');

  if (!text) {
    return;
  }

  terminal.value.write(text);

  for (const chunk of pending) {
    markChunkFlushed(chunk.id);
  }
};

const scheduleFlush = (): void => {
  if (flushFrame) {
    return;
  }

  flushFrame = window.requestAnimationFrame(() => {
    flushFrame = null;
    flushTerminal();
  });
};

const RunCommand = async (): Promise<void> => {
  let userCommand: string = command.value;
  const historyCommand = userCommand;

  if (userCommand.startsWith('console') || userCommand.startsWith('docker')) {
    notification('info', 'Warning', 'Removing leading prefix command from the input.', 2000);
    userCommand = userCommand.replace(/^(console|docker exec -ti watchstate)/i, '');
  }

  if (userCommand.match(/\[.*]/)) {
    const { status } = await useDialog().confirmDialog({
      title: 'Confirm command',
      message: 'The command contains placeholders "[...]". Are you sure you want to run as it is?',
    });

    if (true !== status) {
      return;
    }
  }

  if ('clear' === userCommand) {
    command.value = '';
    if (terminal.value) {
      terminal.value.clear();
    }
    clearStreamOutput();
    return;
  }

  if ('clear_ac' === userCommand) {
    commandHistory.value = [];
    command.value = '';
    return;
  }

  const commandBody: { command: string } = JSON.parse(JSON.stringify({ command: userCommand }));

  if (userCommand.startsWith('$')) {
    if (!allEnabled.value) {
      notification('error', 'Error', 'The option to execute all commands is disabled.');
      focusCommandInput();
      return;
    }
    userCommand = userCommand.slice(1);
  } else {
    userCommand = `console ${userCommand}`;
  }

  const result = await startRun(commandBody.command, userCommand, historyCommand);

  if ('error' === result.status) {
    notification('error', 'Error', result.message, 5000);
    focusCommandInput();
    return;
  }

  if ('blocked' === result.status) {
    focusCommandInput();
    return;
  }

  if (route.query?.cmd || route.query?.run) {
    await useRouter().replace({ path: '/console' });
  }

  command.value = commandBody.command;
  await nextTick();

  focusCommandInput();
};

const reSizeTerminal = (): void => {
  if (!terminal.value || !terminalFit.value) {
    return;
  }

  scheduleTerminalFit();
};

const clearOutput = async (): Promise<void> => {
  if (terminal.value) {
    terminal.value.clear();
  }
  clearStreamOutput();
  focusCommandInput();
};

const showHelp = async (): Promise<void> => {
  if (isLoading.value) {
    return;
  }

  command.value = '';
  await RunCommand();
};

const closeOutput = (): void => {
  closeStreamView();
  focusCommandInput();
};

const loadCommand = async (value: string): Promise<void> => {
  command.value = value;
  await nextTick();
  focusCommandInput();
};

const clearHistory = async (): Promise<void> => {
  if (commandHistory.value.length < 1) {
    return;
  }

  const { status } = await useDialog().confirmDialog({
    title: 'Confirm Action',
    message: 'Clear saved command history?',
    confirmColor: 'error',
  });

  if (true !== status) {
    return;
  }

  commandHistory.value = [];
  focusCommandInput();
};

const removeFromHistory = (value: string): void => {
  commandHistory.value = commandHistory.value.filter((item) => item !== value);
};

onUnmounted(() => {
  window.removeEventListener('resize', reSizeTerminal);
  terminalResizeObserver?.disconnect();
  terminalResizeObserver = null;
  if (flushFrame) {
    window.cancelAnimationFrame(flushFrame);
    flushFrame = null;
  }
  if (fitFrame) {
    window.cancelAnimationFrame(fitFrame);
    fitFrame = null;
  }
  enableOpacity();
});

onMounted(async () => {
  disableOpacity();

  window.addEventListener('resize', reSizeTerminal);

  focusCommandInput();

  if (!terminal.value && outputConsole.value) {
    terminalFit.value = new FitAddon();
    terminal.value = new Terminal({
      fontSize: 16,
      fontFamily: "'JetBrains Mono', monospace",
      cursorBlink: false,
      disableStdin: true,
      convertEol: true,
      altClickMovesCursor: false,
    });
    terminal.value.open(outputConsole.value);
    terminal.value.loadAddon(terminalFit.value);
    bindTerminalResizeObserver();

    await nextTick();
    scheduleTerminalFit();

    if ('fonts' in document) {
      void document.fonts.ready.then(() => {
        scheduleTerminalFit();
      });
    }

    if (streamState.value.chunks.length > 0) {
      restoreBufferedTerminalOutput();
    }
  }

  const run: boolean = route.query?.run ? Boolean(route.query.run) : false;
  if (true === run && command.value) {
    await RunCommand();
  } else {
    const restored = await restoreRun();

    if (restored) {
      command.value = streamState.value.command;
      await nextTick();
      restoreBufferedTerminalOutput();
    }
  }

  try {
    const response = await request('/system/env/WS_CONSOLE_ENABLE_ALL');
    const json = await parse_api_response<EnvVar>(response);

    if (response.ok && 'value' in json) {
      allEnabled.value = Boolean(json.value);
    } else {
      allEnabled.value = false;
    }
  } catch {
    allEnabled.value = false;
  }
});

watch(
  unflushedChunks,
  () => {
    if (!terminal.value || unflushedChunks.value.length < 1) {
      return;
    }

    scheduleFlush();
  },
  { deep: true },
);

watch(
  () => streamState.value.error,
  (message) => {
    if (!message) {
      return;
    }

    notification('error', 'Error', message, 5000);
    focusCommandInput();
  },
);

watch(
  () => streamState.value.status,
  () => {
    if (streamState.value.command) {
      command.value = streamState.value.command;
    }
  },
);
</script>
