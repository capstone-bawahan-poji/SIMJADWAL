<script setup lang="ts">
import Label from '@/components/ui/Label.vue';

defineProps<{
  id: string;
  label: string;
  hint?: string;
  error?: string;
}>();
</script>

<!--
  Label + control + hint/error. Put the control in the default slot with the same `id`.
  #label-action slot: sits right of the label (e.g. "Lupa Kata Sandi?").
-->
<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
      <Label :for="id">{{ label }}</Label>
      <slot name="label-action" />
    </div>

    <slot />

    <p v-if="error" :id="`${id}-error`" class="text-xs text-danger-ink">{{ error }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="text-xs text-ink-muted">{{ hint }}</p>
  </div>
</template>
