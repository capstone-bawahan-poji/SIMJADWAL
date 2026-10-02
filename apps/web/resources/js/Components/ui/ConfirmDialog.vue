<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';

withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        confirmLabel?: string;
        processing?: boolean;
        error?: string | null;
        danger?: boolean;
    }>(),
    { confirmLabel: 'Hapus', processing: false, error: null, danger: true },
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
                <Button :class="{ '!bg-red-600 !shadow-red-600/20 hover:!bg-red-700': danger }" :loading="processing" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </Button>
            </div>
        </div>
    </Modal>
</template>
