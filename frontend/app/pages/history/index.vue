<template>
  <main class="w-full min-w-0 max-w-full space-y-4">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
      <div class="min-w-0 space-y-1">
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
        <UInput
          v-if="showFilter"
          id="filter"
          v-model="filter"
          type="search"
          placeholder="Filter displayed results"
          icon="i-lucide-filter"
          size="sm"
          class="w-full sm:w-72"
        />

        <UButton
          color="neutral"
          :variant="showFilter ? 'soft' : 'outline'"
          size="sm"
          icon="i-lucide-filter"
          @click="toggleFilter"
        >
          <span class="hidden sm:inline">Filter</span>
        </UButton>

        <USelect
          v-model="perpage"
          :items="perPageItems"
          value-key="value"
          label-key="label"
          color="neutral"
          variant="outline"
          size="sm"
          class="w-40"
          :disabled="isLoading"
          @update:model-value="() => void loadContent(1, false)"
        />

        <UButton
          color="neutral"
          :variant="searchForm ? 'soft' : 'outline'"
          size="sm"
          icon="i-lucide-search"
          @click="searchForm = !searchForm"
        >
          <span class="hidden sm:inline">Search</span>
        </UButton>

        <UButton
          color="neutral"
          :variant="selectAll ? 'soft' : 'outline'"
          size="sm"
          :icon="selectAll ? 'i-lucide-square' : 'i-lucide-square-check-big'"
          @click="selectAll = !selectAll"
        >
          <span class="hidden sm:inline">{{ selectAll ? 'Unselect' : 'Select' }}</span>
        </UButton>

        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-refresh-cw"
          :loading="isLoading"
          :disabled="isLoading"
          @click="() => void loadContent(page, true)"
        >
          <span class="hidden sm:inline">Reload</span>
        </UButton>
      </div>
    </div>

    <UCard v-if="searchForm" class="border border-default/70 shadow-sm" :ui="panelCardUi">
      <template #header>
        <div class="flex items-center gap-2 text-sm font-semibold text-highlighted">
          <UIcon name="i-lucide-search" class="size-4 text-toned" />
          <span>Search History</span>
        </div>
      </template>

      <form class="space-y-3" @submit.prevent="void loadContent(1)">
        <div class="grid gap-3 lg:grid-cols-[14rem_minmax(0,1fr)_auto_auto] lg:items-start">
          <USelect
            v-model="searchField"
            :items="searchFieldItems"
            value-key="value"
            label-key="label"
            color="neutral"
            variant="outline"
            size="sm"
            placeholder="Select field"
            icon="i-lucide-folder-tree"
          />

          <UInput
            v-model="query"
            type="search"
            placeholder="Search..."
            icon="i-lucide-search"
            size="sm"
            :disabled="'' === searchField || isLoading"
          />

          <UButton
            color="primary"
            size="sm"
            icon="i-lucide-search"
            type="submit"
            :disabled="!query || '' === searchField || isLoading"
            :loading="isLoading"
          >
            Search
          </UButton>

          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            icon="i-lucide-x"
            type="button"
            :disabled="isLoading"
            @click="clearSearch"
          >
            Reset
          </UButton>
        </div>

        <p v-if="searchHelpText" class="text-sm text-toned">
          {{ searchHelpText }}
        </p>
      </form>
    </UCard>

    <div
      v-if="selected_ids.length > 0"
      class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-default bg-default px-3 py-3"
    >
      <div class="flex flex-wrap items-center gap-2">
        <UBadge color="neutral" variant="soft" size="sm">{{ selected_ids.length }}</UBadge>

        <UButton
          color="neutral"
          variant="outline"
          size="sm"
          icon="i-lucide-trash-2"
          :loading="massActionInProgress"
          :disabled="massActionInProgress"
          @click="() => void massAction('delete')"
        >
          Delete
        </UButton>

        <UButton
          color="neutral"
          variant="soft"
          size="sm"
          icon="i-lucide-eye"
          :loading="massActionInProgress"
          :disabled="massActionInProgress"
          @click="() => void massAction('mark_played')"
        >
          Mark Played
        </UButton>

        <UButton
          color="neutral"
          variant="soft"
          size="sm"
          icon="i-lucide-eye-off"
          :loading="massActionInProgress"
          :disabled="massActionInProgress"
          @click="() => void massAction('mark_unplayed')"
        >
          Mark Unplayed
        </UButton>
      </div>

      <div class="text-xs text-toned">{{ filteredItems.length }} displayed</div>
    </div>

    <div v-if="total && last_page > 1" class="flex flex-wrap items-center justify-between gap-3">
      <Pager :page="page" :last_page="last_page" :is-loading="isLoading" @navigate="navigatePage" />
    </div>

    <UAlert
      v-if="isLoading"
      color="info"
      variant="soft"
      icon="i-lucide-loader-circle"
      title="Loading"
      description="Loading data. Please wait..."
      :ui="{ icon: 'animate-spin' }"
    />

    <UAlert
      v-else-if="filteredItems.length < 1"
      color="warning"
      variant="soft"
      icon="i-lucide-triangle-alert"
      title="No items found"
    >
      <template #description>
        <div class="space-y-2 text-sm text-default">
          <p>
            No items found.
            <span v-if="query">
              For
              <code
                ><strong>{{ searchField }}</strong
                >: <strong>{{ query }}</strong></code
              >
            </span>
            <span v-if="filter">
              For
              <code
                ><strong>Filter</strong>: <strong>{{ filter }}</strong></code
              >
            </span>
          </p>

          <code
            v-if="error"
            class="block rounded-md border border-default bg-elevated/60 p-3 text-xs"
          >
            {{ error }}
          </code>
        </div>
      </template>
    </UAlert>

    <div v-else class="grid gap-4 xl:grid-cols-2">
      <Lazy
        v-for="item in filteredItems"
        :key="item.id"
        :unrender="true"
        :min-height="260"
        class="min-h-65"
      >
        <UCard
          class="h-full border border-default/70 shadow-sm"
          :class="item.watched ? 'bg-default/90 ring-1 ring-success/20' : 'bg-default/90'"
          :ui="historyCardUi"
        >
          <template #header>
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div
                  class="flex min-w-0 items-start gap-2 text-base font-semibold leading-6 text-highlighted"
                >
                  <UIcon
                    :name="'episode' === item.type ? 'i-lucide-tv' : 'i-lucide-film'"
                    class="mt-0.5 size-4 shrink-0 text-toned"
                  />

                  <div class="min-w-0 flex-1">
                    <FloatingImage
                      v-if="poster_enable"
                      :image="`/history/${item.id}/images/poster`"
                    >
                      <NuxtLink
                        :to="`/history/${item.id}`"
                        class="text-highlighted hover:text-primary"
                      >
                        {{ item.full_title || makeName(item as unknown as JsonObject) }}
                      </NuxtLink>
                    </FloatingImage>

                    <NuxtLink
                      v-else
                      :to="`/history/${item.id}`"
                      class="text-highlighted hover:text-primary"
                    >
                      {{ item.full_title || makeName(item as unknown as JsonObject) }}
                    </NuxtLink>
                  </div>
                </div>
              </div>

              <div class="flex shrink-0 items-start">
                <UTooltip :text="selected_ids.includes(item.id) ? 'Unselect item' : 'Select item'">
                  <UCheckbox
                    color="primary"
                    :model-value="selected_ids.includes(item.id)"
                    @update:model-value="toggleSelected(item.id, $event)"
                  />
                </UTooltip>
              </div>
            </div>
          </template>

          <div class="space-y-3">
            <div
              v-if="item.content_title"
              class="flex items-start justify-between gap-3 rounded-md border border-default bg-elevated/40 px-3 py-2.5"
            >
              <div
                class="min-w-0 flex-1 cursor-pointer"
                :class="item.expand_title ? '' : 'overflow-hidden text-ellipsis whitespace-nowrap'"
                @click="item.expand_title = !item.expand_title"
              >
                <span class="inline-flex items-center gap-2 text-sm font-medium text-default">
                  <UIcon name="i-lucide-heading" class="size-4 shrink-0 text-toned" />
                  <NuxtLink
                    :to="makeSearchLink('subtitle', item.content_title ?? '')"
                    class="hover:text-primary"
                  >
                    {{ item.content_title }}
                  </NuxtLink>
                </span>
              </div>

              <UTooltip text="Copy subtitle">
                <UButton
                  color="neutral"
                  variant="ghost"
                  size="sm"
                  square
                  icon="i-lucide-copy"
                  aria-label="Copy subtitle"
                  @click="() => void copyText(item.content_title ?? '', false)"
                />
              </UTooltip>
            </div>

            <div
              v-if="item.content_path"
              class="flex items-start justify-between gap-3 rounded-md border border-default bg-elevated/40 px-3 py-2.5"
            >
              <div
                class="min-w-0 flex-1 cursor-pointer"
                :class="item.expand_path ? '' : 'overflow-hidden text-ellipsis whitespace-nowrap'"
                @click="item.expand_path = !item.expand_path"
              >
                <span class="inline-flex items-center gap-2 text-sm font-medium text-default">
                  <UIcon name="i-lucide-file-text" class="size-4 shrink-0 text-toned" />
                  <NuxtLink
                    :to="makeSearchLink('path', item.content_path ?? '')"
                    class="hover:text-primary"
                  >
                    {{ item.content_path }}
                  </NuxtLink>
                </span>
              </div>

              <UTooltip text="Copy file path">
                <UButton
                  color="neutral"
                  variant="ghost"
                  size="sm"
                  square
                  icon="i-lucide-copy"
                  aria-label="Copy file path"
                  @click="() => void copyText(item.content_path ?? '', false)"
                />
              </UTooltip>
            </div>

            <div
              v-if="item.progress"
              class="flex items-center justify-center gap-2 rounded-md border border-default bg-elevated/40 px-3 py-2.5 text-sm font-medium text-default"
            >
              <UIcon name="i-lucide-gauge" class="size-4 shrink-0 text-toned" />
              <span>{{ formatDuration(item.progress as number) }}</span>
            </div>
          </div>

          <template #footer>
            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
              <div
                class="flex items-center justify-center gap-2 rounded-md border border-default bg-elevated/40 px-3 py-2 text-center text-sm font-medium text-default"
              >
                <UIcon name="i-lucide-calendar" class="size-4 shrink-0 text-toned" />
                <UTooltip
                  :text="`Record updated at: ${moment.unix(item.updated_at).format(TOOLTIP_DATE_FORMAT)}`"
                >
                  <span class="cursor-help">{{ moment.unix(item.updated_at).fromNow() }}</span>
                </UTooltip>
              </div>

              <div
                class="flex items-center justify-center gap-2 rounded-md border border-default bg-elevated/40 px-3 py-2 text-center text-sm font-medium text-default"
              >
                <UIcon name="i-lucide-server" class="size-4 shrink-0 text-toned" />
                <div>
                  <NuxtLink :to="`/backend/${item.via}`" class="hover:text-primary">{{
                    item.via
                  }}</NuxtLink>
                  <UTooltip
                    v-if="item.metadata && Object.keys(item.metadata).length > 1"
                    :text="`Also reported by: ${Object.keys(item.metadata)
                      .filter((i) => i !== item.via)
                      .join(', ')}.`"
                  >
                    <span class="ml-1 cursor-help text-toned">
                      (+{{ Object.keys(item.metadata).length - 1 }})
                    </span>
                  </UTooltip>
                </div>
              </div>

              <div
                class="flex items-center justify-center gap-2 rounded-md border border-default bg-elevated/40 px-3 py-2 text-center text-sm font-medium text-default sm:col-span-2 xl:col-span-1"
              >
                <UIcon name="i-lucide-mail" class="size-4 shrink-0 text-toned" />
                <span>{{ item.event ?? '-' }}</span>
              </div>
            </div>
          </template>
        </UCard>
      </Lazy>
    </div>

    <div v-if="total && last_page > 1" class="flex flex-wrap items-center justify-between gap-3">
      <Pager :page="page" :last_page="last_page" :is-loading="isLoading" @navigate="navigatePage" />
      <div class="text-xs text-toned">Page {{ page }} of {{ last_page }}</div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter, useHead } from '#app';
import { useStorage } from '@vueuse/core';
import moment from 'moment';
import Lazy from '~/components/Lazy.vue';
import Pager from '~/components/Pager.vue';
import { NuxtLink } from '#components';
import FloatingImage from '~/components/FloatingImage.vue';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import {
  request,
  awaitElement,
  copyText,
  formatDuration,
  makeName,
  makeSearchLink,
  notification,
  TOOLTIP_DATE_FORMAT,
  parse_api_response,
} from '~/utils';
import type { HistoryItem, JsonObject, PaginationInfo, RequestOptions } from '~/types';
import { useDialog } from '~/composables/useDialog.ts';

type HistoryPagination = PaginationInfo;

const pageShell = requireTopLevelPageShell('history');

type HistorySearchableField = {
  key: string;
  display?: string;
  description?: string;
  type?: string | Array<string>;
};

const route = useRoute();
const router = useRouter();

useHead({ title: 'History' });

const poster_enable = useStorage('poster_enable', true);

type HistoryItemWithUIState = Omit<
  HistoryItem,
  'metadata' | 'extra' | 'files' | 'parent' | 'rguids'
> & {
  metadata?: Record<string, { via?: string }>;
  extra?: Record<string, unknown>;
  files?: Array<unknown>;
  parent?: Record<string, string>;
  rguids?: Record<string, string>;
  full_title?: string;
  showRawData?: boolean;
  expand_title?: boolean;
  expand_path?: boolean;
};

const jsonFields = ref<Array<string>>(['metadata', 'extra']);
const items = ref<Array<HistoryItemWithUIState>>([]);
const searchable = ref<Array<HistorySearchableField>>([
  { key: 'id' },
  { key: 'via' },
  { key: 'year' },
  { key: 'type' },
  { key: 'title' },
  { key: 'season' },
  { key: 'episode' },
  { key: 'parent' },
  { key: 'guid' },
]);
const error = ref('');

const page = ref<number>(parseInt(route.query.page as string) || 1);
const perpage = ref<number>(parseInt(route.query.perpage as string) || 50);
const total = ref<number>(0);
const last_page = computed<number>(() => Math.ceil(total.value / perpage.value));

const query = ref<string>((route.query.q as string) || '');
const searchField = ref<string>((route.query.key as string) || 'title');
const isLoading = ref(false);
const filter = ref<string>((route.query.filter as string) || '');
const showFilter = ref<boolean>(Boolean(filter.value));
const searchForm = ref(false);
const selectAll = ref(false);
const selected_ids = ref<Array<number>>([]);
const massActionInProgress = ref(false);

const panelCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const historyCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
  footer: 'px-4 pb-4 pt-0',
};

const perPageItems = [50, 100, 200, 400, 500].map((value) => ({
  label: `${value} per page`,
  value,
}));

const searchFieldItems = computed(() =>
  searchable.value.map((field) => ({
    label: field.display ?? field.key,
    value: field.key,
  })),
);

const getHelpText = (key: string): string => {
  if (!key) {
    return '';
  }

  const field = searchable.value.find((entry) => entry.key === key);
  if (!field?.description) {
    return '';
  }

  let text = field.description;

  if (field.type) {
    text += ` Expected value: ${Array.isArray(field.type) ? field.type.join(' or ') : field.type}`;
  }

  return text;
};

const searchHelpText = computed(() => getHelpText(searchField.value));

const stringifyItem = (item: HistoryItemWithUIState): string => JSON.stringify(item).toLowerCase();

const filteredRows = (input: Array<HistoryItemWithUIState>): Array<HistoryItemWithUIState> => {
  if (!filter.value) {
    return input;
  }

  return input.filter((item) => stringifyItem(item).includes(filter.value.toLowerCase()));
};

const filteredItems = computed(() => filteredRows(items.value));

watch(selectAll, (value: boolean) => {
  selected_ids.value = value ? filteredItems.value.map((item) => item.id) : [];
});

const toggleSelected = (id: number, value: boolean | 'indeterminate'): void => {
  if (true === value) {
    if (!selected_ids.value.includes(id)) {
      selected_ids.value.push(id);
    }
    return;
  }

  selected_ids.value = selected_ids.value.filter((itemId) => itemId !== id);
};

const navigatePage = (pageNumber: number): void => {
  void loadContent(pageNumber);
};

const loadContent = async (pageNumber: number, fromPopState: boolean = false): Promise<void> => {
  pageNumber = parseInt(pageNumber.toString());

  if (Number.isNaN(pageNumber) || pageNumber < 1) {
    pageNumber = 1;
  }

  let title = `History: Page #${pageNumber}`;

  const search = new URLSearchParams();
  search.set('perpage', perpage.value.toString());
  search.set('page', pageNumber.toString());

  if (searchField.value && query.value) {
    search.set('q', query.value);
    search.set('key', searchField.value);
    title += `. (Search: ${query.value})`;
  }

  if (filter.value) {
    title += `. (Filter: ${filter.value})`;
  }

  useHead({ title });

  const newUrl = `${window.location.pathname}?${search.toString()}`;

  try {
    if (searchField.value && query.value) {
      search.delete('q');
      search.delete('key');

      if (jsonFields.value.includes(searchField.value)) {
        search.set(searchField.value, '1');
        const [field, value] = splitQuery(query.value, '://');
        if (-1 === query.value.indexOf('://') || !value || !field) {
          notification('error', 'Error', `Invalid search format for '${searchField.value}'.`);
          return;
        }
        search.set('key', field);
        search.set('value', value);
      } else {
        search.set(searchField.value, query.value);
      }
    }

    isLoading.value = true;
    items.value = [];

    const response = await request(`/history?${search.toString()}`);
    const json = await parse_api_response<{
      history: Array<HistoryItem>;
      paging: HistoryPagination;
      searchable: Array<HistorySearchableField>;
      filters?: Record<string, unknown>;
    }>(response);

    if ('error' in json) {
      error.value = json.error?.message || 'Unknown error occurred';
      return;
    }

    if (useRoute().name !== 'history') {
      await unloadPage();
      return;
    }

    const currentUrl = `${window.location.pathname}?${new URLSearchParams(window.location.search).toString()}`;

    if (!fromPopState && currentUrl !== newUrl) {
      const history_query: Record<string, string | number | undefined> = {
        perpage: perpage.value,
        page: pageNumber,
      };

      if (searchField.value && query.value) {
        history_query.q = query.value;
        history_query.key = searchField.value;
      }

      if (filter.value) {
        history_query.filter = filter.value;
      }

      await router.push({ path: '/history', query: history_query });
    }

    if ('paging' in json) {
      page.value = json.paging.current_page;
      perpage.value = json.paging.perpage;
      total.value = json.paging.total;
    } else {
      page.value = 1;
      total.value = 0;
    }

    if (json.history) {
      for (const item of json.history) {
        const fullTitle = makeName(item as unknown as JsonObject);
        if (fullTitle) {
          item.full_title = fullTitle;
        }

        items.value.push(item as unknown as HistoryItemWithUIState);
      }
    }

    if (json.searchable) {
      searchable.value = json.searchable;
    }
  } catch (e) {
    console.error('Failed to load content:', e);
  } finally {
    isLoading.value = false;
    selectAll.value = false;
    selected_ids.value = [];
  }
};

const clearSearch = (): void => {
  query.value = '';
  filter.value = '';
  searchForm.value = false;
  showFilter.value = false;
  void loadContent(1);
};

const splitQuery = (value: string, delimiter: string): Array<string> => {
  const index = value.indexOf(delimiter);
  return -1 === index ? [value] : [value.slice(0, index), value.slice(index + delimiter.length)];
};

const toggleFilter = (): void => {
  showFilter.value = !showFilter.value;
  if (!showFilter.value) {
    filter.value = '';
    return;
  }

  awaitElement('#filter', (_, element) => (element as HTMLInputElement).focus());
};

const massAction = async (action: 'delete' | 'mark_played' | 'mark_unplayed'): Promise<void> => {
  if (0 === selected_ids.value.length) {
    return;
  }

  const title = {
    delete: 'Delete',
    mark_played: 'Mark as played',
    mark_unplayed: 'Mark as unplayed',
  }[action];

  const { status: confirmStatus } = await useDialog().confirmDialog({
    message: `Are you sure you want to '${title}' ${selected_ids.value.length} item/s?`,
    confirmColor: 'delete' === action ? 'error' : 'primary',
  });

  if (true !== confirmStatus) {
    return;
  }

  let urls: Array<string> = [];
  let opts: RequestOptions = {};
  let callback: (() => void) | null = null;

  massActionInProgress.value = true;

  if ('delete' === action) {
    opts = { method: 'DELETE' };
    urls = selected_ids.value.map((id) => `/history/${id}`);
    callback = () => {
      items.value = items.value.filter((item) => !selected_ids.value.includes(item.id));
    };
  }

  if ('mark_played' === action || 'mark_unplayed' === action) {
    opts = { method: 'mark_played' === action ? 'POST' : 'DELETE' };
    const ids = selected_ids.value
      .map((id) => items.value.find((item) => item.id === id))
      .filter((item): item is HistoryItemWithUIState => undefined !== item)
      .filter((item) => ('mark_played' === action ? !item.watched : item.watched))
      .map((item) => item.id);

    urls = ids.map((value) => `/history/${value}/watch`);
    callback = () => {
      items.value.forEach((item) => {
        if (ids.includes(item.id)) {
          item.watched = 'mark_played' === action;
        }
      });
    };
  }

  try {
    notification(
      'success',
      'Action in progress',
      `Processing Mass '${title}' request. Please wait...`,
    );

    const requests = await Promise.all(urls.map((url) => request(url, opts)));
    const all_ok = requests.every((response) => 200 === response.status);

    if (!all_ok) {
      notification(
        'error',
        'Error',
        'Some requests failed. Please check the console for more details.',
      );
    }

    if (all_ok && callback) {
      callback();
    }

    notification('success', 'Success', `Mass '${title}' request completed.`);
  } catch (e) {
    const err = e as Error;
    notification('error', 'Error', `Request error. ${err.message}`);
  } finally {
    massActionInProgress.value = false;
    selected_ids.value = [];
    selectAll.value = false;
  }
};

const stateCallBack = async (event: Event): Promise<void> => {
  const popStateEvent = event as PopStateEvent;
  const customEvent = event as CustomEvent;

  if (!popStateEvent.state && !customEvent.detail) {
    return;
  }

  const state = customEvent.detail ?? popStateEvent.state;
  const currentRoute = useRoute();

  page.value = parseInt(currentRoute.query.page as string) || 1;
  perpage.value = parseInt(currentRoute.query.perpage as string) || 50;
  filter.value = (currentRoute.query.filter as string) || '';

  if (filter.value) {
    showFilter.value = true;
  }

  if ('clear' in state) {
    query.value = '';
    searchField.value = 'title';
  } else {
    query.value = (currentRoute.query.q as string) || '';
    searchField.value = (currentRoute.query.key as string) || 'title';
    if (query.value) {
      searchForm.value = true;
    }
  }

  await loadContent(page.value, true);
};

watch(filter, (value: string) => {
  const currentRoute = useRoute();
  const currentRouter = useRouter();

  if (!value) {
    if (!currentRoute?.query.filter) {
      return;
    }

    currentRouter.push({
      path: '/history',
      query: {
        ...currentRoute.query,
        filter: undefined,
      },
    });
    return;
  }

  if (currentRoute?.query.filter === value) {
    return;
  }

  currentRouter.push({
    path: '/history',
    query: {
      ...currentRoute.query,
      filter: value,
    },
  });
});

onMounted(async (): Promise<void> => {
  if (query.value) {
    searchForm.value = true;
  }

  window.addEventListener('popstate', stateCallBack);
  window.addEventListener('history_main_link_clicked', stateCallBack);
  await loadContent(page.value ?? 1);
});

const unloadPage = async (): Promise<void> => {
  window.removeEventListener('history_main_link_clicked', stateCallBack);
  window.removeEventListener('popstate', stateCallBack);
};

onUnmounted(async (): Promise<void> => {
  await unloadPage();
});
</script>
