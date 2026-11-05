<script setup lang="ts">
withDefaults(defineProps<{ lines?: number }>(), { lines: 3 });
</script>

<template>
  <div class="animate-pulse space-y-3" aria-busy="true" aria-label="Memuat">
    <div v-for="n in lines" :key="n" class="h-4 rounded-badge bg-line-soft" :style="{ width: `${[40, 72, 56, 64][n % 4]}%` }" />
  </div>
</template>
