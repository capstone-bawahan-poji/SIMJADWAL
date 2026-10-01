<script setup lang="ts">
defineProps<{
    title: string;
    value: string | number;
    unit?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-all">
        <div>
            <div class="flex items-center justify-between gap-2 mb-4">
                <span class="text-xs uppercase tracking-wider text-gray-500 font-semibold">{{ title }}</span>
                <slot name="icon" />
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-4xl font-bold text-gray-900 tracking-tight">{{ value }}</span>
                <span v-if="unit" class="text-sm text-gray-500 font-medium">{{ unit }}</span>
                <slot name="value-suffix" />
            </div>
        </div>
        <div v-if="description || $slots.footer" class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-2 text-gray-600 text-sm">
            <slot name="footer">
                {{ description }}
            </slot>
        </div>
    </div>
</template>
