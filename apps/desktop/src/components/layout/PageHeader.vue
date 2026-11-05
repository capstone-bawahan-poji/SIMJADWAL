<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<!-- Title row. Page actions sit on the right, primary action last. -->
<template>
  <header class="flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
      <h1 class="font-serif text-xl font-semibold text-ink">{{ title }}</h1>
      <p v-if="description || $slots.description" class="mt-1 text-sm text-ink-muted">
        <slot name="description">{{ description }}</slot>
      </p>
    </div>
    <div v-if="$slots.actions" class="flex items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
