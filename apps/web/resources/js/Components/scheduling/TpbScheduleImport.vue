<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import type { TpbImportResult } from '@/types/models';
import { FileSpreadsheet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Upload of the TPB schedule file, checked by the server first (dry run), saved on confirm.
 *   - target tpb: the TPB admin enters the TPB schedule as a run (POST tpb-schedule/import),
 *   - target faculty: the faculty keeps its own record of what TPB holds (POST tpb-occupancy/import).
 */
const props = defineProps<{ target: 'tpb' | 'faculty'; period: string; disabled?: boolean }>();
const emit = defineEmits<{ imported: [result: TpbImportResult] }>();

const endpoint = computed(() => (props.target === 'tpb' ? 'tpb-schedule/import' : 'tpb-occupancy/import'));
const file = ref<File | null>(null);
const preview = ref<TpbImportResult | null>(null);
const busy = ref<'preview' | 'save' | null>(null);
const failure = ref<ApiError | null>(null);
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

const columns = computed(() => (props.target === 'tpb'
    ? 'Kode MK, Kelas, Hari, Sesi, dan Ruang wajib diisi. Nama Mata Kuliah, SKS, Jumlah Peserta, dan Dosen boleh diisi untuk catatan.'
    : 'Hari, Sesi, dan Ruang wajib diisi. Kode MK, Nama Mata Kuliah, Kelas, SKS, Jumlah Peserta, dan Dosen boleh diisi.'));
const readyToSave = computed(() => preview.value !== null && preview.value.errors.length === 0 && !busy.value);

async function send(dryRun: boolean): Promise<TpbImportResult | null> {
    if (!file.value) return null;
    const body = new FormData();
    body.append('period', props.period);
    body.append('file', file.value);
    body.append('dry_run', dryRun ? '1' : '0');
    try {
        const response = await api.post<{ data: TpbImportResult }>(endpoint.value, body);

        return response.data.data;
    } catch (error) {
        const apiError = toApiError(error);
        // A file with row errors comes back as 422 with the result in data.
        const data = (error as { response?: { data?: { data?: TpbImportResult } } }).response?.data?.data;
        if (data) return data;
        failure.value = { ...apiError, message: fieldErrors(apiError).file ?? apiError.message };

        return null;
    }
}

async function choose(next: File | null): Promise<void> {
    failure.value = null;
    preview.value = null;
    if (!next) return;
    if (!/\.xlsx$/i.test(next.name)) {
        failure.value = { status: 0, code: 'CLIENT_ERROR', errors: {}, message: 'Pilih file Excel dengan format .xlsx.' };
        return;
    }
    file.value = next;
    busy.value = 'preview';
    preview.value = await send(true);
    busy.value = null;
}

async function save(): Promise<void> {
    busy.value = 'save';
    failure.value = null;
    const result = await send(false);
    busy.value = null;
    if (result?.committed) {
        emit('imported', result);
        file.value = null;
        preview.value = null;
        if (input.value) input.value.value = '';
    } else if (result) {
        preview.value = result;
    }
}

function drop(event: DragEvent): void {
    dragging.value = false;
    void choose(event.dataTransfer?.files?.[0] ?? null);
}

watch(() => props.period, () => {
    file.value = null;
    preview.value = null;
});
</script>

<template>
    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Unggah file jadwal TPB</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-600">Satu baris untuk satu pertemuan kelas. {{ columns }}</p>
            </div>
            <a href="/api/internal/tpb-schedule/import/template" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <FileSpreadsheet class="size-4" aria-hidden="true" />
                Unduh template
            </a>
        </div>

        <label
            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors"
            :class="[dragging ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-primary', disabled ? 'pointer-events-none opacity-60' : '']"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop"
        >
            <input ref="input" type="file" accept=".xlsx" class="sr-only" :disabled="disabled || busy !== null" @change="void choose(($event.target as HTMLInputElement).files?.[0] ?? null)" />
            <FileSpreadsheet class="size-7 text-primary" aria-hidden="true" />
            <span class="mt-2 max-w-full truncate text-sm font-semibold text-gray-900">{{ busy === 'preview' ? 'Memeriksa file…' : file ? file.name : 'Pilih atau seret file Excel jadwal TPB' }}</span>
            <span class="mt-0.5 text-xs text-gray-500">Format .xlsx. File diperiksa server sebelum disimpan.</span>
        </label>

        <Alert v-if="failure" :title="failure.message" />

        <template v-if="preview">
            <Alert v-if="preview.errors.length" :title="`${preview.errors.length} baris perlu diperbaiki`">Perbaiki file lalu unggah ulang. Tidak ada data yang disimpan.</Alert>
            <Alert v-else variant="success" :title="target === 'tpb' ? `${preview.meetings ?? 0} pertemuan siap disimpan` : `${preview.rows ?? 0} baris siap disimpan`">
                <template v-if="target === 'tpb'">Hasilnya menjadi run TPB baru yang belum aktif. Aktifkan dan publikasikan dari halaman Proses GA.</template>
                <template v-else>Import ini menggantikan import sebelumnya untuk periode yang sama.</template>
            </Alert>
            <div v-if="preview.errors.length || preview.warnings.length" class="max-h-72 overflow-y-auto rounded-lg border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                        <tr><th class="w-20 px-4 py-2 font-medium">Baris</th><th class="px-4 py-2 font-medium">Keterangan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(item, index) in preview.errors" :key="`e${index}`">
                            <td class="px-4 py-2 tabular-nums text-red-700">{{ item.row || 'File' }}</td>
                            <td class="px-4 py-2 text-red-700">{{ item.message }}</td>
                        </tr>
                        <tr v-for="(item, index) in preview.warnings" :key="`w${index}`">
                            <td class="px-4 py-2 tabular-nums text-amber-700">{{ item.row || 'File' }}</td>
                            <td class="px-4 py-2 text-amber-800">{{ item.message }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end">
                <Button :disabled="!readyToSave" :loading="busy === 'save'" @click="save">Simpan jadwal TPB</Button>
            </div>
        </template>
    </section>
</template>
