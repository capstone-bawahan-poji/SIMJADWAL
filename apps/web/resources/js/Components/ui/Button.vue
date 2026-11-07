<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
        size?: 'sm' | 'md';
        type?: 'button' | 'submit';
        loading?: boolean;
        disabled?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button', loading: false, disabled: false },
);

const sizeClass = computed(
    () =>
        ({
            sm: 'h-8 px-3 text-xs',
            md: 'h-10 px-4 text-sm',
        })[props.size],
);

const variantClass = computed(
    () =>
        ({
            primary:
                'bg-primary text-primary-foreground shadow-md shadow-primary/20 hover:bg-primary-hover',
            secondary: 'border border-gray-200 bg-white text-slate-700 hover:bg-slate-50',
            danger: 'bg-red-600 text-white shadow-md shadow-red-600/20 hover:bg-red-700',
            ghost: 'text-slate-700 hover:bg-slate-100',
        })[props.variant],
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
        :class="[sizeClass, variantClass]"
    >
        <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
