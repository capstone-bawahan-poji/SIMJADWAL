<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const label = computed(() => usePage().props.labels.sample_data);
</script>

<template>
    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
        {{ label }}
    </span>
</template>
