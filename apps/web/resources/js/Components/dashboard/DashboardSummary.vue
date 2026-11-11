<script setup lang="ts">
defineProps<{
    label: string;
    figures: { label: string; value: string | number; detail?: string }[];
}>();
</script>

<template>
    <section class="rounded-[20px] border border-[#DBE2EF] bg-white px-6 py-5" :aria-label="label">
        <dl class="grid grid-cols-2 gap-y-5" :class="figures.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-4'">
            <div
                v-for="(figure, index) in figures"
                :key="figure.label"
                :class="['min-w-0 px-3 first:pl-0', index % 2 ? 'border-l border-[#DBE2EF]' : 'pl-0', index > 0 ? 'sm:border-l sm:border-[#DBE2EF] sm:pl-3' : '']"
            >
                <dt class="text-sm text-[#112D4E]/70">{{ figure.label }}</dt>
                <dd class="mt-1 text-3xl font-bold tabular-nums tracking-tight text-[#112D4E]">{{ figure.value }}</dd>
                <dd v-if="figure.detail" class="mt-1 text-xs text-[#112D4E]/70">{{ figure.detail }}</dd>
            </div>
        </dl>
    </section>
</template>
