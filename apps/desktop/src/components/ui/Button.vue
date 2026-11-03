<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

/*
 * One button system for every page.
 * primary: main action of an area (max one per area, placed rightmost).
 * secondary: companion action. tertiary: light action or cancel.
 * danger-secondary: delete in toolbars or panels. danger: only inside delete confirmation dialogs.
 * md (40px) for page actions, sm (32px) for row or segment actions.
 */
const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'tertiary' | 'danger-secondary' | 'danger';
    size?: 'sm' | 'md' | 'lg';
    type?: 'button' | 'submit';
    loading?: boolean;
    disabled?: boolean;
  }>(),
  { variant: 'secondary', size: 'md', type: 'button', loading: false, disabled: false },
);

const variantClass = computed(() => ({
  primary: 'bg-primary text-white hover:bg-primary-hover disabled:bg-disabled disabled:text-ink-faint',
  secondary: 'border border-line-strong bg-white text-ink-secondary hover:bg-canvas disabled:border-disabled disabled:bg-white disabled:text-ink-faint',
  tertiary: 'text-ink-secondary hover:bg-canvas disabled:bg-transparent disabled:text-ink-faint',
  'danger-secondary': 'border border-danger-border bg-white text-danger-ink hover:bg-danger-bg disabled:border-disabled disabled:bg-white disabled:text-ink-faint',
  danger: 'bg-danger text-white hover:bg-danger-hover disabled:bg-disabled disabled:text-ink-faint',
})[props.variant]);

const sizeClass = computed(() => ({
  sm: 'h-8 px-3 text-xs',
  md: 'h-10 px-4 text-sm',
  lg: 'h-12 px-4 text-sm',
})[props.size]);
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-control font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed"
    :class="[variantClass, sizeClass]"
  >
    <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
    <slot />
  </button>
</template>
