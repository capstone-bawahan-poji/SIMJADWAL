<script setup lang="ts" generic="T extends string">
defineProps<{ tabs: { key: T; label: string }[] }>();
const model = defineModel<T>({ required: true });
</script>

<!-- Underline tabs for switching views inside a panel. -->
<template>
  <div class="flex gap-6 border-b border-line" role="tablist">
    <button
      v-for="t in tabs"
      :key="t.key"
      type="button"
      role="tab"
      :aria-selected="model === t.key"
      class="-mb-px h-12 border-b-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      :class="model === t.key ? 'border-primary text-primary' : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      @click="model = t.key"
    >
      {{ t.label }}
    </button>
  </div>
</template>
