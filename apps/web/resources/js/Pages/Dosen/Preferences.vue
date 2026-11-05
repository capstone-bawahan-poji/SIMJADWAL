<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Panel from '@/Components/ui/Panel.vue';
import PreferenceMatrix from '@/Components/constraint/PreferenceMatrix.vue';
import { useCollection } from '@/composables/useApiList';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import type { PreferenceType, SlotPreference, TimeSlot } from '@/types/models';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface LecturerPreferences {
    lecturer_id: number;
    preferences: SlotPreference[];
}

const user = computed(() => usePage().props.auth.user);
const { items: timeSlots, loading: timeSlotsLoading, error: timeSlotsError } = useCollection<TimeSlot>('time-slots');
const matrix = ref<Map<number, PreferenceType>>(new Map());
const preferencesLoading = ref(true);
const saving = ref(false);
const dirty = ref(false);
const loadError = ref<ApiError | null>(null);
const saveError = ref<ApiError | null>(null);
const saved = ref(false);
let loadRequest = 0;

const counts = computed(() => {
    let want = 0;
    let avoid = 0;

    for (const preference of matrix.value.values()) {
        if (preference === 'want') want++;
        if (preference === 'avoid') avoid++;
    }

    return { want, avoid };
});

function updateMatrix(value: Map<number, PreferenceType>): void {
    matrix.value = value;
    dirty.value = true;
    saved.value = false;
    saveError.value = null;
}

function applyPreferences(data: LecturerPreferences): void {
    matrix.value = new Map(data.preferences.map((preference) => [preference.time_slot_id, preference.type]));
    dirty.value = false;
}

async function loadPreferences(): Promise<void> {
    const request = ++loadRequest;
    const lecturerId = user.value.lecturer_id;

    loadError.value = null;
    saved.value = false;

    if (!lecturerId) {
        matrix.value = new Map();
        preferencesLoading.value = false;
        return;
    }

    preferencesLoading.value = true;

    try {
        const response = await api.get<{ data: LecturerPreferences }>(`lecturers/${lecturerId}/preferences`);
        if (request === loadRequest) applyPreferences(response.data.data);
    } catch (error) {
        if (request === loadRequest) loadError.value = toApiError(error);
    } finally {
        if (request === loadRequest) preferencesLoading.value = false;
    }
}

async function save(): Promise<void> {
    const lecturerId = user.value.lecturer_id;
    if (!lecturerId || !dirty.value) return;

    saving.value = true;
    saveError.value = null;
    saved.value = false;

    try {
        const response = await api.put<{ data: LecturerPreferences }>(`lecturers/${lecturerId}/preferences`, {
            preferences: [...matrix.value.entries()].map(([time_slot_id, type]) => ({ time_slot_id, type })),
        });
        applyPreferences(response.data.data);
        saved.value = true;
    } catch (error) {
        saveError.value = toApiError(error);
    } finally {
        saving.value = false;
    }
}

onMounted(() => void loadPreferences());
onResourcesChanged(['lecturer_preferences'], () => void loadPreferences());
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
        <header>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Preferensi Jadwal</h1>
            <p class="mt-1 text-sm text-gray-500">Tandai hari dan sesi mengajar yang diinginkan atau dihindari.</p>
        </header>

        <Alert v-if="!user.lecturer_id" title="Akun dosen belum terhubung">
            Data dosen untuk akun ini belum tersedia. Hubungi administrator untuk menghubungkan akun.
        </Alert>
        <Alert
            v-if="loadError"
            :title="loadError.status === 403 ? 'API belum mengizinkan role Dosen membaca preferensi.' : loadError.message"
        >
            <template v-if="loadError.status === 403">
                Halaman ini sudah terhubung ke endpoint preferensi, tetapi policy API saat ini hanya mengizinkan Admin Prodi.
            </template>
            <template v-else>{{ loadError.message }}</template>
        </Alert>
        <Alert
            v-if="saveError"
            :title="saveError.status === 403 ? 'API belum mengizinkan role Dosen menyimpan preferensi.' : saveError.message"
        >
            <template v-if="saveError.status === 403">
                Perubahan tidak disimpan. Policy API saat ini hanya mengizinkan Admin Prodi.
            </template>
            <template v-else>{{ saveError.message }}</template>
        </Alert>
        <Alert v-if="timeSlotsError" :title="timeSlotsError.message" />
        <Alert v-if="saved" variant="success" title="Preferensi berhasil disimpan." />

        <Panel>
            <div class="flex flex-col gap-3 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">{{ user.name }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Pilihan kosong dianggap netral.</p>
                </div>
                <p class="text-sm text-gray-600" aria-live="polite">
                    <span class="font-semibold tabular-nums text-emerald-700">{{ counts.want }}</span> diinginkan ·
                    <span class="font-semibold tabular-nums text-red-600">{{ counts.avoid }}</span> dihindari
                    <span class="text-gray-400">dari {{ timeSlots.length }} slot</span>
                </p>
            </div>

            <div v-if="preferencesLoading || timeSlotsLoading" class="p-8 text-center text-sm text-gray-500">
                Memuat preferensi…
            </div>
            <PreferenceMatrix
                v-else
                :time-slots="timeSlots"
                :model-value="matrix"
                :disabled="!user.lecturer_id || Boolean(loadError) || Boolean(timeSlotsError)"
                @update:model-value="updateMatrix"
            />

            <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    Klik sel untuk mengubah: kosong → diinginkan → dihindari.
                </p>
                <Button type="button" :loading="saving" :disabled="!dirty || preferencesLoading || Boolean(loadError) || Boolean(timeSlotsError)" @click="save">
                    Simpan Preferensi
                </Button>
            </div>
        </Panel>
    </div>
</template>
