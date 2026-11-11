<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Panel from '@/Components/ui/Panel.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { api, toApiError } from '@/lib/api';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, CheckCircle2, ChevronDown, CircleHelp, Download, FileSpreadsheet, History, TriangleAlert, UploadCloud, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Bulk fill of one period (offerings, groups, classes, lecturers) from a single .xlsx.
// The file is checked first (dry run). Nothing is saved until the admin imports it.
type ImportResult = { committed: boolean; offerings: number; groups: number; classes: number; replaced: number; errors: { row: number; message: string }[] };

const STEPS = ['Template', 'Unggah file', 'Periksa', 'Import'];
const EXAMPLE_HEADERS = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu'];
const EXAMPLE_ROWS = [
    [1, 'KU2512015', 'Matematika 1', 3, 'G1', 'Maba', '01,02,16,17', 1, 'A', 40, 'Nama Dosen, M.T.'],
    [2, 'KU2512015', 'Matematika 1', 3, 'G1', '', '', '', 'B', 40, ''],
    [3, 'KU2512015', 'Matematika 1', 3, 'G2', 'Mengulang', '01,02,03', '', 'C', 40, ''],
];
const RULES = [
    'Satu baris mewakili satu kelas.',
    'Baris dengan Kode MK dan Grup yang sama membentuk satu grup. Kolom Peserta, Prodi, dan Semester cukup diisi di baris pertama grup.',
    'Peserta diisi Maba, Non Maba, Campuran, Mengulang, atau Pilihan.',
    'Prodi diisi kode prodi yang dipisah koma. Kosongkan untuk grup Pilihan.',
    'Semester yang kosong diisi otomatis: Maba di semester pertama periode, Non Maba dua semester setelahnya. Kosongkan untuk Mengulang dan Pilihan.',
    'Kelas diisi huruf kelas (A, B, dan seterusnya). Jika kosong, kelas dibuat berurutan.',
    'Dosen Pengampu diisi nama dosen, dengan atau tanpa gelar. Boleh kosong dan bisa dipilih nanti di langkah Dosen pengampu.',
    'Kolom Ruangan, Hari, Sesi, dan Pertemuan (jadwal) dibaca halaman Import Jadwal TPB. Di sini kolom itu diabaikan, dan baris Pertemuan 2 tidak dihitung sebagai kelas baru.',
];
const controlClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';

const { periods, period } = usePeriodPicker();
const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<ImportResult | null>(null);
const result = ref<ImportResult | null>(null);
const working = ref<'check' | 'apply' | null>(null);
const failure = ref<string | null>(null);
const dragging = ref(false);

const periodLabel = computed(() => periods.value.find((p) => p.code === period.value)?.label ?? period.value);
const templateUrl = (prefill: boolean) => `/api/internal/tpb-offerings/import/template?period=${encodeURIComponent(period.value)}${prefill ? '&prefill=1' : ''}`;
const mappingUrl = computed(() => `/tpb/mappings?period=${encodeURIComponent(period.value)}`);
const fileSize = computed(() => (file.value ? `${Math.max(1, Math.round(file.value.size / 1024))} KB` : ''));
const hasErrors = computed(() => (preview.value?.errors.length ?? 0) > 0);
// Backend messages start with the course code ("KU2511001: ..."); show it in its own column.
const issues = computed(() => (preview.value?.errors ?? []).map((e) => {
    const match = e.message.match(/^(\S+): (.*)$/s);
    return { row: e.row, course: match ? match[1] : null, message: match ? match[2] : e.message };
}));
// Index of the step the admin is on. Steps before it are done, a failed check marks step 3 red.
const currentStep = computed(() => (result.value ? STEPS.length : !file.value ? 0 : !preview.value || hasErrors.value ? 2 : 3));

function chooseFile(next: File | null): void {
    preview.value = null;
    failure.value = null;
    file.value = next;
    if (next && !/\.xlsx$/i.test(next.name)) {
        file.value = null;
        failure.value = 'File harus berformat .xlsx.';
    }
}

function clearFile(event: Event): void {
    event.preventDefault();
    chooseFile(null);
    if (fileInput.value) fileInput.value.value = '';
}

function dropFile(event: DragEvent): void {
    dragging.value = false;
    if (working.value) return;
    chooseFile(event.dataTransfer?.files?.[0] ?? null);
}

function restart(): void {
    chooseFile(null);
    result.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

async function send(dryRun: boolean): Promise<void> {
    if (!file.value) return;
    working.value = dryRun ? 'check' : 'apply';
    failure.value = null;
    const body = new FormData();
    body.append('file', file.value);
    body.append('period', period.value);
    body.append('dry_run', dryRun ? '1' : '0');
    try {
        const response = await api.post<{ data: ImportResult }>('tpb-offerings/import', body, { validateStatus: (s) => s === 200 || s === 201 || s === 422 });
        if (dryRun || !response.data.data.committed) preview.value = response.data.data;
        else result.value = response.data.data;
    } catch (e) {
        failure.value = toApiError(e).message;
        preview.value = null;
    } finally {
        working.value = null;
    }
}
</script>

<template>
    <Head title="Import Data TPB" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-6 md:p-8">
            <header class="space-y-4">
                <Link :href="mappingUrl" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Kembali ke mapping
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Import Data TPB</h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Isi mata kuliah, grup, kelas, dan dosen pengampu TPB sekaligus dari satu file Excel.</p>
                </div>
            </header>

            <ol class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Progres import">
                <li v-for="(label, i) in STEPS" :key="label" class="flex items-center gap-3 rounded-lg border bg-white px-4 py-3" :class="i === currentStep ? (i === 2 && hasErrors ? 'border-red-300' : 'border-primary/50 shadow-sm') : 'border-gray-200'" :aria-current="i === currentStep ? 'step' : undefined">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="i < currentStep ? 'bg-green-100 text-green-700' : i === currentStep ? (i === 2 && hasErrors ? 'bg-red-100 text-red-700' : 'bg-primary text-white') : 'bg-gray-100 text-gray-500'"
                    >
                        <Check v-if="i < currentStep" class="size-4" aria-hidden="true" />
                        <TriangleAlert v-else-if="i === currentStep && i === 2 && hasErrors" class="size-3.5" aria-hidden="true" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span class="text-sm font-semibold" :class="i <= currentStep ? 'text-gray-900' : 'text-gray-500'">{{ label }}</span>
                </li>
            </ol>

            <Panel v-if="result" class="border-green-200">
                <div class="flex flex-col gap-5 p-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-700"><CheckCircle2 class="size-5" aria-hidden="true" /></span>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Import selesai</h2>
                            <p class="mt-1 text-sm text-gray-600">Data TPB {{ periodLabel }} sudah tersimpan.</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-md bg-white px-3 py-1.5 text-blue-800 ring-1 ring-blue-200">{{ result.offerings }} mata kuliah</span>
                                <span class="rounded-md bg-white px-3 py-1.5 text-indigo-800 ring-1 ring-indigo-200">{{ result.groups }} grup</span>
                                <span class="rounded-md bg-white px-3 py-1.5 text-teal-800 ring-1 ring-teal-200">{{ result.classes }} kelas</span>
                                <span v-if="result.replaced" class="rounded-md bg-white px-3 py-1.5 text-amber-800 ring-1 ring-amber-200">{{ result.replaced }} diganti</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 md:shrink-0">
                        <Link :href="mappingUrl" class="inline-flex h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white shadow-md shadow-primary/20 hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">Buka Mapping Dosen Pengampu</Link>
                        <Button type="button" variant="secondary" @click="restart">Import file lain</Button>
                    </div>
                </div>
            </Panel>

            <template v-else>
                <section class="grid items-start gap-6 xl:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]" aria-label="Langkah import">
                    <Panel>
                        <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary"><Download class="size-4" aria-hidden="true" /></span>
                            <div>
                                <p class="text-xs font-semibold text-primary">Langkah 1</p>
                                <h2 class="text-sm font-bold text-gray-900">Siapkan template</h2>
                            </div>
                        </div>
                        <div class="space-y-4 p-6">
                            <p class="text-sm text-gray-600">Mulai dari file kosong, atau unduh data TPB yang sudah ada supaya bisa diubah banyak sekaligus di Excel.</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <a :href="templateUrl(false)" class="group flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3.5 transition hover:border-primary/40 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary"><FileSpreadsheet class="size-4" aria-hidden="true" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-900">Template kosong</span>
                                        <span class="block text-xs text-gray-500">Mulai dari awal</span>
                                    </span>
                                    <Download class="size-4 shrink-0 text-gray-400 group-hover:text-primary" aria-hidden="true" />
                                </a>
                                <a :href="templateUrl(true)" class="group flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3.5 transition hover:border-primary/40 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary"><History class="size-4" aria-hidden="true" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-900">Data saat ini</span>
                                        <span class="block truncate text-xs text-gray-500">Isi {{ periodLabel }}</span>
                                    </span>
                                    <Download class="size-4 shrink-0 text-gray-400 group-hover:text-primary" aria-hidden="true" />
                                </a>
                            </div>
                            <p class="flex gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                Mata kuliah yang ada di file akan menggantikan grup dan dosennya yang sekarang. Mata kuliah yang tidak ada di file tidak diubah.
                            </p>

                            <details class="group rounded-lg border border-gray-200 bg-gray-50/70">
                                <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-900 marker:content-none">
                                    <CircleHelp class="size-4 text-primary" aria-hidden="true" />
                                    Petunjuk pengisian
                                    <ChevronDown class="ml-auto size-4 text-gray-500 transition-transform group-open:rotate-180" aria-hidden="true" />
                                </summary>
                                <div class="space-y-4 border-t border-gray-200 bg-white p-4">
                                    <ol class="space-y-2 text-sm text-gray-600">
                                        <li v-for="(rule, i) in RULES" :key="rule" class="flex gap-3">
                                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-primary">{{ i + 1 }}</span>
                                            <span>{{ rule }}</span>
                                        </li>
                                    </ol>
                                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                                        <table class="w-full min-w-[680px] border-collapse text-left text-xs">
                                            <caption class="sr-only">Contoh isi template import TPB</caption>
                                            <thead>
                                                <tr class="bg-blue-50/60 font-semibold text-gray-700">
                                                    <th v-for="h in EXAMPLE_HEADERS" :key="h" class="border-b border-gray-200 px-2.5 py-2">{{ h }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                                <tr v-for="(row, i) in EXAMPLE_ROWS" :key="i" class="even:bg-gray-50/60">
                                                    <td v-for="(cell, j) in row" :key="j" class="px-2.5 py-2">{{ cell }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </Panel>

                    <Panel>
                        <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary"><UploadCloud class="size-4" aria-hidden="true" /></span>
                            <div>
                                <p class="text-xs font-semibold text-primary">Langkah 2</p>
                                <h2 class="text-sm font-bold text-gray-900">Unggah file Excel</h2>
                            </div>
                        </div>
                        <div class="space-y-4 p-6">
                            <label class="block text-sm font-semibold text-gray-700">Periode tujuan
                                <select v-model="period" class="mt-1 block" :class="controlClass" :disabled="working !== null" @change="preview = null">
                                    <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                                </select>
                            </label>

                            <label
                                for="tpb-import-file"
                                class="group relative flex min-h-48 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition"
                                :class="[
                                    dragging ? 'scale-[1.01] border-primary bg-blue-50' : file ? 'border-green-300 bg-green-50/50' : 'border-gray-300 bg-gray-50/70 hover:border-primary/50 hover:bg-blue-50/40',
                                    working ? 'cursor-not-allowed opacity-60' : '',
                                ]"
                                @dragenter.prevent="dragging = true"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="dropFile"
                            >
                                <input id="tpb-import-file" ref="fileInput" type="file" accept=".xlsx" class="sr-only" :disabled="working !== null" @change="chooseFile(($event.target as HTMLInputElement).files?.[0] ?? null)" />
                                <span class="flex size-12 items-center justify-center rounded-lg transition" :class="file ? 'bg-green-100 text-green-700' : 'bg-blue-50 text-primary'">
                                    <FileSpreadsheet v-if="file" class="size-6" aria-hidden="true" />
                                    <UploadCloud v-else class="size-6" aria-hidden="true" />
                                </span>
                                <template v-if="file">
                                    <span class="mt-3 max-w-full truncate text-sm font-semibold text-gray-900">{{ file.name }}</span>
                                    <span class="mt-0.5 text-xs text-gray-500">{{ fileSize }} · klik untuk mengganti file</span>
                                    <button type="button" class="absolute right-3 top-3 rounded-md p-1.5 text-primary hover:bg-white hover:text-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" aria-label="Hapus file" @click="clearFile">
                                        <X class="size-4" aria-hidden="true" />
                                    </button>
                                </template>
                                <template v-else>
                                    <span class="mt-3 text-sm font-semibold text-gray-900">Seret file .xlsx ke sini</span>
                                    <span class="mt-0.5 text-xs text-gray-500">atau klik untuk memilih file dari komputer</span>
                                </template>
                            </label>

                            <Alert v-if="failure" :title="failure" />

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-gray-500">File diperiksa dulu. Belum ada data yang disimpan.</p>
                                <Button type="button" :disabled="!file" :loading="working === 'check'" @click="send(true)">Periksa file</Button>
                            </div>
                        </div>
                    </Panel>
                </section>

                <section v-if="preview" aria-label="Hasil pemeriksaan" class="space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Hasil pemeriksaan</h2>
                        <p class="mt-0.5 text-sm text-gray-500">{{ file?.name }} untuk {{ periodLabel }}</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 md:grid-cols-4" :class="preview.replaced ? 'lg:grid-cols-5' : ''">
                        <div class="rounded-lg bg-blue-50 p-4">
                            <dt class="text-xs font-medium text-blue-800">Mata kuliah</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-blue-900">{{ preview.offerings }}</dd>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-4">
                            <dt class="text-xs font-medium text-indigo-800">Grup</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-indigo-900">{{ preview.groups }}</dd>
                        </div>
                        <div class="rounded-lg bg-teal-50 p-4">
                            <dt class="text-xs font-medium text-teal-800">Kelas</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-teal-900">{{ preview.classes }}</dd>
                        </div>
                        <div v-if="preview.replaced" class="rounded-lg bg-amber-50 p-4">
                            <dt class="text-xs font-medium text-amber-800">Akan diganti</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-900">{{ preview.replaced }}</dd>
                        </div>
                        <div class="rounded-lg p-4" :class="hasErrors ? 'bg-red-50' : 'bg-green-50'">
                            <dt class="text-xs font-medium" :class="hasErrors ? 'text-red-800' : 'text-green-800'">Baris bermasalah</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums" :class="hasErrors ? 'text-red-900' : 'text-green-900'">{{ preview.errors.length }}</dd>
                        </div>
                    </dl>

                    <Alert v-if="hasErrors" :title="`${preview.errors.length} baris perlu diperbaiki sebelum data bisa diimport.`" />
                    <Alert v-else variant="success" title="File siap diimport">
                        {{ preview.offerings }} mata kuliah, {{ preview.groups }} grup, dan {{ preview.classes }} kelas akan disimpan ke {{ periodLabel }}.
                    </Alert>
                    <p v-if="preview.replaced && !hasErrors" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ preview.replaced }} mata kuliah sudah punya grup di periode ini. Grup, kelas, dan dosennya akan diganti oleh isi file.
                    </p>

                    <Panel v-if="hasErrors">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
                                        <th class="w-20 px-6 py-3">Baris</th>
                                        <th class="w-36 px-6 py-3">Mata kuliah</th>
                                        <th class="px-6 py-3">Yang perlu diperbaiki</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(issue, i) in issues" :key="i" class="align-top">
                                        <td class="px-6 py-3 tabular-nums text-gray-500">{{ issue.row }}</td>
                                        <td class="whitespace-nowrap px-6 py-3 font-medium tabular-nums text-gray-900">{{ issue.course ?? '-' }}</td>
                                        <td class="px-6 py-3 text-gray-700">{{ issue.message }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <div class="flex justify-end">
                        <Button type="button" :disabled="hasErrors" :loading="working === 'apply'" @click="send(false)">Import {{ preview.offerings }} mata kuliah</Button>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
