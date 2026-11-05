<script setup lang="ts">
defineProps<{
    title: string;
    value: string | number;
    unit?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-white p-5">
        <div>
            <div class="mb-3 flex items-center justify-between gap-2">
                <span class="text-sm text-gray-500">{{ title }}</span>
                <slot name="icon" />
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-semibold tabular-nums text-gray-900">{{ value }}</span>
                <span v-if="unit" class="text-sm text-gray-500">{{ unit }}</span>
                <slot name="value-suffix" />
            </div>
        </div>
        <div v-if="description || $slots.footer" class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 text-sm text-gray-600">
            <slot name="footer">
                {{ description }}
            </slot>
        </div>
    </div>
</template>
