<script setup lang="ts">
import logoFull from '@/../images/Logo_1-transparent.png';
import logoMark from '@/../images/Logo_2-transparent.png';

withDefaults(defineProps<{ variant?: 'mark' | 'full' }>(), { variant: 'mark' });
</script>

<!-- Size it from the caller with a height class, e.g. class="h-14". -->
<template>
    <img :src="variant === 'full' ? logoFull : logoMark" alt="SIMJADWAL" class="w-auto" />
</template>
