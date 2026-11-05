<script setup lang="ts">
defineProps<{ title: string; dashed?: boolean }>();
</script>

<template>
  <div
    class="flex flex-col items-center justify-center gap-2 rounded-panel px-6 py-12 text-center"
    :class="dashed ? 'border border-dashed border-line-strong' : ''"
  >
    <p class="text-sm font-semibold text-ink-secondary">{{ title }}</p>
    <div v-if="$slots.default" class="max-w-md text-sm text-ink-muted"><slot /></div>
    <div v-if="$slots.action" class="mt-2"><slot name="action" /></div>
  </div>
</template>
