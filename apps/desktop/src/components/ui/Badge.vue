<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{ tone?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }>(),
  { tone: 'neutral' },
);

const toneClass = computed(() => ({
  neutral: 'bg-canvas text-ink-tertiary border-line',
  primary: 'bg-primary-soft text-primary border-primary-tint',
  success: 'bg-success-bg text-success-ink border-success-border',
  warning: 'bg-warning-bg text-warning-ink border-warning-border',
  danger: 'bg-danger-bg text-danger-ink border-danger-border',
})[props.tone]);
</script>

<template>
  <span class="inline-flex h-6 shrink-0 items-center gap-1 whitespace-nowrap rounded-badge border px-2 text-xs font-semibold" :class="toneClass">
    <slot />
  </span>
</template>
