<template>
  <UAlert color="info" variant="soft" icon="i-lucide-info" title="Update available">
    <template #description>
      <span>
        A new WebUI version is installed.
        <button
          type="button"
          class="font-semibold text-primary underline underline-offset-2"
          @click="reloadPage"
        >
          Reload now
        </button>
        to use the latest assets.
      </span>
    </template>
  </UAlert>
</template>

<script setup lang="ts">
const reloadPage = () => window.location.reload();
</script>
