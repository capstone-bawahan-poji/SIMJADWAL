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
      class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"
    >
      <slot name="leading" />
    </span>

    <input
      ref="input"
      v-model="model"
      v-bind="$attrs"
      :aria-invalid="invalid || undefined"
      class="block h-10 w-full rounded-lg border bg-white text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary read-only:bg-slate-50"
      :class="[
        invalid ? 'border-red-400 focus:ring-red-400 focus:border-red-400' : 'border-slate-300',
        $slots.leading ? 'pl-10' : 'pl-3.5',
        $slots.trailing ? 'pr-11' : 'pr-3.5',
      ]"
    />

    <span v-if="$slots.trailing" class="absolute inset-y-0 right-0 flex items-center pr-2">
      <slot name="trailing" />
    </span>
  </div>
</template>
