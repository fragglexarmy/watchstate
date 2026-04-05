<template>
  <NuxtLayout>
    <main class="w-full min-w-0 max-w-full space-y-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2 text-lg font-semibold text-highlighted">
          <UIcon name="i-lucide-triangle-alert" class="size-5 text-error" />
          <span>
            {{ props.error.statusCode }}
            <span v-if="props.error.statusMessage">- {{ props.error.statusMessage }}</span>
          </span>
        </div>

        <p class="text-sm text-toned">An unexpected application error occurred.</p>
      </div>

      <UAlert
        v-if="props.error.message"
        color="warning"
        variant="soft"
        icon="i-lucide-triangle-alert"
        title="Error details"
        :description="props.error.message"
      />

      <UCard v-if="props.error.stack" class="border border-default/70 shadow-sm" :ui="cardUi">
        <template #header>
          <button
            type="button"
            class="flex items-center gap-2 text-left text-sm font-semibold text-highlighted"
            @click="showStacks = !showStacks"
          >
            <UIcon
              :name="showStacks ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
              class="size-4 text-toned"
            />
            <span>Stack trace</span>
          </button>
        </template>

        <div
          v-if="showStacks"
          class="overflow-x-auto rounded-md border border-default bg-elevated/60 p-3"
        >
          <pre
            class="whitespace-pre-wrap wrap-break-word text-xs leading-6 text-default"
          ><code>{{ props.error.stack }}</code></pre>
        </div>
      </UCard>

      <div class="flex justify-end">
        <UButton color="primary" variant="soft" icon="i-lucide-house" to="/">Back to Home</UButton>
      </div>
    </main>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
  error: {
    statusCode?: number;
    statusMessage?: string;
    message?: string;
    stack?: string;
  };
}>();

const cardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const showStacks = ref(false);
</script>
