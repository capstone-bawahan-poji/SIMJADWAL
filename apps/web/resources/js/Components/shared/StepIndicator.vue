<script setup lang="ts">
import { Check } from 'lucide-vue-next';

export interface Step {
    title: string;
    description?: string;
}

/** `current` is 1-based. Steps before it show a check; pass steps.length + 1 to mark all done. */
defineProps<{
    steps: Step[];
    current: number;
}>();
</script>

<template>
    <ol class="flex items-center gap-3">
        <template v-for="(step, i) in steps" :key="step.title">
            <li
                v-if="i > 0"
                aria-hidden="true"
                class="h-0.5 min-w-8 flex-1 rounded-full"
                :class="i < current ? 'bg-primary/40' : 'bg-slate-200'"
            />
            <li class="flex items-center gap-2" :aria-current="i + 1 === current ? 'step' : undefined">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                    :class="
                        i + 1 <= current
                            ? 'bg-primary text-primary-foreground ring-4 ring-primary/10'
                            : 'border border-slate-200 bg-slate-50 text-slate-500'
                    "
                >
                    <Check v-if="i + 1 < current" class="size-3.5" aria-hidden="true" />
                    <template v-else>{{ i + 1 }}</template>
                </span>
                <span class="text-left leading-tight">
                    <span
                        class="block text-sm"
                        :class="i + 1 <= current ? 'font-semibold text-slate-900' : 'text-slate-500'"
                    >
                        {{ step.title }}
                    </span>
                    <span v-if="step.description" class="block text-[11px] text-slate-400">
                        {{ step.description }}
                    </span>
                </span>
            </li>
        </template>
    </ol>
</template>
