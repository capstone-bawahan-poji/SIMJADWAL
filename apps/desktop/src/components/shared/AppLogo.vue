<script setup lang="ts">
import logoFull from '@/assets/images/logo-full.png';
import logoMark from '@/assets/images/logo-mark.png';

withDefaults(defineProps<{ variant?: 'mark' | 'full' }>(), { variant: 'mark' });
</script>

<!-- Size via height class from caller, e.g. class="h-14" -->
<template>
  <img :src="variant === 'full' ? logoFull : logoMark" alt="SIMJADWAL" class="w-auto" />
</template>
