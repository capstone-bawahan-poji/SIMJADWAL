<script setup lang="ts">
defineOptions({ inheritAttrs: false });
defineProps<{ invalid?: boolean }>();
const model = defineModel<number>({ required: true });
</script>

<!-- Numeric field: right aligned, tabular mono digits. -->
<template>
  <input
    v-model.number="model"
    v-bind="$attrs"
    type="number"
    :aria-invalid="invalid || undefined"
    class="tabular block h-10 w-full rounded-control border bg-white px-3 text-right text-sm text-ink transition-colors focus:outline-none focus:ring-2"
    :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line-strong focus:border-primary focus:ring-primary/20'"
  />
</template>
