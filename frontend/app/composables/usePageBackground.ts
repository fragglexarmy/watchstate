type PageBackgroundOverride = {
  id: string;
  src: string;
};

const usePageBackground = () => {
  const pageBackgroundOverride = useState<PageBackgroundOverride | null>(
    'page-background-override',
    () => null,
  );
  const pageBackgroundReloadToken = useState<number>('page-background-reload-token', () => 0);

  const setPageBackgroundOverride = (value: PageBackgroundOverride | null): void => {
    pageBackgroundOverride.value = value;
  };

  const clearPageBackgroundOverride = (id?: string): void => {
    if (!pageBackgroundOverride.value) {
      return;
    }

    if (id && pageBackgroundOverride.value.id !== id) {
      return;
    }

    pageBackgroundOverride.value = null;
  };

  const requestPageBackgroundReload = (id?: string): void => {
    if (id && pageBackgroundOverride.value?.id !== id) {
      return;
    }

    pageBackgroundReloadToken.value += 1;
  };

  return {
    pageBackgroundOverride,
    pageBackgroundReloadToken,
    setPageBackgroundOverride,
    clearPageBackgroundOverride,
    requestPageBackgroundReload,
  };
};

export { usePageBackground };
