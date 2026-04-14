<template>
  <UModal :open="props.open" :title="props.title" :ui="modalUi" @update:open="handleOpenChange">
    <template #body>
      <div class="space-y-3">
        <UInput
          v-model="query"
          type="search"
          placeholder="Filter text"
          icon="i-lucide-filter"
          size="sm"
          class="w-full"
        />

        <UAlert
          v-if="query && 0 === filteredLineCount"
          color="warning"
          variant="soft"
          icon="i-lucide-filter"
          title="No matching lines"
        >
          <template #description>
            <p class="text-sm text-default">
              No lines match this filter: <u>{{ query }}</u>
            </p>
          </template>
        </UAlert>

        <div class="overflow-hidden rounded-md border border-default bg-elevated/60">
          <code
            class="ws-terminal ws-terminal-panel ws-terminal-panel-lg whitespace-pre-wrap text-sm"
            >{{ displayedText }}</code
          >
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex w-full flex-wrap items-center justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-copy"
          @click="copyCurrentText"
        >
          {{ props.copyLabel }}
        </UButton>

        <UButton color="neutral" variant="outline" size="sm" icon="i-lucide-x" @click="close">
          {{ props.closeLabel }}
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { copyText } from '~/utils';

const props = withDefaults(
  defineProps<{
    open: boolean;
    title?: string;
    text: string | number;
    copyLabel?: string;
    closeLabel?: string;
  }>(),
  {
    title: 'Text',
    copyLabel: 'Copy',
    closeLabel: 'Close',
  },
);

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

const modalUi = {
  content: 'max-w-5xl',
  body: 'p-4 sm:p-5',
  footer: 'border-t border-default/70 px-4 py-4 sm:px-5',
};

const renderedText = computed<string>(() => String(props.text));
const query = ref('');

const filteredLines = computed<Array<string>>(() => {
  if (!query.value) {
    return renderedText.value.split('\n');
  }

  const needle = query.value.toLowerCase();

  return renderedText.value.split('\n').filter((line) => line.toLowerCase().includes(needle));
});

const filteredLineCount = computed<number>(() => filteredLines.value.length);
const displayedText = computed<string>(() =>
  query.value ? filteredLines.value.join('\n') : renderedText.value,
);

watch(
  () => props.open,
  (open: boolean) => {
    if (open) {
      query.value = '';
    }
  },
);

const handleOpenChange = (open: boolean): void => {
  emit('update:open', open);
};

const copyCurrentText = (): void => {
  copyText(displayedText.value);
};

const close = (): void => {
  emit('update:open', false);
};
</script>
