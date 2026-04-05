import { computed, toValue, type MaybeRefOrGetter, type Ref } from 'vue';
import { useDialog } from '~/composables/useDialog';

type DirtyCloseGuardOptions = {
  dirty: MaybeRefOrGetter<boolean>;
  title?: string;
  message?: string;
  confirmText?: string;
  cancelText?: string;
  confirmColor?: 'primary' | 'success' | 'info' | 'warning' | 'error' | 'neutral';
  onDiscard?: () => void | Promise<void>;
};

export const useDirtyCloseGuard = (open: Ref<boolean>, options: DirtyCloseGuardOptions) => {
  const dialog = useDialog();

  const isDirty = computed<boolean>(() => Boolean(toValue(options.dirty)));

  const requestClose = async (): Promise<boolean> => {
    if (false === isDirty.value) {
      open.value = false;
      return true;
    }

    const { status } = await dialog.confirmDialog({
      title: options.title ?? 'Discard changes?',
      message: options.message ?? 'You have unsaved changes. Do you want to discard them?',
      confirmText: options.confirmText ?? 'Discard changes',
      cancelText: options.cancelText ?? 'Keep editing',
      confirmColor: options.confirmColor ?? 'warning',
    });

    if (true !== status) {
      return false;
    }

    await options.onDiscard?.();
    open.value = false;
    return true;
  };

  const handleOpenChange = async (value: boolean): Promise<void> => {
    if (true === value) {
      open.value = true;
      return;
    }

    await requestClose();
  };

  return {
    isDirty,
    requestClose,
    handleOpenChange,
  };
};
