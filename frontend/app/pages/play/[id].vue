<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
      <div class="space-y-2">
        <div
          class="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-toned"
        >
          <UIcon :name="pageShell.icon" class="size-4" />
          <span>{{ pageShell.sectionLabel }}</span>
          <span>/</span>
          <NuxtLink to="/history" class="hover:text-primary">{{ pageShell.pageLabel }}</NuxtLink>
          <span>/</span>
          <span class="text-highlighted normal-case tracking-normal">Play</span>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-lg font-semibold text-highlighted">
          <UIcon name="i-lucide-play" class="size-5 text-toned" />
          <span class="wrap-break-word">{{ displayName }}</span>
        </div>

        <p v-if="item?.content_title" class="text-sm text-toned">{{ item?.content_title }}</p>
      </div>

      <div v-if="isPlaying" class="flex flex-wrap items-center justify-end gap-2">
        <UTooltip text="Go back.">
          <UButton
            color="neutral"
            variant="outline"
            size="sm"
            icon="i-lucide-arrow-left"
            @click="closeStream"
          >
            <span class="hidden sm:inline">Back</span>
          </UButton>
        </UTooltip>

        <UTooltip text="Toggle watch state">
          <UButton
            color="neutral"
            :variant="item.watched ? 'soft' : 'outline'"
            size="sm"
            :icon="item.watched ? 'i-lucide-eye-off' : 'i-lucide-eye'"
            @click="toggleWatched"
          >
            <span class="hidden sm:inline">{{ item.watched ? 'Unwatched' : 'Watched' }}</span>
          </UButton>
        </UTooltip>
      </div>
    </div>

    <template v-if="!isPlaying">
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
        v-else-if="(item?.files?.length ?? 0) < 1"
        color="warning"
        variant="soft"
        icon="i-lucide-triangle-alert"
        title="Warning"
        description="No video URLs were found."
      />
    </template>

    <Player v-if="isPlaying" :link="playUrl" />

    <template v-else>
      <UCard :ui="cardUi">
        <template #header>
          <div class="text-base font-semibold text-highlighted">Select settings.</div>
        </template>

        <div class="space-y-5">
          <UFormField label="Select source file" name="config_path">
            <USelect
              v-model="config.path"
              :items="fileItems"
              value-key="value"
              placeholder="Select..."
              icon="i-lucide-file-video"
              class="w-full"
              @update:model-value="(value) => changeStream(null, String(value))"
            />
          </UFormField>

          <UFormField
            v-if="selectedItem?.ffprobe?.streams"
            label="Select audio stream"
            name="config_audio"
          >
            <USelect
              v-model="config.audio"
              :items="audioItems"
              value-key="value"
              placeholder="Select audio stream..."
              icon="i-lucide-file-audio"
              class="w-full"
            />
          </UFormField>

          <UFormField
            v-if="filterStreams('subtitle').length > 0 || externalSubtitles.length > 0"
            label="Burn subtitles"
            name="config_subtitle"
            description="We recommend using the burn subtitle function only when you are using a picture based subtitles. Text based subtitles are able to be selected and converted on the fly using the player."
          >
            <USelect
              v-model="config.subtitle"
              :items="subtitleItems"
              value-key="value"
              placeholder="Select subtitle..."
              icon="i-lucide-captions"
              class="w-full"
            />
          </UFormField>

          <template v-if="showAdvanced">
            <UFormField
              label="Video transcoding codec"
              name="video_codec"
              description="We don't do pre-checks on codecs, so some of those codecs may not work or you don't have the hardware for it."
            >
              <USelect
                v-model="video_codec"
                :items="codecItems"
                value-key="value"
                placeholder="Select codec..."
                icon="i-lucide-captions"
                class="w-full"
                @update:model-value="(value) => updateHwAccel(String(value))"
              />
            </UFormField>

            <UFormField
              v-if="'h264_vaapi' === config.video_codec"
              label="Select VAAPI rendering device"
              name="vaapi_device"
              description="The standard H264 (CPU) is the default and should work on most systems."
            >
              <USelect
                v-model="vaapi_device"
                :items="deviceItems"
                value-key="value"
                placeholder="Select device..."
                icon="i-lucide-monitor-cog"
                class="w-full"
              />
            </UFormField>

            <div class="rounded-md border border-default bg-elevated/30 px-3 py-3">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-medium text-highlighted">
                    Include debug information in response headers
                  </div>
                  <p class="mt-1 text-sm text-toned">
                    Useful to know what options and ffmpeg command being run.
                  </p>
                </div>

                <USwitch id="debug" v-model="session_debug" color="neutral" />
              </div>
            </div>
          </template>
        </div>

        <template #footer>
          <div v-if="config?.path" class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <UButton
              color="neutral"
              :variant="showAdvanced ? 'soft' : 'outline'"
              size="sm"
              icon="i-lucide-settings"
              @click="showAdvanced = !showAdvanced"
            >
              <span class="hidden sm:inline">Advanced settings</span>
            </UButton>

            <UButton
              color="neutral"
              variant="outline"
              size="sm"
              icon="i-lucide-play"
              :loading="isGenerating"
              :disabled="isGenerating"
              @click="generateToken"
            >
              <span class="hidden sm:inline">Play</span>
            </UButton>
          </div>
        </template>
      </UCard>

      <UCard class="border border-default/70 shadow-sm" :ui="tipsCardUi">
        <template #header>
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            @click="show_page_tips = !show_page_tips"
          >
            <span class="inline-flex items-center gap-2 text-sm font-semibold text-highlighted">
              <UIcon name="i-lucide-info" class="size-4 text-toned" />
              <span>Tips</span>
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-medium text-toned">
              <UIcon
                :name="show_page_tips ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                class="size-4"
              />
              <span>{{ show_page_tips ? 'Hide' : 'Show' }}</span>
            </span>
          </button>
        </template>

        <ul v-if="show_page_tips" class="list-disc space-y-2 pl-5 text-sm leading-6 text-default">
          <li>
            Selecting subtitle for burn in will force the video stream to be converted. We attempt
            to direct play compatible streams when possible. Text based subtitles can be converted
            on the fly in the player. and require no burn in.
          </li>
          <li>
            Right now the transcoding is done via CPU and is not optimized for best performance. We
            have plans to include GPU acceleration in the future.
          </li>
          <li>
            If you select subtitle for burn in the player will no longer show text based subtitles
            for selection.
          </li>
          <li>
            Right now we are transcoding all streams to <code>H264</code> for video and
            <code>AAC</code> for audio, regardless of the stream is compatible with the browser or
            not. this will hopefully change in the feature to allow direct play of compatible
            streams.
          </li>
        </ul>
      </UCard>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, navigateTo } from '#app';
import { useStorage } from '@vueuse/core';
import Player from '~/components/Player.vue';
import { requireTopLevelPageShell } from '~/utils/topLevelNavigation';
import {
  request,
  basename,
  disableOpacity,
  enableOpacity,
  notification,
  ucFirst,
  parse_api_response,
} from '~/utils';
import { useDialog } from '~/composables/useDialog';

type SelectItem = {
  label: string;
  value?: string | number;
  type?: 'label' | 'item';
};

type PlayStream = {
  index: number;
  codec_type: 'video' | 'audio' | 'subtitle';
  codec_name: string;
  tags?: {
    title?: string;
    language?: string;
  };
  disposition?: {
    default?: number;
  };
};

const route = useRoute();

const id = route.params.id as string;
const pageShell = requireTopLevelPageShell('history');
type PlayMediaFile = {
  path: string;
  source: Array<string>;
  subtitles: Array<string>;
  ffprobe?: {
    streams?: Array<PlayStream>;
  };
};

type PlayItem = {
  id: string | number;
  type: string;
  title: string;
  year?: number;
  season?: number;
  episode?: number;
  watched: boolean;
  content_title?: string;
  files?: Array<PlayMediaFile>;
  hardware?: {
    codecs?: Array<{ codec: string; name: string; hwaccel: boolean }>;
    devices?: Array<string>;
  };
};

type PlayNameInfo = {
  title?: string;
  year?: number;
  type?: string;
  season?: number;
  episode?: number;
};

const item = ref<PlayItem>({
  id,
  type: 'movie',
  title: '',
  watched: false,
});
const playNameInfo = ref<PlayNameInfo>({
  title: '',
  type: 'movie',
});
const isLoading = ref<boolean>(false);
const isPlaying = ref<boolean>(false);
const isGenerating = ref<boolean>(false);
const playUrl = ref<string>('');
const showAdvanced = useStorage('play_showAdvanced', false);
const show_page_tips = useStorage('show_page_tips', true);
const video_codec = useStorage('play_vcodec', 'libx264');
const vaapi_device = useStorage('play_vaapi_device', '');
const session_debug = useStorage('play_debug', false);

const config = ref<{
  path: string;
  audio: string | number;
  subtitle: string | number;
  video_codec: string;
  vaapi_device: string;
  hwaccel: boolean;
  debug: boolean;
}>({
  path: '',
  audio: '',
  subtitle: '',
  video_codec: video_codec.value,
  vaapi_device: vaapi_device.value,
  hwaccel: false,
  debug: session_debug.value,
});

const selectedItem = ref<PlayMediaFile | null>(null);
const externalSubtitles = computed((): Array<string> => selectedItem.value?.subtitles ?? []);

const cardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
  footer: 'border-t border-default px-4 py-4',
};

const tipsCardUi = {
  header: 'p-4',
  body: 'px-4 pb-4 pt-0',
};

const fileItems = computed<Array<Array<SelectItem>>>(() =>
  (item.value.files ?? []).map((file) => [
    { label: `In: ${file.source.join(', ')}`, type: 'label' },
    { label: basename(file.path), value: file.path, type: 'item' },
  ]),
);

const audioItems = computed<Array<SelectItem>>(() =>
  filterStreams('audio').map((stream) => ({
    value: stream.index,
    label: `${stream.index} - ${String(stream.codec_name).toUpperCase()}${stream.tags?.title ? ` - ${ucFirst(String(stream.tags.title))}` : ''}${stream.tags?.language ? ` - (${String(stream.tags.language).toUpperCase()})` : ''}`,
  })),
);

const subtitleItems = computed<Array<Array<SelectItem>>>(() => {
  const items: Array<Array<SelectItem>> = [];

  if (filterStreams('subtitle').length > 0) {
    items.push([
      { label: 'Internal Subtitles', type: 'label' },
      ...filterStreams('subtitle').map((stream) => ({
        value: stream.index,
        label: `${stream.index} - ${String(stream.codec_name).toUpperCase()}${stream.tags?.title ? ` - ${ucFirst(String(stream.tags.title))}` : ''}${stream.tags?.language ? ` - (${String(stream.tags.language).toUpperCase()})` : ''}`,
        type: 'item' as const,
      })),
    ]);
  }

  if (externalSubtitles.value.length > 0) {
    items.push([
      { label: 'External Subtitles', type: 'label' },
      ...externalSubtitles.value.map((subtitle) => ({
        label: basename(subtitle),
        value: subtitle,
        type: 'item' as const,
      })),
    ]);
  }

  return items;
});

const codecItems = computed<Array<SelectItem>>(() =>
  (item.value.hardware?.codecs ?? []).map((codec) => ({ label: codec.name, value: codec.codec })),
);

const deviceItems = computed<Array<SelectItem>>(() =>
  (item.value.hardware?.devices ?? []).map((device) => ({
    label: basename(device),
    value: device,
  })),
);

const formatPlayName = (value: PlayNameInfo): string => {
  const title = value.title || '??';
  const year = value.year ?? '0000';
  const type = value.type || 'movie';

  if (['show', 'movie'].includes(type)) {
    return `${title} (${year})`;
  }

  const season = String(value.season ?? 0).padStart(2, '0');
  const episode = String(value.episode ?? 0).padStart(3, '0');

  return `${title} (${year}) - ${season}x${episode}`;
};

const displayName = computed((): string => {
  if (playNameInfo.value.title) {
    return formatPlayName(playNameInfo.value);
  }

  return String(id);
});

const loadContent = async (): Promise<void> => {
  isLoading.value = true;
  try {
    const response = await request(`/history/${id}?files=true`);
    const json = await parse_api_response<PlayItem>(response);
    if ('error' in json) {
      notification('error', 'Error', 'Failed to load item.');
      return;
    }
    item.value = json;
    playNameInfo.value = {
      title: json.title,
      year: json.year,
      type: json.type,
      season: json.season,
      episode: json.episode,
    };
  } catch (error: unknown) {
    console.error(error);
    notification('error', 'Error', 'Failed to load item.');
  } finally {
    isLoading.value = false;
  }

  if (1 === item.value.files?.length) {
    const firstFile = item.value.files[0];
    if (firstFile) {
      config.value.path = firstFile.path;
      selectedItem.value = firstFile;
      await changeStream(null, firstFile.path);
    }
  }
};

const generateToken = async (): Promise<void> => {
  isGenerating.value = true;
  try {
    const userConfig: {
      path: string;
      config: {
        audio: string | number;
        video_codec: string;
        hwaccel: boolean;
        debug: boolean;
        vaapi_device?: string;
        subtitle?: string | number;
        external?: string | number;
      };
    } = {
      path: config.value.path,
      config: {
        audio: config.value.audio,
        video_codec: config.value.video_codec,
        hwaccel: config.value.hwaccel,
        debug: Boolean(config.value.debug),
      },
    };

    if (config.value.vaapi_device && 'h264_vaapi' === config.value.video_codec) {
      userConfig.config.vaapi_device = config.value.vaapi_device;
    }

    if (config.value.subtitle) {
      if (String(config.value.subtitle).match(/^\d+$/)) {
        userConfig.config.subtitle = config.value.subtitle;
      } else {
        userConfig.config.external = config.value.subtitle;
      }
    }

    const response = await request(`/system/sign/${id}`, {
      method: 'POST',
      body: JSON.stringify(userConfig),
    });

    const json = await parse_api_response<{ token: string }>(response);

    if ('error' in json) {
      notification('error', 'Token generation', 'Failed to generate token.');
      return;
    }

    playUrl.value = `/v1/api/player/playlist/${json.token}/master.m3u8`;
    isPlaying.value = true;

    await navigateTo({
      path: `/play/${id}`,
      query: { token: json.token },
    });
  } catch (error: unknown) {
    console.error(error);
    notification('error', 'Error', 'Failed to generate token.');
  } finally {
    isGenerating.value = false;
  }
};

const changeStream = async (e: Event | null, path: string | null = null): Promise<void> => {
  if (!path) {
    const target = e?.target as HTMLSelectElement;
    path = target?.value;
  }
  if (!path) {
    selectedItem.value = null;
    return;
  }

  const files = item.value.files ?? [];
  let matchedFile: PlayMediaFile | null = null;
  for (const file of files) {
    if (file.path === path) {
      matchedFile = file;
      break;
    }
  }
  selectedItem.value = matchedFile;
  filterStreams(['subtitle', 'audio']).forEach((stream) => {
    const isDefault = Number(stream.disposition?.default ?? 0);
    if (1 === isDefault) {
      config.value['audio' === stream.codec_type ? 'audio' : 'subtitle'] = stream.index;
    }
  });
};

const filterStreams = (
  type?: PlayStream['codec_type'] | Array<PlayStream['codec_type']>,
): Array<PlayStream> => {
  const streams = selectedItem.value?.ffprobe?.streams ?? [];

  if (!type) {
    return streams;
  }

  const types = Array.isArray(type) ? type : [type];

  return streams.filter((stream) => types.includes(stream.codec_type));
};

const closeStream = async (): Promise<void> => {
  isPlaying.value = false;
  playUrl.value = '';
  await navigateTo({ path: `/history/${id}` });
};

const toggleWatched = async (): Promise<void> => {
  if (!item.value) {
    return;
  }
  const { status } = await useDialog().confirmDialog({
    title: 'Confirm',
    message: `Mark '${displayName.value}' as ${item.value.watched ? 'unplayed' : 'played'}?`,
  });

  if (true !== status) {
    return;
  }
  try {
    const response = await request(`/history/${item.value.id}/watch`, {
      method: item.value.watched ? 'DELETE' : 'POST',
    });

    const json = await response.json();

    if (200 !== response.status) {
      notification('error', 'Error', `${json.error.code}: ${json.error.message}`);
      return;
    }

    item.value.watched = !item.value.watched;
    notification(
      'success',
      '',
      `Marked '${displayName.value}' as ${item.value.watched ? 'played' : 'unplayed'}`,
    );
  } catch (e: unknown) {
    notification('error', 'Error', `Request error. ${e}`);
  }
};

const onPopState = (): void => {
  if (route.query?.token) {
    playUrl.value = `${useStorage('api_url', '').value}${useStorage('api_path', '/v1/api').value}/player/playlist/${route.query.token}/master.m3u8`;
    isPlaying.value = true;
  } else {
    isPlaying.value = false;
    playUrl.value = '';
  }
};

const updateHwAccel = (codec: string): void => {
  const codecInfo = item.value.hardware?.codecs?.filter((c) => c.codec === codec);
  if (!codecInfo || codecInfo.length < 1) {
    config.value.hwaccel = false;
    return;
  }
  config.value.hwaccel = Boolean(codecInfo[0]?.hwaccel);
  config.value.video_codec = codec;
};

watch(video_codec, (value) => {
  config.value.video_codec = value;
});

watch(vaapi_device, (value) => {
  config.value.vaapi_device = value;
});

watch(session_debug, (value) => {
  config.value.debug = value;
});

watch(isPlaying, (value: boolean) => {
  if (true === value) {
    disableOpacity();
    return;
  }

  enableOpacity();
});

onMounted(async () => {
  window.addEventListener('popstate', onPopState);
  await loadContent();
  if (route.query?.token) {
    playUrl.value = `${useStorage('api_url', '').value}${useStorage('api_path', '/v1/api').value}/player/playlist/${route.query.token}/master.m3u8`;
    isPlaying.value = true;
  }
  updateHwAccel(video_codec.value);
});

onUnmounted(() => {
  window.removeEventListener('popstate', onPopState);
  enableOpacity();
});
</script>
