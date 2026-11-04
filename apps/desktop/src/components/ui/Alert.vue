<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'error' | 'success' | 'warning' | 'info';
    title: string;
    dismissible?: boolean;
  }>(),
  { variant: 'error', dismissible: false },
);

defineEmits<{ dismiss: [] }>();

const styles = computed(() => ({
  error:   { box: 'border-danger-border bg-danger-bg',   title: 'text-danger-ink',  icon: CircleAlert, iconClass: 'text-danger'      },
  success: { box: 'border-success-border bg-success-bg', title: 'text-success-ink', icon: CircleCheck, iconClass: 'text-success-dot' },
  warning: { box: 'border-warning-border bg-warning-bg', title: 'text-warning-ink', icon: CircleAlert, iconClass: 'text-warning-dot' },
  info:    { box: 'border-primary-tint bg-primary-soft', title: 'text-primary',     icon: Info,        iconClass: 'text-primary'     },
})[props.variant]);
</script>

<template>
  <div
    :role="variant === 'error' ? 'alert' : 'status'"
    class="flex gap-3 rounded-control border px-4 py-3"
    :class="styles.box"
  >
    <component :is="styles.icon" class="mt-0.5 size-4 shrink-0" :class="styles.iconClass" aria-hidden="true" />

    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold" :class="styles.title">{{ title }}</p>
      <div v-if="$slots.default" class="mt-1 text-sm text-ink-secondary">
        <slot />
      </div>
    </div>

    <button
      v-if="dismissible"
      type="button"
      class="-m-1 h-fit rounded-badge p-1 text-ink-muted hover:text-ink-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      aria-label="Tutup"
      @click="$emit('dismiss')"
    >
      <X class="size-4" aria-hidden="true" />
    </button>
  </div>
</template>
