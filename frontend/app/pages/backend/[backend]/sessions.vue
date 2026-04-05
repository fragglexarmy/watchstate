<template>
  <div class="space-y-6">
    <section class="space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-1">
          <div
            class="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
          >
            <UIcon :name="pageShell.icon" class="size-4" />
            <span>{{ pageShell.sectionLabel }}</span>
            <span>/</span>
            <NuxtLink to="/backends" class="hover:text-primary">{{ pageShell.pageLabel }}</NuxtLink>
            <span>/</span>
            <NuxtLink
              :to="`/backend/${backend}`"
              class="hover:text-primary normal-case tracking-normal"
              >{{ backend }}</NuxtLink
            >
            <span>/</span>
            <span class="text-highlighted normal-case tracking-normal">Sessions</span>
          </div>
        </div>

        <UTooltip text="Reload sessions">
          <UButton
            color="neutral"
            variant="outline"
            icon="i-lucide-refresh-cw"
            :loading="isLoading"
            :disabled="isLoading"
            aria-label="Reload sessions"
            @click="loadContent"
          >
            <span class="hidden sm:inline">Reload</span>
          </UButton>
        </UTooltip>
      </div>

      <UAlert
        v-if="1 > items.length && isLoading"
        color="info"
        variant="soft"
        icon="i-lucide-loader-circle"
        title="Loading"
        description="Requesting active play sessions. Please wait..."
        :ui="{ icon: 'animate-spin' }"
      />

      <UAlert
        v-else-if="1 > items.length"
        color="success"
        variant="soft"
        icon="i-lucide-info"
        title="Information"
        description="There are no active play sessions currently running."
      />

      <UCard v-else class="border border-default/70 shadow-sm" :ui="cardUi">
        <template #header>
          <div class="flex items-center gap-2">
            <UIcon name="i-lucide-play-circle" class="size-5 text-toned" />
            <span class="font-semibold text-highlighted">Active Sessions</span>
          </div>
        </template>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-default text-sm text-default">
            <thead>
              <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-toned">
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">Title</th>
                <th class="px-4 py-3">State</th>
                <th class="px-4 py-3">Progress at</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-default">
              <tr v-for="item in items" :key="item.id" class="bg-default/60">
                <td class="px-4 py-3">{{ item.user_name }}</td>
                <td class="px-4 py-3">
                  <NuxtLink :to="makeItemLink(item)" class="text-primary hover:underline">
                    {{ item.item_title }}
                  </NuxtLink>
                </td>
                <td class="px-4 py-3">{{ item.session_state }}</td>
                <td class="px-4 py-3">{{ formatDuration(item.item_offset_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </UCard>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from '#app';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import { formatDuration, notification, parse_api_response, request } from '~/utils';
import type { SessionItem } from '~/types';

const backend = useRoute().params.backend as string;
const pageShell = requireTopLevelPageShell('backends');
const items = ref<Array<SessionItem>>([]);
const isLoading = ref<boolean>(false);

const cardUi = {
  header: 'p-5',
  body: 'p-0',
};

const loadContent = async (): Promise<void> => {
  try {
    isLoading.value = true;
    items.value = [];

    const response = await request(`/backend/${backend}/sessions`);
    const data = await parse_api_response<Array<SessionItem>>(response);

    if ('error' in data) {
      notification('error', 'Error', `${data.error.code}: ${data.error.message}`);
      return;
    }

    items.value = data;
  } catch (e) {
    return notification(
      'error',
      'Error',
      e instanceof Error ? e.message : 'Unknown error occurred',
    );
  } finally {
    isLoading.value = false;
  }
};

const makeItemLink = (item: SessionItem): string => {
  const params = new URLSearchParams();
  params.append('perpage', '50');
  params.append('page', '1');
  params.append('q', `${backend}.id://${item.item_id}`);
  params.append('key', 'metadata');

  return `/history?${params.toString()}`;
};

onMounted(async () => await loadContent());
</script>
