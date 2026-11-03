<script setup lang="ts">
defineProps<{ selected: boolean; title: string; text: string }>();
</script>

<!-- Radio or checkbox card. Put the input in the default slot. -->
<template>
  <label
    class="flex cursor-pointer gap-3 rounded-control border p-4 transition-colors"
    :class="selected ? 'border-primary bg-primary-soft' : 'border-line-strong hover:border-ink-faint'"
  >
    <span class="pt-0.5"><slot /></span>
    <span class="min-w-0">
      <span class="block text-sm font-semibold" :class="selected ? 'text-primary' : 'text-ink'">{{ title }}</span>
      <span class="mt-1 block text-xs text-ink-muted">{{ text }}</span>
    </span>
  </label>
</template>
