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
  error:   { box: 'border-red-200 bg-red-50',     title: 'text-red-800',    icon: CircleAlert, iconClass: 'text-red-600'    },
  success: { box: 'border-green-200 bg-green-50', title: 'text-green-800',  icon: CircleCheck, iconClass: 'text-green-600'  },
  warning: { box: 'border-amber-200 bg-amber-50', title: 'text-amber-800',  icon: CircleAlert, iconClass: 'text-amber-600'  },
  info:    { box: 'border-blue-200 bg-blue-50',   title: 'text-blue-800',   icon: Info,        iconClass: 'text-blue-600'   },
})[props.variant]);
</script>

<template>
  <div
    :role="variant === 'error' ? 'alert' : 'status'"
    class="flex gap-3 rounded-lg border p-3.5"
    :class="styles.box"
  >
    <component :is="styles.icon" class="mt-0.5 size-4 shrink-0" :class="styles.iconClass" aria-hidden="true" />

    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold" :class="styles.title">{{ title }}</p>
      <div v-if="$slots.default" class="mt-0.5 text-sm text-slate-600">
        <slot />
      </div>
    </div>

    <button
      v-if="dismissible"
      type="button"
      class="-m-1 h-fit rounded p-1 text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      aria-label="Tutup"
      @click="$emit('dismiss')"
    >
      <X class="size-4" aria-hidden="true" />
    </button>
  </div>
</template>
