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

      <div>
        <p class="mt-1 text-sm text-toned">
          Validate whether a Plex token can communicate with the <code>plex.tv</code> API.
        </p>
      </div>
    </div>

    <UCard class="border border-default/70 bg-default/90 shadow-sm" :ui="cardUi">
      <template #header>
        <div class="inline-flex items-center gap-2 text-base font-semibold text-highlighted">
          <UIcon name="i-lucide-key-round" class="size-4 shrink-0 text-toned" />
          <span>X-Plex-Token</span>
        </div>
      </template>

      <form class="space-y-4" @submit.prevent="validateToken">
        <UAlert
          v-if="success"
          color="success"
          variant="soft"
          icon="i-lucide-circle-check"
          title="Validation succeeded"
          :description="success"
          close
          @update:open="success = ''"
        />

        <UAlert
          v-if="error"
          color="error"
          variant="soft"
          icon="i-lucide-triangle-alert"
          title="Validation failed"
          :description="error"
          close
          @update:open="error = ''"
        />

        <UFormField label="" name="plex-token" required>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <UInput
              v-model="token"
              required
              :type="false === exposeToken ? 'password' : 'text'"
              placeholder="X-Plex-Token"
              icon="i-lucide-key-round"
              class="w-full"
              :disabled="isLoading"
            />

            <UTooltip text="Toggle token visibility">
              <UButton
                type="button"
                color="neutral"
                variant="outline"
                size="sm"
                :icon="exposeToken ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                :aria-label="exposeToken ? 'Hide token' : 'Show token'"
                class="whitespace-nowrap"
                :disabled="isLoading"
                @click="exposeToken = !exposeToken"
              />
            </UTooltip>
          </div>

          <p class="mt-2 text-sm text-toned">
            Need a token?
            <NuxtLink
              target="_blank"
              to="https://support.plex.tv/articles/204059436-finding-an-authentication-token-x-plex-token/"
              class="text-primary"
            >
              Read the Plex article
            </NuxtLink>
            .
          </p>
        </UFormField>
      </form>

      <template #footer>
        <div class="flex flex-wrap items-center justify-end gap-2">
          <UButton
            color="primary"
            variant="solid"
            size="sm"
            icon="i-lucide-circle-check"
            :loading="isLoading"
            :disabled="!token || isLoading"
            @click="validateToken"
          >
            {{ isLoading ? 'Validating...' : 'Validate Token' }}
          </UButton>
        </div>
      </template>
    </UCard>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useHead } from '#app';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { parse_api_response, request } from '~/utils';
import type { GenericResponse } from '~/types';

useHead({ title: 'Validate Plex Token' });

const pageShell = requireTopLevelPageShell('plex-token');

const isLoading = ref<boolean>(false);
const token = ref<string>('');
const error = ref<string>('');
const success = ref<string>('');
const exposeToken = ref<boolean>(false);

const cardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
  footer: 'px-4 pb-4 pt-0',
};

const validateToken = async (): Promise<void> => {
  error.value = '';
  success.value = '';

  if (!token.value) {
    error.value = 'Please enter a valid token.';
    return;
  }

  try {
    isLoading.value = true;

    const response = await request('/backends/validate/token/plex', {
      method: 'POST',
      body: JSON.stringify({ token: token.value }),
    });

    const resp = await parse_api_response<GenericResponse>(response);

    if ('error' in resp) {
      error.value = resp.error.message;
      return;
    }

    success.value = resp.info.message;
  } catch (caughtError: unknown) {
    const message = caughtError instanceof Error ? caughtError.message : 'Unexpected error';
    error.value = `An error occurred while validating the token. ${message}`;
  } finally {
    isLoading.value = false;
  }
};
</script>
