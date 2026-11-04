<script setup lang="ts">
defineProps<{ label: string; value: string | number; caption?: string; tone?: 'default' | 'danger' | 'success' }>();
</script>

<template>
  <div class="min-w-0 px-6 py-4">
    <p class="truncate text-xs font-medium text-ink-muted">{{ label }}</p>
    <p
      class="tabular mt-1 text-xl font-semibold"
      :class="tone === 'danger' ? 'text-danger-ink' : tone === 'success' ? 'text-success-ink' : 'text-ink'"
    >
      {{ value }}
    </p>
    <p v-if="caption" class="mt-1 truncate text-xs text-ink-muted">{{ caption }}</p>
  </div>
</template>
