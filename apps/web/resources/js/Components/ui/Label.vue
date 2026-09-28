<script setup lang="ts">
defineProps<{ for?: string }>();
</script>

<template>
    <label :for="$props.for" class="block text-xs font-semibold uppercase tracking-wide text-slate-700">
        <slot />
    </label>
</template>
