<template>
  <main class="w-full min-w-0 max-w-full space-y-4">
    <div class="space-y-1">
      <div
        class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
      >
        <UIcon :name="pageShell.icon" class="size-4" />
        <span>{{ pageShell.sectionLabel }}</span>
        <span>/</span>
        <span>{{ pageShell.pageLabel }}</span>
      </div>
    </div>

    <UCard class="border border-default/70 bg-default/90 shadow-sm" :ui="consoleCardUi">
      <template #header>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <div class="inline-flex items-center gap-2 text-base font-semibold text-highlighted">
                <UIcon name="i-lucide-terminal" class="size-4 text-toned" />
                <span>Terminal</span>
              </div>

              <UBadge color="neutral" variant="soft">
                {{ allEnabled ? 'All commands enabled' : 'Console only' }}
              </UBadge>
            </div>

            <p class="text-sm leading-6 text-default">
              <template v-if="allEnabled">
                Run non-interactive commands directly. Prefix shell commands with <code>$</code>
                when needed.
              </template>
              <template v-else>
                Run non-interactive <code>console</code> commands directly.
              </template>
            </p>
          </div>

          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            icon="i-lucide-eraser"
            aria-label="Clear terminal output"
            @click="clearOutput"
          >
            <span class="hidden sm:inline">Clear</span>
          </UButton>
        </div>
      </template>

      <div class="space-y-4">
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

        <div class="overflow-hidden rounded-md border border-default bg-neutral-950/95">
          <div ref="outputConsole" class="min-h-[60vh] max-h-[70vh] overflow-hidden" />
        </div>
      </div>

      <template #footer>
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
          <UFormField label="Command" name="command" class="flex-1">
            <UInput
              ref="commandInput"
              v-model="command"
              type="text"
              :placeholder="`system:view ${allEnabled ? 'or $ ls' : ''}`"
              list="recent_commands"
              autocomplete="off"
              :disabled="isLoading"
              :icon="isLoading ? 'i-lucide-loader-circle' : 'i-lucide-terminal'"
              :ui="isLoading ? { leadingIcon: 'animate-spin' } : undefined"
              class="w-full"
              @keydown.enter="RunCommand"
            />
          </UFormField>

          <div class="flex flex-wrap items-center justify-end gap-2">
            <UButton
              v-if="isLoading"
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-lucide-power"
              @click="finished"
            >
              <span class="hidden sm:inline">Close</span>
            </UButton>

            <UButton
              v-else
              color="primary"
              variant="solid"
              size="sm"
              icon="i-lucide-send"
              :disabled="hasPrefix"
              @click="RunCommand"
            >
              Execute
            </UButton>
          </div>
        </div>
      </template>
    </UCard>

    <UCard class="border border-default/70 bg-default/90 shadow-sm" :ui="tipsCardUi">
      <template #header>
        <button
          type="button"
          class="flex w-full items-center justify-between gap-3 text-left"
          @click="show_page_tips = !show_page_tips"
        >
          <span class="inline-flex items-center gap-2 text-sm font-semibold text-highlighted">
            <UIcon name="i-lucide-info" class="size-4 text-toned" />
            <span>Tips</span>
          </span>

          <span class="inline-flex items-center gap-1 text-xs font-medium text-toned">
            <UIcon
              :name="show_page_tips ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
              class="size-4"
            />
            <span>{{ show_page_tips ? 'Hide' : 'Show' }}</span>
          </span>
        </button>
      </template>

      <ul v-if="show_page_tips" class="list-disc space-y-2 pl-5 text-sm leading-6 text-default">
        <li>
          You don’t need to type <code>console</code> or run
          <code>docker exec -ti watchstate console</code> when using this interface. Just enter the
          command and options directly. For example: <code>db:list --output yaml</code>.
        </li>
        <li>
          Clicking <strong>Close</strong> only stops the output from being shown. It does
          <em>not</em> stop the command itself. The command will continue running until it finishes.
        </li>
        <li>
          Most commands won’t display anything unless there’s an error or important message. Use
          <code>-v</code> to see more details. If you’re debugging, try <code>-vv --context</code>
          for even more information.
        </li>
        <li>
          There’s an environment variable <code>WS_CONSOLE_ENABLE_ALL</code> that you can set to
          <code>true</code> to allow all commands to run from the console. It’s turned off by
          default.
        </li>
        <li>To clear the recent command suggestions, use the <code>clear_ac</code> command.</li>
        <li>
          The number inside the parentheses is the exit code of the last command. If it’s
          <code>0</code>, the command ran successfully. Any other value usually means something went
          wrong.
        </li>
        <li>Some commands may</li>
      </ul>
    </UCard>

    <datalist id="recent_commands">
      <option v-for="item in recentCommands" :key="item" :value="item" />
    </datalist>
  </main>
</template>

<style scoped>
.xterm {
  padding: 0.5rem !important;
}

.xterm-viewport {
  background-color: #1f2229 !important;
}
</style>

<script setup lang="ts">
import '@xterm/xterm/css/xterm.css';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useHead, useRoute, useRouter } from '#app';
import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { useStorage } from '@vueuse/core';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { request, disableOpacity, enableOpacity, notification, parse_api_response } from '~/utils';
import { fetchEventSource } from '@microsoft/fetch-event-source';
import type { EnvVar, GenericError, GenericResponse } from '~/types';
import { useDialog } from '~/composables/useDialog';

useHead({ title: 'Console' });

const pageShell = requireTopLevelPageShell('console');

const route = useRoute();
const fromCommand: string =
  route.query.cmd && 'string' === typeof route.query.cmd ? atob(route.query.cmd) : '';

type ConsoleInputRef = {
  inputRef?: HTMLInputElement | null;
};

let sse: (() => void) | null = null;
const terminal = ref<Terminal | null>(null);
const terminalFit = ref<FitAddon | null>(null);
const response = ref<Array<unknown>>([]);
const command = ref<string>(fromCommand);
const isLoading = ref<boolean>(false);
const outputConsole = ref<HTMLElement | null>(null);
const commandInput = ref<ConsoleInputRef | null>(null);
const executedCommands = useStorage<Array<string>>('executedCommands', []);
const exitCode = ref<number>(0);

const hasPrefix = computed(
  () => command.value.startsWith('console') || command.value.startsWith('docker'),
);
const hasPlaceholder = computed(() => command.value && command.value.match(/\[.*]/));
const show_page_tips = useStorage<boolean>('show_page_tips', true);
const allEnabled = ref<boolean>(false);
const ctrl = new AbortController();

const consoleCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
  footer: 'border-t border-default p-4',
};

const tipsCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const focusCommandInput = (): void => {
  commandInput.value?.inputRef?.focus({ preventScroll: true });
};

const RunCommand = async (): Promise<void> => {
  const token = useStorage<string>('token', '');

  let userCommand: string = command.value;

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

  response.value = [];

  if ('clear' === userCommand) {
    command.value = '';
    if (terminal.value) {
      terminal.value.clear();
    }
    return;
  }

  if ('clear_ac' === userCommand) {
    executedCommands.value = [];
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

  isLoading.value = true;
  let commandToken: string;

  try {
    const response = await request('/system/command', {
      method: 'POST',
      body: JSON.stringify(commandBody),
    });

    const json = await parse_api_response<{ token: string }>(response);

    if ('error' in json) {
      await finished();
      notification('error', 'Error', `${json.error.code}: ${json.error.message}`, 5000);
      return;
    }

    if (201 !== response.status) {
      await finished();
      return;
    }

    commandToken = json.token;
  } catch (e: unknown) {
    await finished();
    const errorMessage = e instanceof Error ? e.message : 'Unknown error occurred';
    notification('error', 'Error', errorMessage, 5000);
    return;
  }

  fetchEventSource(`/v1/api/system/command/${commandToken}`, {
    signal: ctrl.signal,
    headers: { Authorization: `Token ${token.value}` },
    onmessage: async (evt: { event: string; data: string }): Promise<void> => {
      switch (evt.event) {
        case 'data':
          if (terminal.value) {
            const eventData = JSON.parse(evt.data) as { data: string };
            terminal.value.write(eventData.data);
          }
          break;
        case 'close':
          await finished();
          break;
        case 'exit_code':
          exitCode.value = parseInt(evt.data);
          break;
        default:
          break;
      }
    },
    onopen: async (response: Response): Promise<void> => {
      if (response.ok) {
        return;
      }

      const json = await parse_api_response<GenericResponse>(response);

      if ('error' in json) {
        const errorJson = json as GenericError;
        if (400 === errorJson.error.code) {
          ctrl.abort();
          return;
        }
        const message = `${errorJson.error.code}: ${errorJson.error.message}`;
        notification('error', 'Error', message, 3000);
        await finished();
        return;
      }

      if (400 === response.status) {
        ctrl.abort();
        return;
      }

      await finished();
    },
    onerror: (e: unknown): void => {
      console.log(e);
    },
  });

  sse = () => ctrl.abort();

  if ('' !== command.value && terminal.value) {
    terminal.value.writeln(`(${exitCode.value}) ~ ${userCommand}`);
  }
};

const finished = async (): Promise<void> => {
  if (sse) {
    sse();
    sse = null;
  }

  isLoading.value = false;

  const route = useRoute();

  if (route.query?.cmd || route.query?.run) {
    route.query.cmd = '';
    route.query.run = '';
    await useRouter().push({ path: '/console' });
  }

  if (executedCommands.value.includes(command.value)) {
    executedCommands.value.splice(executedCommands.value.indexOf(command.value), 1);
  }

  executedCommands.value.push(command.value);

  if (30 < executedCommands.value.length) {
    executedCommands.value.shift();
  }

  if (terminal.value) {
    terminal.value.writeln(`\n(${exitCode.value}) ~ `);
  }

  command.value = '';
  await nextTick();

  focusCommandInput();
};

const recentCommands = computed(() => executedCommands.value.slice(-10).reverse());

const reSizeTerminal = (): void => {
  if (!terminal.value || !terminalFit.value) {
    return;
  }
  terminalFit.value.fit();
};

const clearOutput = async (): Promise<void> => {
  if (terminal.value) {
    terminal.value.clear();
  }
  focusCommandInput();
};

onUnmounted(() => {
  window.removeEventListener('resize', reSizeTerminal);
  if (sse) {
    sse();
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
    terminalFit.value.fit();
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

  const run: boolean = route.query?.run ? Boolean(route.query.run) : false;
  if (true === run && command.value) {
    await RunCommand();
  }
});
</script>
