<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { api, fieldErrors, toApiError, type ApiError } from '@/lib/api';
import type { TpbImportResult } from '@/types/models';
import { ArrowRight, FileSpreadsheet, Info, TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ target: 'tpb' | 'faculty'; period: string; disabled?: boolean }>();
const emit = defineEmits<{ imported: [result: TpbImportResult] }>();

const endpoint = computed(() => (props.target === 'tpb' ? 'tpb-schedule/import' : 'tpb-occupancy/import'));
const file = ref<File | null>(null);
const preview = ref<TpbImportResult | null>(null);
const busy = ref<'preview' | 'save' | null>(null);
const failure = ref<ApiError | null>(null);
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

interface FormatGroup { title: string; hint: string; columns: string[]; required?: boolean }

const formatGroups = computed<FormatGroup[]>(() => (props.target === 'tpb'
    ? [
        { title: 'Data grup dan kelas', hint: 'Membuat grup dan kelas. Boleh dikosongkan jika grup dan kelas sudah ada di Mapping Dosen.', columns: ['Kode MK', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu'] },
        { title: 'Jadwal', hint: 'Membuat jadwal. Kosongkan semuanya jika hanya mengimpor data.', columns: ['Ruangan', 'Hari', 'Sesi', 'Pertemuan'] },
    ]
    : [
        { title: 'Wajib diisi', hint: 'Menentukan slot ruangan yang dipakai TPB.', columns: ['Ruangan', 'Hari', 'Sesi'], required: true },
        { title: 'Boleh diisi', hint: 'Dosen Pengampu dipakai untuk memeriksa bentrok dosen.', columns: ['Kode MK', 'Mata Kuliah', 'Kelas', 'SKS', 'Kapasitas', 'Dosen Pengampu'] },
    ]));
const formatNotes = computed(() => (props.target === 'tpb'
    ? ['Satu baris untuk satu pertemuan kelas.', 'MK 4 SKS butuh dua baris per kelas (Pertemuan 1 dan 2).']
    : ['Satu baris untuk satu pertemuan kelas.']));
const summaryTitle = computed(() => {
    const result = preview.value;
    if (!result) return '';
    if (props.target === 'faculty') return `${result.rows ?? 0} baris siap disimpan`;

    return result.meetings ? `${result.meetings} pertemuan siap disimpan` : 'Data TPB siap disimpan, tanpa jadwal';
});
const summaryStats = computed(() => {
    const result = preview.value;
    if (!result) return [];
    if (props.target === 'faculty') return [{ label: 'Baris', value: result.rows ?? 0 }, { label: 'Dilewati', value: result.skipped ?? 0 }];

    return [
        ...(result.data ? [
            { label: 'Mata kuliah', value: result.data.offerings },
            { label: 'Grup', value: result.data.groups },
            { label: 'Kelas', value: result.data.classes },
        ] : []),
        ...(result.meetings ? [{ label: 'Pertemuan', value: result.meetings }] : []),
    ];
});
const summaryNotes = computed(() => {
    const result = preview.value;
    if (!result) return [];
    const notes: { icon: typeof Info; tone: string; text: string }[] = [];
    if (props.target === 'faculty') {
        notes.push({ icon: Info, tone: 'text-gray-400', text: 'Import ini menggantikan import sebelumnya untuk periode yang sama.' });

        return notes;
    }
    if (result.data?.replaced) {
        notes.push({ icon: TriangleAlert, tone: 'text-amber-600', text: `${result.data.replaced} mata kuliah sudah punya grup. Grup dan kelas lamanya diganti isi file.` });
    }
    if (result.warnings.length) {
        notes.push({ icon: TriangleAlert, tone: 'text-amber-600', text: `${result.warnings.length} peringatan. Peringatan tidak menghalangi penyimpanan.` });
    }
    notes.push(result.meetings
        ? { icon: ArrowRight, tone: 'text-primary', text: 'Jadwal disimpan sebagai run TPB baru yang belum aktif. Aktifkan dan publikasikan lewat halaman Proses GA.' }
        : { icon: Info, tone: 'text-gray-400', text: 'File ini tidak berisi jadwal, jadi tidak ada run baru.' });

    return notes;
});
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
            <h2 class="text-base font-semibold text-gray-900">Unggah file jadwal TPB</h2>
            <div class="flex flex-wrap gap-2">
                <a href="/api/internal/tpb-schedule/import/template" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <FileSpreadsheet class="size-4" aria-hidden="true" />
                    Unduh template
                </a>
                <a
                    v-if="target === 'tpb'"
                    :href="`/api/internal/tpb-offerings/import/template?prefill=1&period=${encodeURIComponent(period)}`"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <FileSpreadsheet class="size-4" aria-hidden="true" />
                    Unduh data dan jadwal saat ini
                </a>
            </div>
        </div>

        <div class="space-y-3 rounded-lg bg-gray-50 p-4">
            <p class="text-sm font-medium text-gray-900">Format file</p>
            <ul class="list-inside list-disc space-y-0.5 text-sm text-gray-600">
                <li v-for="note in formatNotes" :key="note">{{ note }}</li>
            </ul>
            <div class="grid gap-3 md:grid-cols-2">
                <section v-for="group in formatGroups" :key="group.title" class="rounded-lg border border-gray-200 bg-white p-3">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        {{ group.title }}
                        <span v-if="group.required" class="rounded bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary">Wajib</span>
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">{{ group.hint }}</p>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li v-for="column in group.columns" :key="column" class="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700">{{ column }}</li>
                    </ul>
                </section>
            </div>
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
            <template v-else>
                <Alert variant="success" :title="summaryTitle" />
                <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div v-for="stat in summaryStats" :key="stat.label" class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <dt class="text-xs text-gray-500">{{ stat.label }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">{{ stat.value }}</dd>
                    </div>
                </dl>
                <ul class="space-y-1.5">
                    <li v-for="note in summaryNotes" :key="note.text" class="flex items-start gap-2 text-sm text-gray-600">
                        <component :is="note.icon" class="mt-0.5 size-4 shrink-0" :class="note.tone" aria-hidden="true" />
                        {{ note.text }}
                    </li>
                </ul>
            </template>
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
            <div v-if="preview.schedule?.length" class="space-y-2">
                <h3 class="text-sm font-semibold text-gray-900">Pratinjau jadwal ({{ preview.schedule.length }} pertemuan)</h3>
                <div class="max-h-96 overflow-auto rounded-lg border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">No</th>
                                <th class="px-3 py-2 font-medium">Mata kuliah</th>
                                <th class="px-3 py-2 font-medium">Kelas</th>
                                <th class="px-3 py-2 font-medium">Grup</th>
                                <th class="px-3 py-2 font-medium">Pert.</th>
                                <th class="px-3 py-2 font-medium">Hari</th>
                                <th class="px-3 py-2 font-medium">Sesi</th>
                                <th class="px-3 py-2 font-medium">Ruang</th>
                                <th class="px-3 py-2 font-medium">Dosen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, index) in preview.schedule" :key="item.row">
                                <td class="px-3 py-2 tabular-nums text-gray-500">{{ index + 1 }}</td>
                                <td class="px-3 py-2"><span class="font-medium text-gray-900">{{ item.course }}</span> <span class="text-xs text-gray-500">{{ item.code }}</span></td>
                                <td class="px-3 py-2">{{ item.class }}</td>
                                <td class="px-3 py-2">{{ item.group ?? '-' }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ item.meeting }}</td>
                                <td class="px-3 py-2">{{ item.day }}</td>
                                <td class="px-3 py-2">{{ item.session }}</td>
                                <td class="px-3 py-2">{{ item.room }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ item.lecturer ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end">
                <Button :disabled="!readyToSave" :loading="busy === 'save'" @click="save">Simpan jadwal TPB</Button>
            </div>
        </template>
    </section>
</template>
