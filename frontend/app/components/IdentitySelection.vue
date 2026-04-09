<template>
  <div class="space-y-4">
    <UFormField label="Browse as" name="identity">
      <USelect
        id="identity"
        v-model="api_user"
        :items="identities"
        icon="i-lucide-user"
        class="w-full"
        :disabled="isLoading"
      />
    </UFormField>

    <UAlert
      color="warning"
      variant="soft"
      icon="i-lucide-triangle-alert"
      description="Browse the WebUI as the selected identity. Not all API endpoints support non-main identity."
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
import { notification, parse_api_response, request } from '~/utils';
import type { IdentityListItem } from '~/types';

const api_user = useStorage<string>('api_user', 'main');
const identities = ref<Array<string>>(['main']);
const isLoading = ref<boolean>(true);

const emitter = defineEmits<{
  (e: 'close'): void;
}>();

onMounted(async (): Promise<void> => {
  try {
    const response = await request('/identities');
    const json = await parse_api_response<{ identities: Array<IdentityListItem> }>(response);

    if ('error' in json) {
      notification('error', 'Error', `Failed to fetch identities. ${json.error.message}`);
      identities.value = [api_user.value];
      return;
    }

    json.identities.forEach((identity) => {
      const name = identity.identity;
      if (!identities.value.includes(name)) {
        identities.value.push(name);
      }
    });
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Unexpected error';
    notification('error', 'Error', `Failed to fetch identities. ${message}`);
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
