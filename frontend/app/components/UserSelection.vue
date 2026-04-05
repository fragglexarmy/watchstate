<template>
  <div class="space-y-4">
    <UFormField label="Browse as" name="user">
      <USelect
        id="user"
        v-model="api_user"
        :items="users"
        icon="i-lucide-user"
        class="w-full"
        :disabled="isLoading"
      />
    </UFormField>

    <UAlert
      color="warning"
      variant="soft"
      icon="i-lucide-triangle-alert"
      description="Browse the WebUI as the selected user. Not all API endpoints support non-main user."
    />

    <div class="flex flex-col justify-end gap-2 sm:flex-row">
      <UButton
        type="button"
        color="primary"
        variant="solid"
        icon="i-lucide-refresh-cw"
        :disabled="!api_user || isLoading"
        @click="reloadPage"
      >
        Reload
      </UButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { navigateTo, useRoute } from '#app';
import { useStorage } from '@vueuse/core';
import { notification, request } from '~/utils';

const api_user = useStorage<string>('api_user', 'main');
const users = ref<Array<string>>(['main']);
const isLoading = ref<boolean>(true);

const emitter = defineEmits<{
  (e: 'close'): void;
}>();

onMounted(async (): Promise<void> => {
  try {
    const response = await request('/system/users');
    if (!response.ok) {
      notification('error', 'Error', 'Failed to fetch users.');
      users.value = [api_user.value];
      return;
    }
    const json = await response.json();
    if ('users' in json) {
      (json.users as Array<{ user: string }>).forEach((user) => {
        const username = user.user;
        if (!users.value.includes(username)) {
          users.value.push(username);
        }
      });
    }
  } catch (e) {
    notification('error', 'Error', `Failed to fetch users. ${e}`);
  } finally {
    isLoading.value = false;
  }
});

const reloadPage = async () => {
  await emitter('close');
  if ('/' === useRoute().path) {
    window.location.reload();
    return;
  }
  await navigateTo('/');
};
</script>
