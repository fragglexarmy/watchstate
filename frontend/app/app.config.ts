export default defineAppConfig({
  ui: {
    primary: 'indigo',
    colors: {
      primary: 'indigo',
      secondary: 'amber',
      success: 'emerald',
      neutral: 'stone',
    },
    formField: {
      slots: {
        label: 'block font-bold text-default',
      },
    },
    tooltip: {
      slots: {
        content: 'pointer-events-auto',
      },
    },
  },
});
