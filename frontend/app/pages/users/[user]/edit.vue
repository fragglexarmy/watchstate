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
        <span class="text-highlighted normal-case tracking-normal">Edit</span>
      </div>

      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-highlighted">Edit user configuration</h1>
        <p class="text-sm text-toned">Edit user backends configuration.</p>
      </div>
    </div>

    <UserEditForm
      :user-id="id"
      @close="() => void closeEditor()"
      @saved="() => void closeEditor()"
    />
  </div>
</template>

<script setup lang="ts">
import { navigateTo, useRoute } from '#app';
import UserEditForm from '~/components/UserEditForm.vue';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';

const route = useRoute();
const id = route.params.user as string;
const pageShell = requireTopLevelPageShell('users');

const closeEditor = async (): Promise<void> => {
  await navigateTo((route.query.redirect as string) || '/users');
};
</script>
