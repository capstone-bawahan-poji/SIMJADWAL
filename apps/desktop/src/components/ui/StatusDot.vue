<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{ tone?: 'neutral' | 'success' | 'warning' | 'danger' }>(),
  { tone: 'neutral' },
);

const dotClass = computed(() => ({
  neutral: 'bg-ink-faint',
  success: 'bg-success-dot',
  warning: 'bg-warning-dot',
  danger: 'bg-danger',
})[props.tone]);
</script>

<template>
  <span class="inline-flex items-center gap-2">
    <span class="size-2 shrink-0 rounded-full" :class="dotClass" aria-hidden="true" />
    <slot />
  </span>
</template>
