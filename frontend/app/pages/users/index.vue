<template>
  <div class="space-y-6">
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
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-plus"
          :disabled="isLoading"
          @click="openAddUserForm"
        >
          Add User
        </UButton>

        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-refresh-cw"
          :loading="isLoading"
          :disabled="isLoading"
          @click="loadContent"
        >
          <span class="hidden sm:inline">Reload</span>
        </UButton>
      </div>
    </div>

    <UModal
      :open="toggleForm"
      title="Add User"
      :ui="addUserModalUi"
      @update:open="handleAddUserOpenChange"
    >
      <template #body>
        <form v-if="toggleForm" class="space-y-4" @submit.prevent="addUser">
          <UAlert
            v-if="formError"
            color="error"
            variant="soft"
            icon="i-lucide-triangle-alert"
            title="Error"
            :close="{
              onClick: () => {
                formError = null;
              },
            }"
            :description="formError"
          />

          <UFormField
            label="Username"
            name="username"
            description="Username must be unique and only contain lowercase letters (a-z), numbers (0-9), and underscores (_)."
          >
            <UInput
              v-model="newUsername"
              type="text"
              required
              icon="i-lucide-user"
              class="w-full"
              placeholder="Enter username (lowercase a-z, 0-9, _)"
            />
          </UFormField>

          <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <UButton
              type="button"
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-lucide-x"
              @click="() => void cancelAddUser()"
            >
              Cancel
            </UButton>

            <UButton
              type="submit"
              color="primary"
              variant="solid"
              size="sm"
              icon="i-lucide-circle-check"
              :loading="isAdding"
              :disabled="isAdding"
            >
              Add User
            </UButton>
          </div>
        </form>
      </template>
    </UModal>

    <UModal
      :open="editUserOpen"
      :title="editUserId ? `Edit User: ${ucFirst(editUserId)}` : 'Edit User'"
      :ui="editUserModalUi"
      @update:open="handleEditUserOpenChange"
    >
      <template #body>
        <UserEditForm
          v-if="editUserOpen && editUserId"
          :user-id="editUserId"
          @close="() => void requestCloseEditUser()"
          @saved="() => void handleUserEdited()"
          @dirty-change="(dirty) => (editUserDirty = dirty)"
        />
      </template>
    </UModal>

    <UAlert
      v-if="users.length < 1 && isLoading"
      color="info"
      variant="soft"
      icon="i-lucide-loader-circle"
      title="Loading"
      description="Loading users. Please wait..."
      :ui="{ icon: 'animate-spin' }"
    />

    <UAlert
      v-else-if="users.length < 1"
      color="warning"
      variant="soft"
      icon="i-lucide-info"
      title="No Users Found"
    >
      <template #description>
        <div class="flex flex-wrap items-center gap-2 text-sm text-default">
          <span>No users found.</span>
          <UButton color="primary" variant="link" size="sm" class="px-0" @click="openAddUserForm">
            Add a new user
          </UButton>
        </div>
      </template>
    </UAlert>

    <div v-else class="grid gap-4 xl:grid-cols-2">
      <UCard
        v-for="user in users"
        :key="user.user"
        class="h-full border border-default/70 shadow-sm"
        :ui="userCardUi"
      >
        <template #header>
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex items-center gap-2">
              <UIcon name="i-lucide-user" class="size-4 shrink-0 text-toned" />
              <h2 class="truncate text-base font-semibold">
                {{ ucFirst(user.user) }}
              </h2>
            </div>

            <div class="flex items-center gap-2">
              <UButton
                color="neutral"
                variant="outline"
                size="sm"
                icon="i-lucide-settings"
                @click="openEditUser(user.user)"
              >
                <span class="hidden sm:inline">Edit</span>
              </UButton>

              <UButton
                v-if="user.user !== 'main'"
                color="neutral"
                variant="outline"
                size="sm"
                icon="i-lucide-trash-2"
                :to="`/users/${user.user}/delete?redirect=/users`"
              >
                <span class="hidden sm:inline">Delete</span>
              </UButton>
            </div>
          </div>
        </template>

        <div class="space-y-3 text-sm text-default">
          <div v-if="user.backends.length > 0" class="flex flex-wrap gap-2">
            <button
              v-for="backend in user.backends"
              :key="backend"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium text-primary hover:bg-primary/15"
              @click="user_link(user.user, `/backend/${backend}`)"
            >
              <UIcon name="i-lucide-server" class="size-3.5 shrink-0 text-toned" />
              {{ backend }}
            </button>
          </div>

          <UBadge v-else color="warning" variant="soft">No backends configured</UBadge>
        </div>
      </UCard>
    </div>

    <UCard v-if="users.length > 0" :ui="tipsCardUi">
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
        <li>The <strong>main</strong> user is the primary user and cannot be deleted.</li>
        <li>Each user can have their own set of backends configured independently.</li>
        <li>
          Server configurations are validated against the system specification before saving. While
          this may help prevent misconfigurations, it's recommended to double-check configurations
          manually. The validation is not foolproof and may miss certain issues.
        </li>
      </ul>
    </UCard>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { navigateTo, useHead, useRoute } from '#app';
import { useStorage } from '@vueuse/core';
import UserEditForm from '~/components/UserEditForm.vue';
import { useDirtyCloseGuard } from '~/composables/useDirtyCloseGuard';
import { useDirtyState } from '~/composables/useDirtyState';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { notification, parse_api_response, request, ucFirst } from '~/utils';
import type { GenericResponse, UserListItem } from '~/types';

useHead({ title: 'Users Management' });

const pageShell = requireTopLevelPageShell('users');

const route = useRoute();
const users = ref<Array<UserListItem>>([]);
const toggleForm = ref<boolean>(false);
const editUserOpen = ref<boolean>(false);
const editUserId = ref<string>('');
const editUserDirty = ref<boolean>(false);
const isLoading = ref<boolean>(false);
const isAdding = ref<boolean>(false);
const newUsername = ref<string>('');
const formError = ref<string | null>(null);
const show_page_tips = useStorage('show_page_tips', true);
const addUserDirtySource = computed(() => ({
  username: newUsername.value.trim().toLowerCase(),
}));
const { isDirty: isAddUserDirty, markClean: markAddUserClean } = useDirtyState(addUserDirtySource);

const addUserModalUi = {
  content: 'max-w-xl',
  body: 'p-4 sm:p-5',
};

const editUserModalUi = {
  content: 'max-w-6xl',
  body: 'p-4 sm:p-5',
};

const userCardUi = {
  header: 'p-4',
  body: 'p-4',
};

const tipsCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const resetAddUserForm = (): void => {
  newUsername.value = '';
  formError.value = null;
  markAddUserClean();
};

const { handleOpenChange: handleAddUserOpenChange, requestClose: requestCloseAddUser } =
  useDirtyCloseGuard(toggleForm, {
    dirty: isAddUserDirty,
    onDiscard: async () => {
      resetAddUserForm();
    },
  });

const { handleOpenChange: handleEditUserOpenChange, requestClose: requestCloseEditUser } =
  useDirtyCloseGuard(editUserOpen, {
    dirty: editUserDirty,
    onDiscard: async () => {
      editUserDirty.value = false;
      editUserId.value = '';
    },
  });

const openAddUserForm = (): void => {
  resetAddUserForm();
  toggleForm.value = true;
};

const openEditUser = (userId: string): void => {
  editUserDirty.value = false;
  editUserId.value = userId;
  editUserOpen.value = true;
};

const loadContent = async (): Promise<void> => {
  users.value = [];
  isLoading.value = true;

  try {
    const response = await request('/users');
    const json = await parse_api_response<{ users: Array<UserListItem> }>(response);

    if ('users' !== route.name) {
      return;
    }

    if ('error' in json) {
      notification('error', 'Error', `Failed to load users. ${json.error.message}`);
      return;
    }

    users.value = json.users || [];
    useHead({ title: 'Users Management' });
  } catch (e: unknown) {
    const error = e as Error;
    notification('error', 'Error', `Failed to load users. ${error.message}`);
  } finally {
    isLoading.value = false;
  }
};

const addUser = async (): Promise<void> => {
  if (true === isAdding.value) {
    return;
  }

  formError.value = null;
  const username = newUsername.value.trim().toLowerCase();

  if (0 === username.length) {
    formError.value = 'Please enter a username';
    return;
  }

  isAdding.value = true;

  try {
    const response = await request('/users', {
      method: 'POST',
      body: JSON.stringify({ user: username }),
    });
    const result = await parse_api_response<GenericResponse>(response);

    if ('error' in result) {
      formError.value = result.error?.message || 'Failed to create user';
      return;
    }

    notification('success', 'Success', `User '${username}' created successfully`);
    resetAddUserForm();
    toggleForm.value = false;
    await loadContent();
  } catch (e: unknown) {
    const error = e as Error;
    formError.value = `Failed to create user. ${error.message}`;
  } finally {
    isAdding.value = false;
  }
};

const cancelAddUser = async (): Promise<void> => {
  await requestCloseAddUser();
};

const handleUserEdited = async (): Promise<void> => {
  editUserDirty.value = false;
  editUserOpen.value = false;
  editUserId.value = '';
  await loadContent();
};

const user_link = async (user: string, url: string): Promise<void> => {
  const api_user = useStorage('api_user', 'main');
  api_user.value = user || 'main';
  await nextTick();
  await navigateTo(url);
};

markAddUserClean();

onMounted(() => loadContent());
</script>
