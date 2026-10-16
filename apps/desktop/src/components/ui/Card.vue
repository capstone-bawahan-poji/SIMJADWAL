<script setup lang="ts">
defineProps<{
  rounded?: boolean;
  noPadding?: boolean;
}>();
</script>

<template>
  <div
    class="bg-white border border-gray-100 shadow-sm"
    :class="[
      rounded !== false ? 'rounded-2xl' : '',
      noPadding ? '' : 'p-6',
    ]"
  >
    <slot />
  </div>
</template>
