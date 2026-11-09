<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    description: string;
    items: { label: string; value: number }[];
    unit: string;
}>();

const maximum = computed(() => Math.max(0, ...props.items.map((item) => item.value)));
const format = (value: number) => value.toLocaleString('id-ID');
</script>

<template>
    <section class="min-w-0 rounded-[20px] border border-[#DBE2EF] bg-white p-6" :aria-label="title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-bold text-[#112D4E]">{{ title }}</h2>
            <p class="text-xs text-[#112D4E]/70">{{ description }}</p>
        </div>

        <div class="mt-8 overflow-x-auto">
            <div class="flex min-w-[300px] gap-3">
                <div class="flex h-44 w-8 shrink-0 flex-col justify-between text-right text-xs tabular-nums text-[#112D4E]/70" aria-hidden="true">
                    <span>{{ format(maximum) }}</span>
                    <span>0</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="relative flex h-44 items-end gap-3 border-b border-[#DBE2EF]">
                        <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-[#DBE2EF]"></div>
                        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-[#DBE2EF]"></div>
                        <div
                            v-for="item in items"
                            :key="item.label"
                            class="relative flex h-full min-w-0 flex-1 items-end justify-center"
                            :aria-label="`${item.label}: ${format(item.value)} ${unit}`"
                            :title="`${format(item.value)} ${unit}`"
                        >
                            <div class="relative flex h-full w-14 max-w-full items-end overflow-hidden rounded-t-[14px] bg-[#DBE2EF]/60">
                                <div
                                    class="w-full rounded-t-[14px] bg-[#3F72AF]"
                                    :style="{ height: maximum > 0 ? `${item.value / maximum * 100}%` : '0%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3">
                        <div v-for="item in items" :key="item.label" class="min-w-0 flex-1 text-center">
                            <p class="text-sm font-bold tabular-nums text-[#112D4E]">{{ format(item.value) }}</p>
                            <p class="mt-1 break-words text-xs text-[#112D4E]/70">{{ item.label }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
