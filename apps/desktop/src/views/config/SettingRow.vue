<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<!-- Two-column settings row: title and description left, fields right. -->
<template>
  <section class="grid grid-cols-[minmax(0,280px)_minmax(0,1fr)] gap-8 border-t border-line-soft px-6 py-6 first:border-t-0">
    <div>
      <h3 class="text-sm font-semibold text-ink">{{ title }}</h3>
      <p v-if="description" class="mt-1 text-xs text-ink-muted">{{ description }}</p>
    </div>
    <div class="min-w-0"><slot /></div>
  </section>
</template>
