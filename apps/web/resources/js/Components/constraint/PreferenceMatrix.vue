<script setup lang="ts">
import { formatTime } from '@/lib/labels';
import type { PreferenceType, TimeSlot } from '@/types/models';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        timeSlots: TimeSlot[];
        modelValue: Map<number, PreferenceType>;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [value: Map<number, PreferenceType>] }>();

const days = computed(() => {
    const byDay = new Map<number, string>();
    props.timeSlots.forEach((slot) => byDay.set(slot.day, slot.day_label));

    return [...byDay.entries()].sort(([a], [b]) => a - b).map(([day, label]) => ({ day, label }));
});

const sessions = computed(() => {
    const bySession = new Map<number, { start: string; end: string }>();
    props.timeSlots.forEach((slot) => bySession.set(slot.session, { start: slot.start_time, end: slot.end_time }));

    return [...bySession.entries()].sort(([a], [b]) => a - b).map(([session, time]) => ({ session, ...time }));
});

const grid = computed(() =>
    sessions.value.map((session) => ({
        ...session,
        cells: days.value.map((day) => ({
            day,
            slot: props.timeSlots.find((slot) => slot.day === day.day && slot.session === session.session),
        })),
    })),
);

const FULL: Record<PreferenceType | 'neutral', string> = { want: 'Diinginkan', avoid: 'Dihindari', neutral: 'Netral' };
const CELL: Record<PreferenceType | 'neutral', string> = {
    want: 'border-blue-300 bg-blue-50 text-blue-900',
    avoid: 'border-gray-200 bg-gray-100 text-gray-600 line-through decoration-gray-400 hover:bg-gray-200',
    neutral: 'border-gray-100 bg-white text-gray-300 hover:border-gray-300 hover:bg-gray-50',
};
const NEXT: Record<PreferenceType | 'neutral', PreferenceType | undefined> = { neutral: 'want', want: 'avoid', avoid: undefined };

function stateOf(slot: TimeSlot): PreferenceType | 'neutral' {
    return props.modelValue.get(slot.id) ?? 'neutral';
}

function cyclePreference(slot: TimeSlot): void {
    if (props.disabled) return;

    const next = NEXT[stateOf(slot)];
    const updated = new Map(props.modelValue);

    if (next) updated.set(slot.id, next);
    else updated.delete(slot.id);

    emit('update:modelValue', updated);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-sm font-medium text-gray-600">
                    <th class="px-5 py-3 text-left">Sesi</th>
                    <th v-for="day in days" :key="day.day" class="px-2 py-3 text-center">{{ day.label }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="row in grid" :key="row.session">
                    <td class="whitespace-nowrap px-5 py-3 text-left">
                        <p class="font-semibold text-gray-900">Sesi {{ row.session }}</p>
                        <p class="text-xs tabular-nums text-gray-500">{{ formatTime(row.start) }} - {{ formatTime(row.end) }}</p>
                    </td>
                    <td v-for="cell in row.cells" :key="cell.day.day" class="p-1.5">
                        <button
                            v-if="cell.slot"
                            type="button"
                            class="flex h-11 w-full min-w-24 items-center justify-center rounded-lg border text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed"
                            :class="CELL[stateOf(cell.slot)]"
                            :disabled="disabled"
                            :aria-label="`${cell.day.label} sesi ${row.session}: ${FULL[stateOf(cell.slot)]}`"
                            @click="cyclePreference(cell.slot)"
                        >
                            {{ stateOf(cell.slot) === 'neutral' ? '' : FULL[stateOf(cell.slot)] }}
                        </button>
                        <span v-else class="block text-center text-gray-300">-</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
