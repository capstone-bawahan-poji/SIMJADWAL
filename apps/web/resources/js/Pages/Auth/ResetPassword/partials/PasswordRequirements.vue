<script setup lang="ts">
import { Check, Circle } from 'lucide-vue-next';
import { computed } from 'vue';

// Keep MIN_LENGTH in sync with Password::defaults() in AppServiceProvider.
const MIN_LENGTH = 8;

const props = defineProps<{
    password: string;
    confirmation: string;
}>();

const rules = computed(() => [
    { label: `Minimal ${MIN_LENGTH} karakter`, met: props.password.length >= MIN_LENGTH },
    { label: 'Konfirmasi sama dengan kata sandi baru', met: props.password !== '' && props.password === props.confirmation },
]);
</script>

<template>
    <ul class="space-y-1.5 text-xs" aria-live="polite">
        <li
            v-for="rule in rules"
            :key="rule.label"
            class="flex items-center gap-2"
            :class="rule.met ? 'text-green-700' : 'text-slate-500'"
        >
            <component :is="rule.met ? Check : Circle" class="size-3.5" aria-hidden="true" />
            {{ rule.label }}
        </li>
    </ul>
</template>
