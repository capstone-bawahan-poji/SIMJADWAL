<script setup lang="ts">
import Modal from '@/components/ui/Modal.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';

withDefaults(
  defineProps<{
    show: boolean;
    title: string;
    confirmLabel?: string;
    processing?: boolean;
    error?: string | null;
    danger?: boolean;
    confirmDisabled?: boolean;
  }>(),
  { confirmLabel: 'Konfirmasi', processing: false, error: null, danger: false, confirmDisabled: false },
);

defineEmits<{ confirm: []; close: [] }>();
</script>

<template>
  <Modal :show="show" max-width="md" @close="$emit('close')">
    <div class="flex flex-col gap-4 p-6">
      <h2 class="text-base font-semibold text-ink">{{ title }}</h2>
      <div class="text-sm text-ink-tertiary"><slot /></div>
      <Alert v-if="error" title="Tidak dapat diproses">{{ error }}</Alert>
      <div class="flex justify-end gap-2 pt-2">
        <Button variant="tertiary" @click="$emit('close')">Batal</Button>
        <Button
          :variant="danger ? 'danger' : 'primary'"
          :loading="processing"
          :disabled="confirmDisabled"
          @click="$emit('confirm')"
        >
          {{ confirmLabel }}
        </Button>
      </div>
    </div>
  </Modal>
</template>
