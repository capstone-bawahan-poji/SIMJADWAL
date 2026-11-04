<script setup lang="ts" generic="T extends string | number | null">
import { ChevronDown } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'md' });
const model = defineModel<T>({ required: true });
</script>

<!-- Native select styled like Input. Width comes from the class passed by the caller. -->
<template>
  <div class="relative inline-flex" :class="$attrs.class as string">
    <select
      v-model="model"
      v-bind="{ ...$attrs, class: undefined }"
      class="w-full appearance-none rounded-control border border-line-strong bg-white pl-3 pr-10 text-ink transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-canvas disabled:text-ink-faint"
      :class="size === 'sm' ? 'h-8 text-xs' : 'h-10 text-sm'"
    >
      <slot />
    </select>
    <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
  </div>
</template>
