<template>
  <div class="space-y-6">
    <div class="space-y-2">
      <div
        class="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
      >
        <UIcon :name="pageShell.icon" class="size-4" />
        <span>{{ pageShell.sectionLabel }}</span>
        <span>/</span>
        <NuxtLink to="/users" class="hover:text-primary">{{ pageShell.pageLabel }}</NuxtLink>
        <span>/</span>
        <span class="normal-case tracking-normal">{{ id }}</span>
        <span>/</span>
        <span class="text-highlighted normal-case tracking-normal">Delete</span>
      </div>

      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-highlighted">Delete user</h1>
        <p class="text-sm text-toned">Delete user and all their backend configurations.</p>
      </div>
    </div>

    <UAlert
      v-if="isDeleting"
      color="warning"
      variant="soft"
      icon="i-lucide-loader-circle"
      title="Deleting..."
      description="Delete operation is in progress. Please wait..."
      :ui="{ icon: 'animate-spin' }"
    />

    <UAlert
      v-else-if="isLoading"
      color="info"
      variant="soft"
      icon="i-lucide-loader-circle"
      title="Loading"
      description="Loading data. Please wait..."
      :ui="{ icon: 'animate-spin' }"
    />

    <template v-else>
      <UAlert
        v-if="error"
        color="error"
        variant="soft"
        icon="i-lucide-triangle-alert"
        title="Error"
        :description="`${error.error.code}: ${error.error.message}`"
        :close="{ onClick: () => void navigateTo('/users') }"
      />

      <UAlert
        v-else-if="id === 'main'"
        color="error"
        variant="soft"
        icon="i-lucide-triangle-alert"
        title="Action is not permitted"
        :close="{ onClick: () => void navigateTo('/users') }"
      >
        <template #description>
          <p class="text-sm text-default">
            The <strong>main</strong> user cannot be deleted as it is the primary user.
          </p>
        </template>
      </UAlert>

      <template v-else>
        <UAlert
          color="warning"
          variant="soft"
          icon="i-lucide-triangle-alert"
          title="Confirmation is required"
        >
          <template #description>
            <div class="space-y-3 text-sm text-default">
              <p>
                Are you sure you want to delete the user <code>{{ id }}</code> and all their backend
                configurations?
              </p>

              <div>
                <div class="mb-2 font-medium text-highlighted">
                  This operation will do the following
                </div>
                <ul class="list-disc space-y-1 pl-5">
                  <li>Remove all user data.</li>
                  <li v-if="backends.length > 0">
                    Delete <strong>{{ backends.length }}</strong> backend{{
                      backends.length > 1 ? 's' : ''
                    }}:
                    <span v-for="(backend, index) in backends" :key="backend">
                      <code>{{ backend }}</code
                      ><template v-if="index < backends.length - 1">, </template>
                    </span>
                  </li>
                </ul>
              </div>

              <p class="font-semibold text-error underline">
                There is no undo operation. This action is irreversible.
              </p>
            </div>
          </template>
        </UAlert>

        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
          <UButton
            color="neutral"
            variant="soft"
            size="sm"
            icon="i-lucide-arrow-left"
            @click="navigateTo('/users')"
          >
            Back
          </UButton>

          <UButton
            color="error"
            variant="solid"
            size="sm"
            icon="i-lucide-trash-2"
            :loading="isDeleting"
            :disabled="isDeleting"
            @click="deleteUser()"
          >
            Delete user
          </UButton>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, nextTick, ref } from 'vue';
import { navigateTo, useRoute } from '#app';
import { useStorage } from '@vueuse/core';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { notification, parse_api_response, request } from '~/utils';
import { useDialog } from '~/composables/useDialog';
import type { GenericError, UserListItem } from '~/types';

const id = useRoute().params.user as string;
const pageShell = requireTopLevelPageShell('users');
const error = ref<GenericError | null>(null);
const backends = ref<Array<string>>([]);
const isLoading = ref<boolean>(false);
const isDeleting = ref<boolean>(false);

const loadUser = async (): Promise<void> => {
  try {
    isLoading.value = true;

    const response = await request('/users');
    const data = await parse_api_response<{ users: Array<UserListItem> }>(response);

    if ('error' in data) {
      error.value = data;
      return;
    }

    const user = data.users.find((u) => u.user === id);
    if (user) {
      backends.value = user.backends;
    } else {
      error.value = {
        error: { code: 404, message: 'User not found' },
      } as GenericError;
    }
  } catch (e: unknown) {
    error.value = {
      error: { code: 500, message: e instanceof Error ? e.message : 'Unknown error occurred' },
    } as GenericError;
  } finally {
    isLoading.value = false;
  }
};

const deleteUser = async (): Promise<void> => {
  const { status: confirmStatus } = await useDialog().confirmDialog({
    title: 'Last Chance!',
    message: `This action is irreversible. Delete '${id}' data?`,
    confirmColor: 'error',
  });

  if (true !== confirmStatus) {
    return;
  }

  try {
    isDeleting.value = true;

    const response = await request(`/users/${id}`, { method: 'DELETE' });

    if (200 !== response.status) {
      error.value = await parse_api_response(response);
      return;
    }

    notification('success', 'Success', `User '${id}' has been deleted successfully`);

    const api_user = useStorage('api_user', 'main');
    if (api_user.value === id) {
      api_user.value = 'main';
      await nextTick();
    }

    await navigateTo('/users');
  } catch (e: unknown) {
    error.value = {
      error: { code: 500, message: e instanceof Error ? e.message : 'Unknown error occurred' },
    } as GenericError;
  } finally {
    isDeleting.value = false;
  }
};

onMounted(async (): Promise<void> => await loadUser());
</script>
