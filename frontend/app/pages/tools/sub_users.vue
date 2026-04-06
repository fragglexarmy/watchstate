<template>
  <main class="w-full min-w-0 max-w-full space-y-4">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
      <div class="space-y-1">
        <div
          class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
        >
          <UIcon :name="pageShell.icon" class="size-4" />
          <span>{{ pageShell.sectionLabel }}</span>
          <span>/</span>
          <span>{{ pageShell.pageLabel }}</span>
        </div>

        <div>
          <p class="mt-1 text-sm text-toned">
            Drag and drop backend users into groups to build sub-user associations.
            <template v-if="expires">
              Cached results expire {{ moment(expires).fromNow() }}.
            </template>
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-file-output"
          :disabled="userWithNoPin.length > 0"
          @click="generateFile"
          label="Export"
        />

        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-plus"
          @click="addNewUser"
          label="Add group"
        />

        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-refresh-cw"
          :loading="isLoading"
          :disabled="isLoading"
          @click="loadContent(true)"
          label="Reload"
        />
      </div>
    </div>

    <UAlert
      v-if="isLoading"
      color="info"
      variant="soft"
      icon="i-lucide-loader-circle"
      title="Loading"
      description="Loading data. Please wait..."
      :ui="{ icon: 'animate-spin' }"
    />

    <UAlert
      v-if="!isLoading && userWithNoPin.length > 0"
      color="warning"
      variant="soft"
      icon="i-lucide-triangle-alert"
      title="User/s missing PIN"
    >
      <template #description>
        <div class="space-y-2 text-sm text-default">
          <p>
            The following users are missing a PIN. Click on
            <UIcon name="i-lucide-lock-open" class="inline size-4 align-text-bottom" /> to set the
            user PIN. Otherwise you will not be able to proceed.
          </p>
          <div class="flex flex-wrap gap-2">
            <UBadge
              v-for="(user, index) in userWithNoPin"
              :key="index"
              color="warning"
              variant="soft"
            >
              {{ user }}
            </UBadge>
          </div>
        </div>
      </template>
    </UAlert>

    <UAlert
      v-if="matched?.length < 1 && !isLoading && !allowSingleBackendUsers"
      color="error"
      variant="soft"
      icon="i-lucide-triangle-alert"
      title="No matched users."
      description="Click on the add button to user group"
    />

    <div v-if="matched.length > 0" class="grid gap-4 xl:grid-cols-2">
      <UCard
        v-for="(group, index) in matched"
        :key="index"
        class="h-full border shadow-sm"
        :class="group.matched.length >= 2 ? 'border-success/50' : 'border-warning/50'"
        :ui="groupCardUi"
      >
        <template #header>
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
              <UTooltip :text="String(group.user)">
                <h2 class="truncate text-base font-semibold text-highlighted">{{ group.user }}</h2>
              </UTooltip>
            </div>

            <div class="flex shrink-0 items-center gap-2">
              <UBadge color="neutral" variant="soft" size="sm">{{ group.matched.length }}</UBadge>

              <UButton
                color="neutral"
                variant="outline"
                size="sm"
                icon="i-lucide-trash-2"
                aria-label="Delete group"
                @click="deleteGroup(index)"
                label="Delete"
              />
            </div>
          </div>
        </template>

        <draggable
          v-model="group.matched"
          :group="{ name: 'shared', pull: true, put: true }"
          animation="150"
          :move="checkBackend"
          item-key="id"
          class="flex min-h-20 flex-wrap gap-2 rounded-md border border-dashed border-default bg-elevated/20 p-2"
        >
          <template #item="{ element }">
            <div class="ws-sub-user-chip" :class="setClass(element)">
              <button
                v-if="element?.protected"
                type="button"
                class="inline-flex items-center text-toned hover:text-primary"
                @click="setUserPin(element)"
              >
                <UTooltip text="Click to set/view user PIN">
                  <UIcon
                    :name="element?.options?.PLEX_USER_PIN ? 'i-lucide-lock' : 'i-lucide-lock-open'"
                    class="size-4"
                  />
                </UTooltip>
              </button>

              <span class="min-w-0">
                <span class="font-medium text-highlighted"
                  >{{ element.backend }}@{{ element.username }}</span
                >
                <span v-if="!isSameName(element.real_name, element.username)">
                  (<span class="underline">{{ element.real_name }}</span
                  >)
                </span>
              </span>
            </div>
          </template>

          <template #footer>
            <div
              v-if="group.matched.length < 1"
              class="ws-sub-user-chip ws-sub-user-chip-placeholder"
            >
              <span class="font-medium text-toned">Drop users here.</span>
            </div>
          </template>
        </draggable>
      </UCard>
    </div>

    <UCard
      v-if="!isLoading"
      class="border shadow-sm"
      :class="allowSingleBackendUsers ? 'border-info/50' : 'border-error/50'"
      :ui="groupCardUi"
    >
      <template #header>
        <div class="flex items-center justify-between gap-3">
          <div
            class="flex min-w-0 items-center gap-2 text-base font-semibold"
            :class="allowSingleBackendUsers ? 'text-highlighted' : 'text-error'"
          >
            <UIcon
              :name="allowSingleBackendUsers ? 'i-lucide-info' : 'i-lucide-triangle-alert'"
              class="size-4 shrink-0"
            />
            <span class="truncate">{{
              allowSingleBackendUsers ? 'Single Backend Mode Enabled' : 'Unmatched Users'
            }}</span>
          </div>

          <UBadge color="neutral" variant="soft" size="sm">{{ unmatched.length }}</UBadge>
        </div>
      </template>

      <template #default>
        <draggable
          v-if="unmatched?.length > 0"
          v-model="unmatched"
          :group="{ name: 'shared', pull: true, put: true }"
          animation="150"
          :move="checkBackend"
          item-key="id"
          class="flex min-h-20 flex-wrap gap-2 rounded-md border border-dashed border-default bg-elevated/20 p-2"
        >
          <template #item="{ element }">
            <div class="ws-sub-user-chip" :class="setClass(element)">
              <button
                v-if="element?.protected && allowSingleBackendUsers"
                type="button"
                class="inline-flex items-center text-toned hover:text-primary"
                @click="setUserPin(element)"
              >
                <UTooltip text="Click to set/view user PIN">
                  <UIcon
                    :name="element?.options?.PLEX_USER_PIN ? 'i-lucide-lock' : 'i-lucide-lock-open'"
                    class="size-4"
                  />
                </UTooltip>
              </button>

              <UIcon
                v-else-if="element?.protected && !allowSingleBackendUsers"
                :name="element?.options?.PLEX_USER_PIN ? 'i-lucide-lock' : 'i-lucide-lock-open'"
                class="size-4 text-toned"
              />

              <span class="min-w-0">
                <span class="font-medium text-highlighted"
                  >{{ element.backend }}@{{ element.username }}</span
                >
                <span v-if="!isSameName(element.real_name, element.username)">
                  (<span class="underline">{{ element.real_name }}</span
                  >)
                </span>
              </span>
            </div>
          </template>
        </draggable>

        <UAlert
          v-if="unmatched?.length < 1"
          color="success"
          variant="soft"
          icon="i-lucide-circle-check"
          description="All users are associated."
          class="mt-4"
        />
      </template>
    </UCard>

    <UCard v-if="!isLoading" :ui="formCardUi">
      <template #header>
        <div class="text-base font-semibold text-highlighted">Execution options</div>
      </template>

      <div class="space-y-4">
        <div class="space-y-3">
          <div v-if="hasUsers" class="rounded-md border border-default bg-elevated/20 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Re-create local sub-users</div>
                <p class="mt-1 text-sm text-toned">
                  Delete current local sub-user data before creating the new set.
                </p>
              </div>

              <USwitch v-model="recreate" color="neutral" />
            </div>
          </div>

          <div class="rounded-md border border-default bg-elevated/20 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Generate remote backups</div>
                <p class="mt-1 text-sm text-toned">
                  Create an initial backup for each sub-user remote backend dataset.
                </p>
              </div>

              <USwitch v-model="backup" color="neutral" />
            </div>
          </div>

          <div class="rounded-md border border-default bg-elevated/20 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Skip mapper save</div>
                <p class="mt-1 text-sm text-toned">
                  Do not save the current mapping before running.
                </p>
              </div>

              <USwitch v-model="noSave" color="neutral" />
            </div>
          </div>

          <div class="rounded-md border border-default bg-elevated/20 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Verbose logs</div>
                <p class="mt-1 text-sm text-toned">Show more detailed output in the console run.</p>
              </div>

              <USwitch v-model="verbose" color="neutral" />
            </div>
          </div>

          <div class="rounded-md border border-default bg-elevated/20 px-3 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Dry run</div>
                <p class="mt-1 text-sm text-toned">Preview the operation without making changes.</p>
              </div>

              <USwitch v-model="dryRun" color="neutral" />
            </div>
          </div>

          <div
            v-if="1 === backendCount"
            class="rounded-md border border-default bg-elevated/20 px-3 py-3"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-medium text-highlighted">Allow single backend users</div>
                <p class="mt-1 text-sm text-toned">
                  Create sub-users from the single configured backend without requiring user
                  mapping.
                </p>
              </div>

              <USwitch v-model="allowSingleBackendUsers" color="neutral" />
            </div>
          </div>
        </div>

        <UAlert
          v-if="allowSingleBackendUsers && 1 === backendCount"
          color="success"
          variant="soft"
          icon="i-lucide-info"
          title="Single Backend Mode"
        >
          <template #description>
            <p class="text-sm text-default">
              You are in <strong>single backend mode</strong>. The system will create individual
              sub-users from your single configured backend without requiring user mapping. Each
              user will be set up independently.
            </p>
          </template>
        </UAlert>
      </div>

      <template #footer>
        <div class="flex gap-2 flex-row justify-end">
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            icon="i-lucide-save"
            :disabled="userWithNoPin.length > 0"
            @click="
              () => {
                void saveMap();
              }
            "
            label="Save mapping"
          />

          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            icon="i-lucide-users"
            :disabled="userWithNoPin.length > 0"
            @click="createUsers"
          >
            <span v-if="!dryRun">
              <span v-if="recreate || !hasUsers"
                >{{ recreate ? 'Re-create' : 'Create' }} sub-users</span
              >
              <span v-else>Update sub-users</span>
            </span>
            <span v-else>Test create sub-users<span v-if="hasUsers"> (Safe operation)</span></span>
          </UButton>
        </div>
      </template>
    </UCard>

    <UCard class="border border-default/70 shadow-sm" :ui="tipsCardUi">
      <template #header>
        <button
          type="button"
          class="flex w-full items-center justify-between gap-3 text-left"
          @click="show_page_tips = !show_page_tips"
        >
          <span class="inline-flex items-center gap-2 text-sm font-semibold text-highlighted">
            <UIcon name="i-lucide-info" class="size-4 text-toned" />
            <span>Information</span>
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
          This page lets you guide the system in matching sub-users across different backends.
        </li>
        <li>
          When you click <code>Create sub-users</code>, your mapping will be uploaded unless you’ve
          selected <code>Do not save mapper</code>. Based on your choice, the system will either
          delete and recreate the local sub-users, or try to update the existing ones.
        </li>
        <li class="font-semibold text-error">
          Warning: If you choose not to delete the existing local sub-users and the matching changes
          for any reason, you may end up with duplicate users. We strongly recommend deleting the
          current local sub-users.
        </li>
        <li>
          Clicking <code>Save mapping</code> will only save your current mapping to the system. It
          will <strong>not</strong> create any sub-users.
        </li>
        <li>
          Clicking the
          <UIcon name="i-lucide-file-output" class="inline size-4 align-text-bottom" /> icon will
          download the current mapping as a YAML file. You can review and manually upload it to the
          system later if needed.
        </li>
        <li>
          Users in the <b>Not matched</b> group aren’t currently linked to any others and likely
          won’t be matched automatically.
        </li>
        <li>Each user group must have at least two users to be considered a valid group.</li>
        <li>
          You can drag and drop users from the <b>Not matched</b> group into any other group to
          manually associate them.
        </li>
        <li>
          A user group can only include <b>one</b> user from <b>each</b> backend. If you try to add
          a second user from the same backend, an error will be shown.
        </li>
        <li>
          The display name format is: <code>backend_name@normalized_name (real_username)</code>. The
          <code>(real_username)</code> part only appears if it’s different from the
          <code>normalized_name</code>.
        </li>
        <li>
          There is a 5-minute cache when retrieving users from the API, so the data you see might be
          slightly out of date.
        </li>
        <li>
          Users backends with red border and icon of
          <UIcon name="i-lucide-lock-open" class="inline size-4 align-text-bottom" /> are protected
          by PIN, and you need to click on the icon to set the PIN. Otherwise, you will not be able
          to proceed.
        </li>
      </ul>
    </UCard>
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, toRaw } from 'vue';
import { useStorage } from '@vueuse/core';
import { navigateTo, useRoute } from '#app';
import moment from 'moment';
import draggable from 'vuedraggable';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { makeConsoleCommand, notification, parse_api_response, request } from '~/utils';
import { useDialog } from '~/composables/useDialog';
import type { GenericResponse } from '~/types';

const pageShell = requireTopLevelPageShell('sub-users');

type SubUserOptions = {
  PLEX_USER_PIN?: string;
};

type SubUserEntry = {
  id: string;
  backend: string;
  username: string;
  real_name: string;
  protected?: boolean;
  options?: SubUserOptions;
};

type SubUserMappingData = {
  version: string;
  map: Array<Record<string, { name: string; options: SubUserOptions }>>;
};

type SubUserGroup = {
  user: string;
  matched: Array<SubUserEntry>;
};

const matched = ref<Array<SubUserGroup>>([]);
const unmatched = ref<Array<SubUserEntry>>([]);
const isLoading = ref<boolean>(false);
const toastIsVisible = ref<boolean>(false);
const recreate = ref<boolean>(false);
const backup = ref<boolean>(false);
const noSave = ref<boolean>(false);
const dryRun = ref<boolean>(false);
const hasUsers = ref<boolean>(false);
const verbose = ref<boolean>(false);
const allowSingleBackendUsers = ref<boolean>(false);
const backendCount = ref<number>(0);
const expires = ref<string | undefined>();
const api_user = useStorage('api_user', 'main');
const show_page_tips = useStorage('show_page_tips', true);

type FilePickerOptions = {
  suggestedName?: string;
};

type FilePickerHandle = {
  createWritable: () => Promise<WritableStream>;
};

const groupCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const formCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
  footer: 'border-t border-default px-4 py-4',
};

const tipsCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const addNewUser = (): void => {
  const newUserName = `User group #${matched.value.length + 1}`;
  matched.value.push({ user: newUserName, matched: [] });
};

const loadContent = async (force?: boolean): Promise<void> => {
  if (matched.value.length > 0) {
    const { status } = await useDialog().confirmDialog({
      title: 'Reload data',
      message: 'Reloading will remove all modifications. Are you sure?',
      confirmColor: 'error',
    });

    if (true !== status) {
      return;
    }
  }

  matched.value = [];
  unmatched.value = [];
  isLoading.value = true;

  try {
    const response = await request(`/backends/mapper${force ? '?force=1' : ''}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
    });
    const json = await parse_api_response<{
      matched: Array<SubUserGroup>;
      unmatched: Array<SubUserEntry>;
      has_users: boolean;
      expires?: string;
      backends?: Array<string>;
    }>(response);

    if ('tools-sub_users' !== useRoute().name) {
      return;
    }

    if ('error' in json) {
      notification('error', 'Error', json.error.message || 'Unknown error');
      return;
    }

    matched.value = json.matched;
    unmatched.value = json.unmatched;
    recreate.value = json.has_users;
    backup.value = !json.has_users;
    hasUsers.value = json.has_users;
    backendCount.value = json.backends?.length || 0;
    expires.value = json?.expires;
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Unexpected error';
    notification('error', 'Error', message);
  } finally {
    isLoading.value = false;
  }
};

const generateFile = async (): Promise<void> => {
  const filename = 'mapper.yaml';
  const data = formatData();

  if (!data.map.length) {
    notification('error', 'Error', 'No data to export.');
    return;
  }

  const response = request(`/system/yaml/${filename}`, {
    method: 'POST',
    headers: { Accept: 'text/yaml' },
    body: JSON.stringify(data),
  });

  const pickerWindow = window as Window & {
    showSaveFilePicker?: (options: FilePickerOptions) => Promise<FilePickerHandle>;
  };
  const showSaveFilePicker = pickerWindow.showSaveFilePicker;

  if (showSaveFilePicker) {
    response.then(async (res) => {
      if (!res.body) {
        notification('error', 'Error', 'No data returned from export request.');
        return;
      }

      const handle = await showSaveFilePicker({
        suggestedName: `${filename}`,
      });
      await res.body.pipeTo(await handle.createWritable());
    });
  }

  response
    .then((res) => res.blob())
    .then((blob) => {
      const fileURL = URL.createObjectURL(blob);
      const fileLink = document.createElement('a');
      fileLink.href = fileURL;
      fileLink.download = `${filename}`;
      fileLink.click();
    });
};

interface DragEvent {
  draggedContext: {
    list: Array<SubUserEntry>;
    element: SubUserEntry;
  };
  relatedContext: {
    list: Array<SubUserEntry>;
  };
}

const checkBackend = (e: DragEvent): boolean => {
  if (e.draggedContext.list === e.relatedContext.list) {
    return true;
  }

  const isMatchedContainer = matched.value.some((group) => group.matched === e.relatedContext.list);

  if (false === isMatchedContainer) {
    return true;
  }

  const draggedUser = e.draggedContext.element;
  const alreadyExists = e.relatedContext.list.some((item) => item.backend === draggedUser.backend);

  if (true === alreadyExists) {
    if (!toastIsVisible.value) {
      toastIsVisible.value = true;
      nextTick(() => {
        notification(
          'error',
          'error',
          `A user from '${draggedUser.backend}' backend, already mapped in this group.`,
          3001,
          {
            onClose: () => (toastIsVisible.value = false),
          },
        );
      });
    }
    return false;
  }

  return true;
};

const deleteGroup = async (i: number) => {
  const group = matched.value[i];
  if (group && group.matched && group.matched.length) {
    const { status } = await useDialog().confirmDialog({
      title: 'Delete group',
      message: `Delete user group #${i + 1}?, Users will be moved to unmatched`,
      confirmColor: 'error',
    });

    if (true !== status) {
      return;
    }

    unmatched.value.push(...group.matched);
  }

  nextTick(() => matched.value.splice(i, 1));
};

const saveMap = async (no_toast: boolean = false): Promise<boolean> => {
  const data = formatData();

  if (!data.map.length) {
    if (!no_toast) {
      notification('error', 'Error', 'No mapping data to save.');
    }
    return true;
  }

  try {
    const req = await request('/backends/mapper', {
      method: 'PUT',
      body: JSON.stringify(data),
    });

    const response = await parse_api_response<GenericResponse>(req);
    if ('error' in response) {
      if (!no_toast) {
        notification('error', 'Error', `${req.status}: ${response.error.message}`);
      }
      return false;
    }

    if (200 <= req.status && 300 > req.status) {
      if (!no_toast) {
        notification('success', 'Success', response.info.message);
      }
      return true;
    }

    if (!no_toast) {
      notification('error', 'Error', `${req.status}: Request failed`);
    }

    return false;
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Unexpected error';
    notification('error', 'Error', `Error: ${message}`);
  }

  return false;
};

const formatData = (): SubUserMappingData => {
  const data: SubUserMappingData = { version: '1.6', map: [] };

  matched.value.forEach((group) => {
    const users: Record<string, { name: string; options: SubUserOptions }> = {};
    group?.matched.forEach((u) => {
      const options: SubUserOptions = u.options ? toRaw(u.options) : {};
      users[u.backend] = { name: u.username, options };
    });

    if (Object.keys(users).length < 2) {
      return;
    }

    data.map.push(users);
  });

  if (allowSingleBackendUsers.value) {
    unmatched.value.forEach((u) =>
      data.map.push({
        [u.backend]: { name: u.username, options: u.options ? toRaw(u.options) : {} },
      }),
    );
  }

  return toRaw(data);
};

const createUsers = async (): Promise<void> => {
  if (!noSave.value) {
    const state = await saveMap(true);
    if (false === state) {
      return;
    }
  }

  const command = ['backend:create'];

  command.push(verbose.value ? '-vvv' : '-vv');

  if (allowSingleBackendUsers.value) {
    command.push('--allow-single-backend-users');
    if (!recreate.value && hasUsers.value) {
      command.push('--run --update');
    } else if (recreate.value) {
      command.push('--re-create');
    } else {
      command.push('--run');
    }
  } else {
    command.push(recreate.value ? '--re-create' : '--run --update');
  }

  if (backup.value) {
    command.push('--generate-backup');
  }

  if (dryRun.value) {
    command.push('--dry-run');
  }

  await navigateTo(makeConsoleCommand(command.join(' '), true));
};

const isSameName = (name1: string, name2: string): boolean =>
  name1.toLowerCase() === name2.toLowerCase();

const setUserPin = async (user: SubUserEntry): Promise<void> => {
  const { status, value } = await useDialog().promptDialog({
    title: 'Set PIN',
    message: `Enter user PIN for '${user.backend}@${user.username}':`,
    initial: user?.options?.PLEX_USER_PIN || '',
  });

  if (true !== status) {
    return;
  }

  const pin = value;

  if ('' === pin) {
    if (user?.options?.PLEX_USER_PIN) {
      delete user.options.PLEX_USER_PIN;
    }
    return;
  }

  if (pin === user?.options?.PLEX_USER_PIN) {
    console.log('PIN is the same, no changes made.');
    return;
  }

  if (4 !== pin.length) {
    notification('error', 'Error', 'PIN must be at least 4 characters.');
    return;
  }

  if (!user?.options) {
    user.options = {};
  }

  user.options.PLEX_USER_PIN = pin;
};

const setClass = (user: SubUserEntry): string | undefined => {
  if (!user?.protected) {
    return;
  }

  return user?.options?.PLEX_USER_PIN ? 'is-success' : 'is-danger';
};

const userWithNoPin = computed<Array<string>>(() => {
  const no_pin: Array<string> = [];

  matched.value.forEach((group) =>
    group.matched.forEach((user) => {
      if (!user?.protected) {
        return;
      }

      if (!user?.options?.PLEX_USER_PIN) {
        no_pin.push(`${user.backend}@${user.username}`);
      }
    }),
  );

  if (!allowSingleBackendUsers.value) {
    return no_pin;
  }

  unmatched.value.forEach((user) => {
    if (!user?.protected) {
      return;
    }

    if (!user?.options?.PLEX_USER_PIN) {
      no_pin.push(`${user.backend}@${user.username}`);
    }
  });

  return no_pin;
});

onMounted(async (): Promise<void> => {
  if ('main' !== api_user.value) {
    notification('error', 'Error', 'The sub users page is only available for the main user.');
    await navigateTo({ name: 'backends' });
    return;
  }
  await loadContent();
});
</script>

<style scoped>
.ws-sub-user-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.625rem;
  max-width: 100%;
  background: color-mix(in srgb, var(--ui-bg-elevated) 88%, transparent);
  cursor: move;
  border: 1px solid var(--ui-border);
  border-radius: 0.375rem;
  color: var(--ui-text-highlighted);
  overflow-wrap: anywhere;
}

.ws-sub-user-chip.is-danger {
  border-color: color-mix(in srgb, var(--ui-color-error-500) 55%, transparent);
}

.ws-sub-user-chip.is-success {
  border-color: color-mix(in srgb, var(--ui-color-success-500) 55%, transparent);
}

.ws-sub-user-chip-placeholder {
  cursor: default;
  border-style: dashed;
  background: transparent;
}
</style>
