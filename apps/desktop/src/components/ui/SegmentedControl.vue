<script setup lang="ts" generic="T extends string">
defineProps<{ options: { value: T; label: string; count?: number }[]; label?: string }>();
const model = defineModel<T>({ required: true });
</script>

<!-- Mutually exclusive filter or mode switch. 32px tall. -->
<template>
  <div class="inline-flex gap-1 rounded-control bg-canvas p-1" role="radiogroup" :aria-label="label">
    <button
      v-for="o in options"
      :key="o.value"
      type="button"
      role="radio"
      :aria-checked="model === o.value"
      class="inline-flex h-8 items-center gap-2 whitespace-nowrap rounded-[6px] px-3 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      :class="model === o.value ? 'bg-white text-ink shadow-[0_1px_2px_rgba(16,24,40,0.08)]' : 'text-ink-muted hover:text-ink-secondary'"
      @click="model = o.value"
    >
      {{ o.label }}
      <span v-if="o.count !== undefined" class="tabular" :class="model === o.value ? 'text-ink-muted' : 'text-ink-faint'">{{ o.count }}</span>
    </button>
  </div>
</template>
