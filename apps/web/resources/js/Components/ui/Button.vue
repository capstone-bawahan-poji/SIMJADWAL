<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'ghost';
        type?: 'button' | 'submit';
        loading?: boolean;
        disabled?: boolean;
    }>(),
    { variant: 'primary', type: 'button', loading: false, disabled: false },
);

const variantClass = computed(
    () =>
        ({
            primary:
                'bg-primary text-primary-foreground shadow-md shadow-primary/20 hover:bg-primary-hover',
            ghost: 'text-slate-700 hover:bg-slate-100',
        })[props.variant],
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
        :class="variantClass"
    >
        <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
