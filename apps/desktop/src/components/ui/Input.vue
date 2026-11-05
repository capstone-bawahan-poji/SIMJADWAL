<script setup lang="ts">
import { onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ invalid?: boolean }>();

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
  if (input.value?.hasAttribute('autofocus')) {
    input.value.focus();
  }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<!-- Text input with optional leading/trailing icon slots -->
<template>
  <div class="relative">
    <span
      v-if="$slots.leading"
      class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-muted"
    >
      <slot name="leading" />
    </span>

    <input
      ref="input"
      v-model="model"
      v-bind="$attrs"
      :aria-invalid="invalid || undefined"
      class="block h-10 w-full rounded-control border bg-white text-sm text-ink placeholder:text-ink-faint transition-colors focus:outline-none focus:ring-2 read-only:bg-canvas"
      :class="[
        invalid ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line-strong focus:border-primary focus:ring-primary/20',
        $slots.leading ? 'pl-10' : 'pl-3',
        $slots.trailing ? 'pr-12' : 'pr-3',
      ]"
    />

    <span v-if="$slots.trailing" class="absolute inset-y-0 right-0 flex items-center pr-2">
      <slot name="trailing" />
    </span>
  </div>
</template>
