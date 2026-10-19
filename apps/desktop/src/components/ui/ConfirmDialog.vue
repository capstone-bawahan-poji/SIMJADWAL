<script setup lang="ts">
import Modal from './Modal.vue';
import Alert from './Alert.vue';
import Button from './Button.vue';

withDefaults(
  defineProps<{
    show: boolean;
    title: string;
    confirmLabel?: string;
    processing?: boolean;
    error?: string | null;
    danger?: boolean;
  }>(),
  { confirmLabel: 'Konfirmasi', processing: false, error: null, danger: false },
);

defineEmits<{ confirm: []; close: [] }>();
</script>

<template>
  <Modal :show="show" max-width="md" @close="$emit('close')">
    <div class="space-y-4 p-6">
      <h2 class="text-lg font-bold text-gray-900">{{ title }}</h2>
      <div class="text-sm text-gray-600"><slot /></div>
      <Alert v-if="error" title="Tidak dapat diproses">{{ error }}</Alert>
      <div class="flex justify-end gap-3 pt-2">
        <Button variant="ghost" class="border border-gray-200" @click="$emit('close')">Batal</Button>
        <Button
          :variant="danger ? 'danger' : 'primary'"
          :loading="processing"
          @click="$emit('confirm')"
        >
          {{ confirmLabel }}
        </Button>
      </div>
    </div>
  </Modal>
</template>
