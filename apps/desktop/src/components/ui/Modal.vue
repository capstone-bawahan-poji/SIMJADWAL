<script setup lang="ts">
withDefaults(
  defineProps<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
  }>(),
  { maxWidth: 'md' },
);

defineEmits<{ close: [] }>();

const maxWidthClass: Record<string, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
};
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4"
        @click.self="$emit('close')"
        @keydown.esc="$emit('close')"
      >
        <div
          role="dialog"
          aria-modal="true"
          class="w-full rounded-panel border border-line bg-white shadow-[0_24px_48px_-12px_rgba(16,24,40,0.18)]"
          :class="maxWidthClass[maxWidth]"
        >
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
