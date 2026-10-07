<script setup lang="ts">
defineProps<{
    label: string;
    value: string | number;
}>();
</script>

<template>
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">{{ label }}</span>
        <div class="mt-1 text-3xl font-bold text-gray-900">{{ value }}</div>
    </div>
</template>
