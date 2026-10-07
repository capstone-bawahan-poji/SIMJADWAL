<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { fetchAll, useCollection } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { api, toApiError } from '@/lib/api';
import { formatTime } from '@/lib/labels';
import type { Collection, Lecturer, PreferenceType, SlotPreference, TimeSlot } from '@/types/models';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);
const { submission, locked } = useOwnSubmission();
const { items: timeSlots } = useCollection<TimeSlot>('time-slots');

const lecturers = ref<Lecturer[]>([]);
const lecturerId = ref<number | null>(null);
const matrix = ref<Map<number, PreferenceType>>(new Map());
const loadError = ref<string | null>(null);
const saveError = ref<string | null>(null);
const flash = ref<string | null>(null);
const saving = ref(false);
const dirty = ref(false);

const days = computed(() => {
    const byDay = new Map<number, string>();
    timeSlots.value.forEach((slot) => byDay.set(slot.day, slot.day_label));

    return [...byDay.entries()].sort(([a], [b]) => a - b).map(([day, label]) => ({ day, label }));
});

const sessions = computed(() => {
    const bySession = new Map<number, { start: string; end: string }>();
    timeSlots.value.forEach((slot) => bySession.set(slot.session, { start: slot.start_time, end: slot.end_time }));

    return [...bySession.entries()].sort(([a], [b]) => a - b).map(([session, time]) => ({ session, ...time }));
});

function slotAt(day: number, session: number): TimeSlot | undefined {
    return timeSlots.value.find((slot) => slot.day === day && slot.session === session);
}

onMounted(async () => {
    try {
        lecturers.value = await fetchAll<Lecturer>('lecturers', { study_program_id: studyProgramId.value });
        lecturerId.value = lecturers.value[0]?.id ?? null;
    } catch (e) {
        loadError.value = toApiError(e).message;
    }
});

watch(lecturerId, async (id) => {
    matrix.value = new Map();
    dirty.value = false;
    flash.value = null;

    if (id === null) return;

    try {
        const response = await api.get<{ data: { preferences: SlotPreference[] } }>(`lecturers/${id}/preferences`);
        matrix.value = new Map(response.data.data.preferences.map((p) => [p.time_slot_id, p.type]));
        loadError.value = null;
    } catch (e) {
        loadError.value = toApiError(e).message;
    }
});

const NEXT: Record<string, PreferenceType | undefined> = { neutral: 'want', want: 'avoid', avoid: undefined };
const TONE: Record<string, string> = {
    want: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    avoid: 'bg-red-50 text-red-700 border-red-200',
    neutral: 'bg-gray-50 text-gray-500 border-gray-200',
};
const LABEL: Record<string, string> = { want: 'Diinginkan', avoid: 'Dihindari', neutral: 'Netral' };

function stateOf(slot: TimeSlot): string {
    return matrix.value.get(slot.id) ?? 'neutral';
}

function cycle(slot: TimeSlot): void {
    if (locked.value) return;

    const next = NEXT[stateOf(slot)];
    const copy = new Map(matrix.value);

    if (next) copy.set(slot.id, next);
    else copy.delete(slot.id);

    matrix.value = copy;
    dirty.value = true;
    flash.value = null;
}

async function save(): Promise<void> {
    if (lecturerId.value === null) return;

    saving.value = true;
    saveError.value = null;

    try {
        await api.put(`lecturers/${lecturerId.value}/preferences`, {
            preferences: [...matrix.value.entries()].map(([time_slot_id, type]) => ({ time_slot_id, type })),
        });
        dirty.value = false;
        flash.value = 'Preferensi dosen disimpan.';
    } catch (e) {
        saveError.value = toApiError(e).message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Constraint & Preferensi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Constraint &amp; Preferensi</h1>
                    <p class="mt-1 text-sm text-gray-500">Atur slot waktu yang diinginkan atau dihindari tiap dosen. Slot kosong berarti netral.</p>
                </div>
                <Link :href="route('prodi.constraints.review')" class="inline-flex w-fit items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                    Review &amp; Submit
                </Link>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="loadError || saveError" :title="loadError ?? saveError ?? ''" />

            <div class="flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                <label class="flex items-center gap-3 text-xs font-semibold text-gray-600">
                    Dosen
                    <select v-model="lecturerId" class="min-w-64 rounded-xl border-gray-200 bg-gray-50 text-xs focus:border-primary focus:ring-primary">
                        <option v-if="lecturers.length === 0" :value="null">Belum ada dosen</option>
                        <option v-for="lecturer in lecturers" :key="lecturer.id" :value="lecturer.id">{{ lecturer.name }}</option>
                    </select>
                </label>
                <div class="flex flex-wrap gap-4 text-[11px] font-semibold text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Diinginkan</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Dihindari</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>Netral</span>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-bold text-gray-900">Matriks Ketersediaan Waktu Mengajar</h2>
                    <span class="text-[11px] text-gray-400">Klik sel: netral → diinginkan → dihindari</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-center text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3 text-left">Sesi &amp; Waktu</th>
                                <th v-for="day in days" :key="day.day" class="px-4 py-3">{{ day.label }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="session in sessions" :key="session.session">
                                <td class="px-4 py-3 text-left">
                                    <div class="font-bold text-gray-900">Sesi {{ session.session }}</div>
                                    <div class="text-[11px] text-gray-400">{{ formatTime(session.start) }} - {{ formatTime(session.end) }}</div>
                                </td>
                                <td v-for="day in days" :key="day.day" class="p-2">
                                    <button
                                        v-if="slotAt(day.day, session.session)"
                                        type="button"
                                        class="w-full rounded-lg border px-2 py-2 font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                        :class="TONE[stateOf(slotAt(day.day, session.session)!)]"
                                        :disabled="locked || lecturerId === null"
                                        @click="cycle(slotAt(day.day, session.session)!)"
                                    >
                                        {{ LABEL[stateOf(slotAt(day.day, session.session)!)] }}
                                    </button>
                                    <span v-else class="text-gray-300">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end">
                <Button type="button" :loading="saving" :disabled="locked || !dirty" @click="save">Simpan Preferensi</Button>
            </div>
        </div>
    </AppLayout>
</template>
