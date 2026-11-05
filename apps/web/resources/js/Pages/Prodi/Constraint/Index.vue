<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import PreferenceMatrix from '@/Components/constraint/PreferenceMatrix.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Panel from '@/Components/ui/Panel.vue';
import { fetchAll, useCollection } from '@/composables/useApiList';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { api, toApiError } from '@/lib/api';
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

function updateMatrix(value: Map<number, PreferenceType>): void {
    matrix.value = value;
    dirty.value = true;
    flash.value = null;
}

const counts = computed(() => {
    let want = 0;
    let avoid = 0;

    for (const type of matrix.value.values()) {
        if (type === 'want') want++;
        else if (type === 'avoid') avoid++;
    }

    return { want, avoid };
});

const footerHint = computed(() => {
    if (dirty.value) return 'Ada perubahan yang belum disimpan.';

    return locked.value ? '' : 'Klik sel untuk mengubah: kosong → diinginkan → dihindari.';
});

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
        <div class="w-full space-y-6 p-6 md:p-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Constraint &amp; Preferensi</h1>
                    <p class="mt-1 text-sm text-gray-500">Tandai slot yang diinginkan atau dihindari tiap dosen. Slot kosong dianggap netral.</p>
                </div>
                <Link
                    :href="route('prodi.constraints.review')"
                    class="inline-flex w-fit items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                >
                    Review &amp; Submit
                </Link>
            </div>

            <ProgramLockBanner :submission="submission" />
            <Alert v-if="flash" variant="success" :title="flash" dismissible @dismiss="flash = null" />
            <Alert v-if="loadError || saveError" :title="loadError ?? saveError ?? ''" />

            <Panel>
                <div class="flex flex-col gap-4 border-b border-gray-200 p-5 md:flex-row md:items-center md:justify-between">
                    <label class="flex items-center gap-3 text-sm font-medium text-gray-700">
                        Dosen
                        <select
                            v-model="lecturerId"
                            class="min-w-64 rounded-lg border-gray-200 text-sm focus:border-primary focus:ring-primary"
                        >
                            <option v-if="lecturers.length === 0" :value="null">Belum ada dosen</option>
                            <option v-for="lecturer in lecturers" :key="lecturer.id" :value="lecturer.id">{{ lecturer.name }}</option>
                        </select>
                    </label>
                    <p class="text-sm text-gray-600" aria-live="polite">
                        <span class="font-semibold tabular-nums text-emerald-700">{{ counts.want }}</span> diinginkan ·
                        <span class="font-semibold tabular-nums text-red-600">{{ counts.avoid }}</span> dihindari
                        <span class="text-gray-400">dari {{ timeSlots.length }} slot</span>
                    </p>
                </div>

                <PreferenceMatrix
                    :time-slots="timeSlots"
                    :model-value="matrix"
                    :disabled="locked || lecturerId === null"
                    @update:model-value="updateMatrix"
                />

                <div class="flex items-center justify-between gap-4 border-t border-gray-200 bg-gray-50 px-5 py-3">
                    <p class="text-sm text-gray-500">{{ footerHint }}</p>
                    <Button type="button" :loading="saving" :disabled="locked || !dirty" @click="save">Simpan Preferensi</Button>
                </div>
            </Panel>
        </div>
    </AppLayout>
</template>
