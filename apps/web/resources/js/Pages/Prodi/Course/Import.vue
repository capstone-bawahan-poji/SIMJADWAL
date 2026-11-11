<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Panel from '@/Components/ui/Panel.vue';
import { api, toApiError, type ApiError } from '@/lib/api';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, CircleHelp, Download, FileSpreadsheet, Trash2, UploadCloud } from 'lucide-vue-next';
import { AxiosError } from 'axios';
import { computed, reactive, ref } from 'vue';

interface LecturerRef {
    id: number;
    name: string;
    title: string | null;
    study_program_code: string | null;
}

interface ImportIssue {
    row: number;
    column: string;
    value?: string | number | null;
    message: string;
}

interface ImportClass {
    number: number;
    label: string;
    row: number | null;
    lecturer: LecturerRef | null;
    previous_lecturer: LecturerRef | null;
    change: 'added' | 'removed' | 'lecturer_changed' | 'same';
}

interface ImportCourse {
    code: string;
    status: 'created' | 'updated' | 'unchanged' | 'invalid';
    rows: number[];
    fields: { name: string; sks: number; semester: number; class_capacity: number; parallel_class_count: number };
    changes: { field: string; from: string | number; to: string | number }[];
    classes: ImportClass[];
}

interface LecturerMatch {
    input: string;
    key: string;
    rows: number[];
    status: 'matched' | 'resolved' | 'ambiguous' | 'not_found';
    lecturer: LecturerRef | null;
    candidates: LecturerRef[];
}

interface ImportPreview {
    can_commit: boolean;
    plan_hash: string;
    summary: { rows: number; courses: number; created: number; updated: number; unchanged: number; invalid: number; errors: number; warnings: number };
    errors: ImportIssue[];
    warnings: ImportIssue[];
    courses: ImportCourse[];
    lecturer_matches: LecturerMatch[];
    lecturer_options: LecturerRef[];
    untouched_courses: { code: string; name: string; semester: number }[];
}

interface ImportResult {
    created: number;
    updated: number;
    unchanged: number;
}

const studyProgramId = computed(() => usePage().props.auth.user.study_program_id);

const templateUrl = (prefill: boolean) => `/api/internal/courses/import/template?study_program_id=${studyProgramId.value}${prefill ? '&prefill=1' : ''}`;

const exampleHeaders = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Semester', 'Kapasitas', 'Kelas', 'Dosen Pengampu'];
const exampleRows = [
    [1, 'IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', 'Nama Dosen, S.Kom., M.Kom.'],
    [2, 'IF2514101', 'Matematika Diskrit', 3, 1, 40, 'B', 'Nama Dosen Lain, S.T., M.T.'],
    [3, 'IF2515102', 'Keamanan Siber', 3, 5, 40, 'X', 'Nama Dosen, S.Kom., M.Kom.'],
];

const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const resolutions = reactive<Record<string, number>>({});
const lecturerSearch = reactive<Record<string, string>>({});
const preview = ref<ImportPreview | null>(null);
const result = ref<ImportResult | null>(null);
const processing = ref(false);
const failure = ref<ApiError | null>(null);
const notice = ref<string | null>(null);
const statusFilter = ref<ImportCourse['status'] | 'all'>('all');

const statusLabel: Record<ImportCourse['status'], { text: string; class: string; dot: string }> = {
    created: { text: 'Baru', class: 'text-green-700', dot: 'bg-green-600' },
    updated: { text: 'Berubah', class: 'text-amber-700', dot: 'bg-amber-500' },
    unchanged: { text: 'Sama', class: 'text-gray-500', dot: 'bg-gray-400' },
    invalid: { text: 'Error', class: 'text-red-700', dot: 'bg-red-600' },
};
const filterTabs: { key: ImportCourse['status'] | 'all'; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'created', label: 'Baru' },
    { key: 'updated', label: 'Berubah' },
    { key: 'unchanged', label: 'Sama' },
    { key: 'invalid', label: 'Error' },
];

const fieldLabel: Record<string, string> = {
    name: 'Nama',
    sks: 'SKS',
    semester: 'Semester',
    class_capacity: 'Kapasitas',
    parallel_class_count: 'Jumlah kelas',
};

const classChange: Record<ImportClass['change'], string> = {
    added: 'baru',
    removed: 'dihapus',
    lecturer_changed: 'dosen berubah',
    same: '',
};

const openMatches = computed(() => preview.value?.lecturer_matches.filter((m) => m.status === 'ambiguous' || m.status === 'not_found') ?? []);
const resolvedMatches = computed(() => preview.value?.lecturer_matches.filter((m) => m.status === 'resolved') ?? []);
const sortedCourses = computed(() => {
    const order: ImportCourse['status'][] = ['invalid', 'created', 'updated', 'unchanged'];

    return [...(preview.value?.courses ?? [])]
        .filter((course) => statusFilter.value === 'all' || course.status === statusFilter.value)
        .sort((a, b) => order.indexOf(a.status) - order.indexOf(b.status));
});

function lecturerName(lecturer: LecturerRef | null): string {
    if (!lecturer) return '—';

    return lecturer.title ? `${lecturer.name}, ${lecturer.title}` : lecturer.name;
}

function statusCount(status: ImportCourse['status']): number {
    return preview.value?.courses.filter((course) => course.status === status).length ?? 0;
}

function chooseFile(nextFile: File | null): void {
    file.value = nextFile;
    Object.keys(resolutions).forEach((key) => delete resolutions[key]);
    Object.keys(lecturerSearch).forEach((key) => delete lecturerSearch[key]);
    preview.value = null;
    statusFilter.value = 'all';
    failure.value = null;
    notice.value = null;

    if (nextFile && !/\.xlsx$/i.test(nextFile.name)) {
        file.value = null;
        failure.value = {
            status: 0,
            message: 'File harus berformat .xlsx.',
            code: 'INVALID_FILE_TYPE',
            errors: {},
        };
    }
}

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function removeFile(): void {
    chooseFile(null);
    if (fileInput.value) fileInput.value.value = '';
}

function pickFile(event: Event): void {
    chooseFile((event.target as HTMLInputElement).files?.[0] ?? null);
}

function dropFile(event: DragEvent): void {
    isDragging.value = false;
    if (processing.value) return;
    chooseFile(event.dataTransfer?.files[0] ?? null);
}

function issuesFor(course: ImportCourse, labels: string[]): string[] {
    const rowSet = new Set(course.rows);

    return (preview.value?.errors ?? [])
        .filter((issue) => rowSet.has(issue.row) && labels.includes(issue.column))
        .map((issue) => `Baris ${issue.row}: ${issue.message}`);
}

function lecturerOptionsFor(match: LecturerMatch): LecturerRef[] {
    const query = (lecturerSearch[match.key] ?? '').trim().toLocaleLowerCase();
    const unique = new Map<number, LecturerRef>();

    [...match.candidates, ...(preview.value?.lecturer_options ?? [])].forEach((lecturer) => unique.set(lecturer.id, lecturer));

    return [...unique.values()].filter((lecturer) => {
        if (!query) return true;
        return `${lecturer.name} ${lecturer.title ?? ''} ${lecturer.study_program_code ?? ''}`.toLocaleLowerCase().includes(query);
    });
}

function restart(): void {
    file.value = null;
    preview.value = null;
    result.value = null;
    failure.value = null;
    notice.value = null;
    statusFilter.value = 'all';
    Object.keys(resolutions).forEach((key) => delete resolutions[key]);
    Object.keys(lecturerSearch).forEach((key) => delete lecturerSearch[key]);
    if (fileInput.value) fileInput.value.value = '';
}

async function send(dryRun: boolean): Promise<void> {
    if (!file.value || !studyProgramId.value) return;

    const form = new FormData();
    form.append('study_program_id', String(studyProgramId.value));
    form.append('file', file.value);
    form.append('dry_run', dryRun ? '1' : '0');
    form.append('resolutions', JSON.stringify(resolutions));
    if (!dryRun && preview.value) form.append('plan_hash', preview.value.plan_hash);

    processing.value = true;
    failure.value = null;

    try {
        const response = await api.post<{ data: ImportPreview | ImportResult }>('courses/import', form);
        if (dryRun) {
            preview.value = response.data.data as ImportPreview;
        } else {
            result.value = response.data.data as ImportResult;
        }
    } catch (e) {
        const body = e instanceof AxiosError ? (e.response?.data as { data?: ImportPreview } | undefined) : undefined;
        const error = toApiError(e);

        if (body?.data) {
            // Commit refused: the response carries the fresh preview with its errors.
            preview.value = body.data;
        } else if (error.code === 'IMPORT_PLAN_CHANGED') {
            notice.value = error.message;
            processing.value = false;
            await send(true);

            return;
        } else {
            const fileError = (error.errors.file as string[] | undefined)?.[0];
            failure.value = fileError ? { ...error, message: fileError } : error;
            preview.value = null;
        }
    } finally {
        processing.value = false;
    }
}

function resolve(match: LecturerMatch, value: string): void {
    if (!value) return;
    resolutions[match.key] = Number(value);
    void send(true);
}

function unresolve(match: LecturerMatch): void {
    delete resolutions[match.key];
    void send(true);
}
</script>

<template>
    <Head title="Import Mata Kuliah" />

    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('prodi.mappings.index')" class="-ml-1 inline-flex items-center gap-1.5 rounded px-1 text-sm font-medium text-gray-600 transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                        Data Mata Kuliah
                    </Link>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">Import Mata Kuliah</h1>
                    <p class="mt-1 text-sm text-gray-600">Tambah atau perbarui mata kuliah dan dosen pengampu dari file Excel.</p>
                </div>
            </header>


            <Panel v-if="result" class="overflow-hidden border-green-200 p-0 shadow-sm">
                <div class="flex flex-col gap-5 bg-green-50/40 p-6 md:flex-row md:items-center md:justify-between md:p-8">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-green-100 text-green-700">
                            <CheckCircle2 class="h-6 w-6" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Import berhasil diselesaikan</h2>
                            <p class="mt-1 text-sm text-gray-600">Perubahan data mata kuliah sudah tersimpan.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-green-800 ring-1 ring-green-200">{{ result.created }} baru</span>
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ result.updated }} diperbarui</span>
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 ring-1 ring-gray-200">{{ result.unchanged }} tidak berubah</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 md:shrink-0">
                        <Link :href="route('prodi.courses.index')" class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">
                            Lihat data mata kuliah
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Button variant="ghost" @click="restart">Import file lain</Button>
                    </div>
                </div>
            </Panel>

            <template v-else>
                <section class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]" aria-label="Langkah import">
                    <Panel class="overflow-hidden p-0 shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4 md:px-6">
                            <h2 class="text-sm font-semibold text-gray-900"><span class="mr-2 text-gray-400">1</span>Siapkan template</h2>
                        </div>
                        <div class="space-y-4 p-5 md:p-6">
                            <p class="text-sm leading-6 text-gray-600">
                                Unduh format Excel yang sesuai. Template terisi membantu memperbarui data tanpa mengubah mata kuliah yang tidak ada di file.
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <a
                                    :href="templateUrl(false)"
                                    class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3.5 transition hover:border-primary/40 hover:bg-blue-50/50"
                                >
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition group-hover:bg-white group-hover:text-primary">
                                        <FileSpreadsheet class="h-5 w-5" aria-hidden="true" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-bold text-gray-900">Template kosong</span>
                                        <span class="mt-0.5 block text-[11px] text-gray-500">Mulai dari awal</span>
                                    </span>
                                    <Download class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-primary" aria-hidden="true" />
                                </a>
                                <a
                                    :href="templateUrl(true)"
                                    class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3.5 transition hover:border-primary/40 hover:bg-blue-50/50"
                                >
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                        <FileSpreadsheet class="h-5 w-5" aria-hidden="true" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-bold text-gray-900">Template terisi</span>
                                        <span class="mt-0.5 block text-[11px] text-gray-500">Data saat ini</span>
                                    </span>
                                    <Download class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-primary" aria-hidden="true" />
                                </a>
                            </div>

                            <details class="group rounded-xl border border-gray-200 bg-gray-50/70">
                                <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-semibold text-gray-800 marker:content-none">
                                    <CircleHelp class="h-4 w-4 text-primary" aria-hidden="true" />
                                    Petunjuk pengisian file
                                    <span class="ml-auto text-xs font-medium text-gray-400 transition group-open:rotate-180">⌄</span>
                                </summary>
                                <div class="space-y-4 border-t border-gray-200 bg-white p-4">
                                    <ol class="space-y-2.5 text-xs leading-5 text-gray-600">
                                        <li><span class="mr-2 font-bold text-primary">01</span>Satu baris mewakili satu kelas. Isi Kode MK, Mata Kuliah, SKS, Semester, Kapasitas, Kelas, dan Dosen Pengampu. Kolom No boleh diisi bebas.</li>
                                        <li><span class="mr-2 font-bold text-primary">02</span>Nama, SKS, semester, dan kapasitas harus sama pada semua baris dengan kode mata kuliah yang sama.</li>
                                        <li><span class="mr-2 font-bold text-primary">03</span>Kelas paralel dimulai dari A tanpa melompati urutan. Kelas X untuk pengayaan disimpan sebagai kelas A dan hanya boleh satu kelas.</li>
                                        <li><span class="mr-2 font-bold text-primary">04</span>Dosen boleh dikosongkan jika belum ditentukan. Nama dosen harus terdaftar; dosen prodi lain juga dapat dipilih. Nama yang tidak cocok bisa dicocokkan di preview.</li>
                                        <li><span class="mr-2 font-bold text-primary">05</span>Kode mata kuliah yang sudah ada akan diperbarui. Mata kuliah yang tidak tercantum di file tetap tersimpan dan tidak diubah.</li>
                                        <li><span class="mr-2 font-bold text-primary">06</span>Kolom tambahan seperti Hari, Sesi, atau Ruangan akan diabaikan.</li>
                                    </ol>
                                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                                        <table class="w-full min-w-[640px] border-collapse text-left text-[11px]">
                                            <caption class="sr-only">Contoh format template import mata kuliah</caption>
                                            <thead>
                                                <tr class="bg-gray-50 font-bold text-gray-600">
                                                    <th v-for="h in exampleHeaders" :key="h" class="border-b border-gray-200 px-2.5 py-2">{{ h }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(row, i) in exampleRows" :key="i" class="text-gray-600 even:bg-gray-50/70">
                                                    <td v-for="(cell, j) in row" :key="j" class="border-b border-gray-100 px-2.5 py-2 last:border-b-0">{{ cell }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </Panel>

                    <Panel class="overflow-hidden p-0 shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4 md:px-6">
                            <h2 class="text-sm font-semibold text-gray-900"><span class="mr-2 text-gray-400">2</span>Unggah file Excel</h2>
                        </div>
                        <div class="space-y-5 p-5 md:p-6">
                            <p class="text-sm leading-6 text-gray-600">Pilih file .xlsx, lalu periksa hasilnya terlebih dahulu sebelum menyimpan perubahan.</p>
                            <input
                                id="import-file"
                                ref="fileInput"
                                type="file"
                                accept=".xlsx"
                                :disabled="processing"
                                class="sr-only"
                                @change="pickFile"
                            />

                            <div v-if="file" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-700">
                                    <FileSpreadsheet class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ file.name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ formatSize(file.size) }} · siap dipreview</p>
                                </div>
                                <label
                                    for="import-file"
                                    class="inline-flex h-8 cursor-pointer items-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-within:ring-2 focus-within:ring-primary"
                                    :class="{ 'pointer-events-none opacity-60': processing }"
                                >
                                    Ganti
                                </label>
                                <Button variant="ghost" size="sm" :disabled="processing" :aria-label="`Hapus file ${file.name}`" class="text-red-700 hover:bg-red-50" @click="removeFile">
                                    <Trash2 class="h-4 w-4" aria-hidden="true" />
                                    Hapus
                                </Button>
                            </div>

                            <label
                                v-else
                                for="import-file"
                                class="group flex min-h-52 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-5 py-8 text-center transition"
                                :class="[
                                    isDragging ? 'border-primary bg-blue-50' : 'border-gray-300 bg-gray-50/70 hover:border-primary/50 hover:bg-blue-50/50',
                                    processing ? 'cursor-not-allowed opacity-60' : '',
                                ]"
                                @dragenter.prevent="isDragging = true"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="dropFile"
                            >
                                <UploadCloud class="h-8 w-8 text-gray-400 transition group-hover:text-primary" aria-hidden="true" />
                                <span class="mt-3 text-sm font-semibold text-gray-900">Seret file ke sini</span>
                                <span class="mt-1 text-xs text-gray-500">atau klik untuk memilih dari perangkat. Format .xlsx</span>
                            </label>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <Button :disabled="!file" :loading="processing && !preview" @click="send(true)">
                                    <FileSpreadsheet class="h-4 w-4" aria-hidden="true" />
                                    Lihat preview
                                </Button>
                            </div>
                            <Alert v-if="failure" :title="failure.message" />
                        </div>
                    </Panel>
                </section>

                <!-- Preview -->
                <template v-if="preview">
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-label="Ringkasan preview import">
                        <div class="flex flex-col gap-4 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between md:px-6">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Preview hasil import</h2>
                                <p class="mt-1 text-xs text-gray-500">Tinjau perubahan dan perbaiki temuan sebelum menyimpan.</p>
                            </div>
                            <span
                                class="inline-flex w-fit items-center gap-2 rounded-md px-2.5 py-1 text-xs font-semibold"
                                :class="preview.can_commit ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'"
                            >
                                <span class="h-2 w-2 rounded-full" :class="preview.can_commit ? 'bg-green-500' : 'bg-red-500'"></span>
                                {{ preview.can_commit ? 'Siap diimport' : 'Perlu diperbaiki' }}
                            </span>
                        </div>
                        <dl class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-3 sm:divide-y-0 lg:grid-cols-5">
                            <div class="p-4 md:px-5">
                                <dt class="text-xs font-medium text-gray-500">Baris file</dt>
                                <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ preview.summary.rows }}</dd>
                            </div>
                            <div class="p-4 md:px-5">
                                <dt class="text-xs font-medium text-gray-500">Mata kuliah</dt>
                                <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ preview.summary.courses }}</dd>
                            </div>
                            <div class="p-4 md:px-5">
                                <dt class="text-xs font-medium text-gray-500">Baru</dt>
                                <dd class="mt-1 text-2xl font-bold tabular-nums text-green-700">{{ preview.summary.created }}</dd>
                            </div>
                            <div class="p-4 md:px-5">
                                <dt class="text-xs font-medium text-gray-500">Berubah</dt>
                                <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ preview.summary.updated }}</dd>
                            </div>
                            <div class="p-4 md:px-5">
                                <dt class="text-xs font-medium text-gray-500">Error</dt>
                                <dd class="mt-1 text-2xl font-bold tabular-nums" :class="preview.summary.errors ? 'text-red-700' : 'text-gray-900'">
                                    {{ preview.summary.errors }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <Alert v-if="notice" :title="notice" dismissible @dismiss="notice = null" />

                    <Alert v-if="!preview.can_commit" :title="`${preview.summary.errors} error ditemukan. Import diblokir sampai semua error diperbaiki.`">
                        Perbaiki file lalu pilih ulang file dan klik Lihat Preview. Dosen yang belum cocok bisa dipilih langsung di bawah.
                    </Alert>
                    <Alert
                        v-else
                        variant="success"
                        :title="`Siap diimport: ${preview.summary.created} baru, ${preview.summary.updated} berubah, ${preview.summary.unchanged} sama.`"
                    >
                        {{ preview.summary.rows }} baris, {{ preview.summary.courses }} mata kuliah.
                        <span v-if="preview.untouched_courses.length">{{ preview.untouched_courses.length }} mata kuliah lain tidak ada di file dan tidak diubah.</span>
                    </Alert>

                    <!-- Lecturer matching -->
                    <Panel v-if="openMatches.length || resolvedMatches.length" class="p-6">
                        <h2 class="text-sm font-bold text-gray-900">Pencocokan Dosen</h2>
                        <p v-if="openMatches.length" class="mt-1 text-xs text-gray-500">Nama di file belum cocok dengan satu dosen. Pilih dosen yang dimaksud agar import bisa dilanjutkan.</p>
                        <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                            <li v-for="match in openMatches" :key="match.key" class="flex flex-col gap-3 py-4 md:flex-row md:items-center md:justify-between">
                                <div class="text-sm">
                                    <p class="font-semibold text-gray-900">{{ match.input }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        <span class="font-medium text-red-700">{{ match.status === 'ambiguous' ? `Cocok dengan ${match.candidates.length} dosen` : 'Tidak ditemukan' }}</span>
                                        · baris {{ match.rows.join(', ') }}
                                    </p>
                                </div>
                                <div class="grid w-full gap-2 sm:grid-cols-2 md:w-[28rem]">
                                    <input
                                        v-model="lecturerSearch[match.key]"
                                        type="search"
                                        placeholder="Cari nama, gelar, atau prodi"
                                        :aria-label="`Cari dosen untuk ${match.input}`"
                                        class="w-full rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary"
                                        :disabled="processing"
                                    />
                                    <select
                                        class="w-full rounded-lg border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary"
                                        :aria-label="`Pilih dosen untuk ${match.input}`"
                                        :disabled="processing"
                                        @change="resolve(match, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">Pilih dosen…</option>
                                        <option v-for="lecturer in lecturerOptionsFor(match)" :key="lecturer.id" :value="lecturer.id">
                                            {{ lecturerName(lecturer) }}{{ lecturer.study_program_code ? ` (${lecturer.study_program_code})` : '' }}
                                        </option>
                                    </select>
                                </div>
                            </li>
                            <li v-for="match in resolvedMatches" :key="match.key" class="flex items-center justify-between gap-3 py-3 text-sm">
                                <p>
                                    <span class="font-semibold text-gray-900">{{ match.input }}</span>
                                    <span class="text-gray-500"> → {{ lecturerName(match.lecturer) }} (dipilih manual)</span>
                                </p>
                                <button type="button" class="text-xs font-semibold text-gray-600 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" :disabled="processing" @click="unresolve(match)">Batalkan</button>
                            </li>
                        </ul>
                    </Panel>

                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filter status mata kuliah">
                        <button
                            v-for="tab in filterTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors"
                            :class="statusFilter === tab.key ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            :aria-pressed="statusFilter === tab.key"
                            @click="statusFilter = tab.key"
                        >
                            {{ tab.label }}
                            <span class="ml-1 tabular-nums opacity-70">{{ tab.key === 'all' ? preview.courses.length : statusCount(tab.key) }}</span>
                        </button>
                    </div>

                    <!-- Errors and warnings -->
                    <Panel v-if="preview.errors.length || preview.warnings.length" class="space-y-5 p-6">
                        <div v-if="preview.errors.length">
                            <h2 class="text-sm font-bold text-gray-900">Error ({{ preview.errors.length }})</h2>
                            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                                <li v-for="(issue, i) in preview.errors" :key="`e-${i}`" class="py-2">
                                    <span class="font-semibold text-gray-900">Baris {{ issue.row }} · {{ issue.column }}</span>
                                    <span v-if="issue.value !== null && issue.value !== undefined" class="text-gray-500"> ("{{ issue.value }}")</span>
                                    <span class="text-red-700"> · {{ issue.message }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-if="preview.warnings.length">
                            <h2 class="text-sm font-bold text-gray-900">Peringatan ({{ preview.warnings.length }})</h2>
                            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                                <li v-for="(issue, i) in preview.warnings" :key="`w-${i}`" class="py-2">
                                    <span class="font-semibold text-gray-900">Baris {{ issue.row }} · {{ issue.column }}</span>
                                    <span class="text-amber-800"> · {{ issue.message }}</span>
                                </li>
                            </ul>
                        </div>
                    </Panel>

                    <!-- Courses -->
                    <Panel>
                        <div class="w-full overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Mata Kuliah</th>
                                        <th class="px-4 py-3 text-center">SKS</th>
                                        <th class="px-4 py-3 text-center">Smt</th>
                                        <th class="px-4 py-3 text-center">Kapasitas</th>
                                        <th class="px-4 py-3">Kelas &amp; Dosen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-xs text-gray-700" :class="{ 'opacity-60': processing }">
                                    <tr v-for="course in sortedCourses" :key="course.code" class="align-top">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="statusLabel[course.status].class">
                                                <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[course.status].dot"></span>{{ statusLabel[course.status].text }}
                                            </span>
                                            <span class="mt-1 block text-[10px] text-gray-400">Baris {{ course.rows.join(', ') }}</span>
                                        </td>
                                        <td class="px-4 py-3" :class="{ 'bg-red-50 text-red-800': issuesFor(course, ['Kode MK', 'Mata Kuliah']).length }" :title="issuesFor(course, ['Kode MK', 'Mata Kuliah']).join('\n')">
                                            <span class="font-mono text-[10px] font-bold text-blue-700">{{ course.code }}</span>
                                            <span class="block font-bold text-gray-900">{{ course.fields.name }}</span>
                                            <ul v-if="course.changes.length" class="mt-1 text-[11px] text-amber-700">
                                                <li v-for="c in course.changes" :key="c.field">{{ fieldLabel[c.field] ?? c.field }}: {{ c.from }} → {{ c.to }}</li>
                                            </ul>
                                        </td>
                                        <td class="px-4 py-3 text-center" :class="{ 'bg-red-50 text-red-800': issuesFor(course, ['SKS']).length }" :title="issuesFor(course, ['SKS']).join('\n')">{{ course.fields.sks }}</td>
                                        <td class="px-4 py-3 text-center" :class="{ 'bg-red-50 text-red-800': issuesFor(course, ['Semester']).length }" :title="issuesFor(course, ['Semester']).join('\n')">{{ course.fields.semester }}</td>
                                        <td class="px-4 py-3 text-center" :class="{ 'bg-red-50 text-red-800': issuesFor(course, ['Kapasitas']).length }" :title="issuesFor(course, ['Kapasitas']).join('\n')">{{ course.fields.class_capacity }}</td>
                                        <td class="px-4 py-3" :class="{ 'bg-red-50 text-red-800': issuesFor(course, ['Kelas', 'Dosen Pengampu']).length }" :title="issuesFor(course, ['Kelas', 'Dosen Pengampu']).join('\n')">
                                            <ul class="space-y-0.5">
                                                <li
                                                    v-for="cls in course.classes"
                                                    :key="cls.number"
                                                    class="-mx-2 rounded-md px-2 py-1"
                                                    :class="{ 'text-gray-400 line-through': cls.change === 'removed', 'bg-amber-50': cls.change === 'lecturer_changed' }"
                                                >
                                                    <span class="font-semibold">{{ cls.label }}</span> · <span :class="{ 'font-semibold text-gray-900': cls.change === 'lecturer_changed' }">{{ lecturerName(cls.lecturer) }}</span>
                                                    <span v-if="cls.change === 'lecturer_changed'" class="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">Dosen diganti</span>
                                                    <span v-else-if="classChange[cls.change] && course.status !== 'created'" class="text-amber-700">({{ classChange[cls.change] }})</span>
                                                    <span v-if="cls.change === 'lecturer_changed'" class="mt-0.5 block text-[11px] text-gray-500">
                                                        sebelumnya <span class="line-through">{{ lecturerName(cls.previous_lecturer) }}</span>
                                                    </span>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr v-if="sortedCourses.length === 0">
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada mata kuliah dengan status ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel v-if="preview.untouched_courses.length" class="space-y-3 p-6">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Tidak ada di file ({{ preview.untouched_courses.length }})</h2>
                            <p class="mt-1 text-xs text-gray-500">Mata kuliah ini tetap tersimpan dan tidak akan diubah oleh import ini.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase text-gray-500">
                                        <th class="px-3 py-2">Kode MK</th>
                                        <th class="px-3 py-2">Mata Kuliah</th>
                                        <th class="px-3 py-2 text-center">Semester</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="course in preview.untouched_courses" :key="course.code">
                                        <td class="px-3 py-2 font-mono font-semibold text-blue-700">{{ course.code }}</td>
                                        <td class="px-3 py-2">{{ course.name }}</td>
                                        <td class="px-3 py-2 text-center">{{ course.semester }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <div class="flex flex-col items-end gap-2">
                        <Button :disabled="!preview.can_commit" :loading="processing" @click="send(false)">Import {{ preview.summary.courses }} Mata Kuliah</Button>
                        <p v-if="!preview.can_commit" class="text-xs text-red-600">Tombol aktif setelah semua error diperbaiki.</p>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>