<script setup lang="ts">
defineProps<{ noPadding?: boolean }>();
</script>

<!-- Panel: white surface, 12px radius, 1px border, no shadow. -->
<template>
  <div class="rounded-panel border border-line bg-white" :class="noPadding ? '' : 'p-6'">
    <slot />
  </div>
</template>
