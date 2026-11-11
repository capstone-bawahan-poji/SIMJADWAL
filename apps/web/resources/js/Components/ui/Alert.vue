<script setup lang="ts">
import { CircleAlert, CircleCheck, TriangleAlert, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'error' | 'success' | 'warning';
        title: string;
        dismissible?: boolean;
    }>(),
    { variant: 'error', dismissible: false },
);

defineEmits<{ dismiss: [] }>();

const styles = computed(
    () =>
        ({
            error: { title: 'text-gray-700', icon: CircleAlert, iconClass: 'text-red-500' },
            success: { title: 'text-gray-700', icon: CircleCheck, iconClass: 'text-green-600' },
            warning: { title: 'text-gray-700', icon: TriangleAlert, iconClass: 'text-amber-600' },
        })[props.variant],
);
</script>

<template>
    <div
        :role="variant === 'success' ? 'status' : 'alert'"
        class="flex items-start gap-2 py-1"
    >
        <component :is="styles.icon" class="mt-0.5 size-4 shrink-0" :class="styles.iconClass" aria-hidden="true" />

        <div class="min-w-0 flex-1">
            <p class="text-sm" :class="styles.title">{{ title }}</p>
            <div v-if="$slots.default" class="mt-0.5 text-sm text-gray-500">
                <slot />
            </div>
        </div>

        <button
            v-if="dismissible"
            type="button"
            class="-m-1 h-fit rounded p-1 text-gray-400 hover:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            aria-label="Tutup"
            @click="$emit('dismiss')"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
