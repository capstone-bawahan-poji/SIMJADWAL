<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import PreferenceMatrix from '@/Components/constraint/PreferenceMatrix.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { fetchAll, useCollection } from '@/composables/useApiList';
import { api, toApiError } from '@/lib/api';
import type { Lecturer, PreferenceType, SlotPreference, TimeSlot } from '@/types/models';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface LecturerPreferenceState {
    preferences: Map<number, PreferenceType>;
    loading: boolean;
    saving: boolean;
    dirty: boolean;
    error: string | null;
    flash: string | null;
}

const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);
const { items: timeSlots, error: timeSlotError } = useCollection<TimeSlot>('time-slots');
const lecturers = ref<Lecturer[]>([]);
const search = ref('');
const states = ref<Record<number, LecturerPreferenceState>>({});
const loadError = ref<string | null>(null);
const loadingLecturers = ref(false);

const visibleLecturers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('id');

    return lecturers.value.filter((lecturer) =>
        !query || `${lecturer.name} ${lecturer.nip}`.toLocaleLowerCase('id').includes(query),
    );
});

function stateFor(lecturerId: number): LecturerPreferenceState {
    return states.value[lecturerId];
}

function countsFor(lecturerId: number) {
    const preferences = stateFor(lecturerId)?.preferences ?? new Map<number, PreferenceType>();
    let want = 0;
    let avoid = 0;

    for (const type of preferences.values()) {
        if (type === 'want') want++;
        else if (type === 'avoid') avoid++;
    }

    return { want, avoid, neutral: Math.max(0, timeSlots.value.length - want - avoid) };
}

const totals = computed(() => {
    let want = 0;
    let avoid = 0;
    let filledLecturers = 0;

    for (const lecturer of lecturers.value) {
        const state = states.value[lecturer.id];
        if (!state || state.loading || state.error) continue;

        const counts = countsFor(lecturer.id);
        want += counts.want;
        avoid += counts.avoid;
        if (counts.want + counts.avoid > 0) filledLecturers++;
    }

    return { want, avoid, filledLecturers };
});

async function loadPreferences(lecturer: Lecturer): Promise<void> {
    const state = stateFor(lecturer.id);
    state.loading = true;
    state.error = null;

    try {
        const response = await api.get<{ data: { preferences: SlotPreference[] } }>(`lecturers/${lecturer.id}/preferences`);
        state.preferences = new Map(response.data.data.preferences.map((preference) => [preference.time_slot_id, preference.type]));
    } catch (error) {
        state.error = toApiError(error).message;
    } finally {
        state.loading = false;
    }
}

async function loadLecturers(): Promise<void> {
    loadingLecturers.value = true;
    loadError.value = null;

    try {
        lecturers.value = await fetchAll<Lecturer>('lecturers', { study_program_id: studyProgramId.value });
        states.value = Object.fromEntries(lecturers.value.map((lecturer) => [
            lecturer.id,
            {
                preferences: new Map<number, PreferenceType>(),
                loading: false,
                saving: false,
                dirty: false,
                error: null,
                flash: null,
            },
        ]));
        await Promise.all(lecturers.value.map(loadPreferences));
    } catch (error) {
        loadError.value = toApiError(error).message;
    } finally {
        loadingLecturers.value = false;
    }
}

function updatePreferences(lecturerId: number, preferences: Map<number, PreferenceType>): void {
    const state = stateFor(lecturerId);
    state.preferences = preferences;
    state.dirty = true;
    state.flash = null;
}

async function save(lecturerId: number): Promise<void> {
    const state = stateFor(lecturerId);
    if (!state || state.loading || state.saving) return;

    state.saving = true;
    state.error = null;
    state.flash = null;

    try {
        await api.put(`lecturers/${lecturerId}/preferences`, {
            preferences: [...state.preferences.entries()].map(([time_slot_id, type]) => ({ time_slot_id, type })),
        });
        state.dirty = false;
        state.flash = 'Preferensi berhasil disimpan.';
    } catch (error) {
        state.error = toApiError(error).message;
    } finally {
        state.saving = false;
    }
}

onMounted(loadLecturers);
</script>

<template>
    <Head title="Constraint & Preferensi" />

    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Constraint &amp; Preferensi</h1>
                    <p class="mt-1 text-sm text-gray-600">Atur preferensi waktu setiap dosen program studi.</p>
                </div>
            </header>

            <Alert v-if="loadError" :title="loadError" />
            <Alert v-if="timeSlotError" :title="timeSlotError.message" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Dosen dengan Preferensi" :value="`${totals.filledLecturers} / ${lecturers.length}`" />
                <StatCard label="Slot Dihindari" :value="totals.avoid" />
            </div>

            <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                <section class="space-y-4" aria-label="Preferensi dosen">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama atau NIP dosen..."
                        aria-label="Cari nama atau NIP dosen"
                        class="w-full rounded-xl border-gray-200 bg-white text-sm placeholder-gray-400 focus:border-primary focus:ring-primary md:w-80"
                    />

                    <p v-if="loadingLecturers" class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">Memuat daftar dosen dan preferensi…</p>
                    <p v-else-if="visibleLecturers.length === 0" class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500">
                        {{ lecturers.length === 0 ? 'Belum ada dosen di program studi ini.' : 'Tidak ada dosen yang cocok dengan pencarian.' }}
                    </p>

                    <Card v-for="lecturer in visibleLecturers" :key="lecturer.id" class="!p-0 border border-gray-100 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 p-5">
                            <div>
                                <h2 class="text-base font-bold text-gray-900">{{ lecturer.name }}</h2>
                                <p class="mt-1 text-xs text-gray-500">{{ lecturer.nip }}<template v-if="lecturer.title"> · {{ lecturer.title }}</template></p>
                                <p class="mt-2 text-sm text-gray-600">
                                    <strong class="font-semibold tabular-nums text-gray-900">{{ countsFor(lecturer.id).want }}</strong> diinginkan
                                    <span class="mx-1.5 text-gray-300">·</span>
                                    <strong class="font-semibold tabular-nums text-gray-900">{{ countsFor(lecturer.id).avoid }}</strong> dihindari
                                </p>
                            </div>
                            <Button
                                type="button"
                                :loading="stateFor(lecturer.id).saving"
                                :disabled="!stateFor(lecturer.id).dirty || stateFor(lecturer.id).loading"
                                @click="save(lecturer.id)"
                            >Simpan Preferensi</Button>
                        </div>

                        <Alert v-if="stateFor(lecturer.id).error" :title="stateFor(lecturer.id).error ?? ''" class="m-5" />
                        <Alert v-if="stateFor(lecturer.id).flash" variant="success" :title="stateFor(lecturer.id).flash ?? ''" class="m-5" />

                        <div v-if="stateFor(lecturer.id).loading" class="p-8 text-center text-sm text-gray-500">Memuat preferensi…</div>
                        <div v-else class="p-3 sm:p-5">
                            <PreferenceMatrix
                                :time-slots="timeSlots"
                                :model-value="stateFor(lecturer.id).preferences"
                                :disabled="stateFor(lecturer.id).saving"
                                @update:model-value="updatePreferences(lecturer.id, $event)"
                            />
                        </div>
                    </Card>
                </section>

                <Card class="h-fit !p-0 border border-gray-100 shadow-sm">
                    <div class="border-b border-gray-100 p-5">
                        <h2 class="text-sm font-bold text-gray-900">Ringkasan Preferensi Dosen</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="lecturer in lecturers" :key="lecturer.id" class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-gray-900">{{ lecturer.name }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">
                                    <span>{{ countsFor(lecturer.id).want }} diinginkan</span>
                                    <span class="mx-1 text-gray-300">·</span>
                                    <span>{{ countsFor(lecturer.id).avoid }} dihindari</span>
                                </p>
                            </div>
                            <span v-if="countsFor(lecturer.id).want + countsFor(lecturer.id).avoid === 0" class="shrink-0 text-[10px] text-gray-400">
                                Belum diisi
                            </span>
                        </li>
                        <li v-if="lecturers.length === 0" class="px-5 py-6 text-center text-xs text-gray-500">Belum ada dosen.</li>
                    </ul>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
