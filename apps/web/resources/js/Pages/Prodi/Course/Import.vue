<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ProgramLockBanner from '@/Components/constraint/ProgramLockBanner.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { useOwnSubmission } from '@/composables/useOwnSubmission';
import { api, toApiError, type ApiError } from '@/lib/api';
import { Head, Link, usePage } from '@inertiajs/vue3';
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
const { submission, locked } = useOwnSubmission();

const templateUrl = (prefill: boolean) => `/api/internal/courses/import/template?study_program_id=${studyProgramId.value}${prefill ? '&prefill=1' : ''}`;

const exampleHeaders = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Semester', 'Kapasitas', 'Kelas', 'Dosen Pengampu'];
const exampleRows = [
    [1, 'IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', 'Nama Dosen, S.Kom., M.Kom.'],
    [2, 'IF2514101', 'Matematika Diskrit', 3, 1, 40, 'B', 'Nama Dosen Lain, S.T., M.T.'],
    [3, 'IF2515102', 'Keamanan Siber', 3, 5, 40, 'X', 'Nama Dosen, S.Kom., M.Kom.'],
];

const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const resolutions = reactive<Record<string, number>>({});
const preview = ref<ImportPreview | null>(null);
const result = ref<ImportResult | null>(null);
const processing = ref(false);
const failure = ref<ApiError | null>(null);
const notice = ref<string | null>(null);

const statusLabel: Record<ImportCourse['status'], { text: string; class: string }> = {
    created: { text: 'Baru', class: 'bg-green-50 text-green-700 border-green-200' },
    updated: { text: 'Berubah', class: 'bg-amber-50 text-amber-700 border-amber-200' },
    unchanged: { text: 'Sama', class: 'bg-gray-50 text-gray-600 border-gray-200' },
    invalid: { text: 'Error', class: 'bg-red-50 text-red-700 border-red-200' },
};

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

    return [...(preview.value?.courses ?? [])].sort((a, b) => order.indexOf(a.status) - order.indexOf(b.status));
});

function lecturerName(lecturer: LecturerRef | null): string {
    if (!lecturer) return '—';

    return lecturer.title ? `${lecturer.name}, ${lecturer.title}` : lecturer.name;
}

function pickFile(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    Object.keys(resolutions).forEach((key) => delete resolutions[key]);
    preview.value = null;
    failure.value = null;
    notice.value = null;
}

function restart(): void {
    file.value = null;
    preview.value = null;
    result.value = null;
    failure.value = null;
    notice.value = null;
    Object.keys(resolutions).forEach((key) => delete resolutions[key]);
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
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Import Mata Kuliah</h1>
                    <p class="mt-1 text-sm text-gray-500">Unggah file xlsx berisi mata kuliah, kelas, dan dosen pengampu. Periksa preview sebelum mengimpor.</p>
                </div>
                <Link :href="route('prodi.courses.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Kembali ke Data Mata Kuliah</Link>
            </div>

            <ProgramLockBanner :submission="submission" />

            <!-- Done -->
            <Card v-if="result" class="space-y-4 border border-gray-100 shadow-sm">
                <Alert variant="success" title="Import selesai.">
                    {{ result.created }} mata kuliah baru, {{ result.updated }} diperbarui, {{ result.unchanged }} tidak berubah.
                </Alert>
                <div class="flex gap-3">
                    <Link :href="route('prodi.courses.index')" class="inline-flex h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary-hover">Lihat Data Mata Kuliah</Link>
                    <Button variant="ghost" @click="restart">Import file lain</Button>
                </div>
            </Card>

            <template v-else>
                <!-- Upload -->
                <Card class="space-y-4 border border-gray-100 shadow-sm">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">1. Unduh template</h2>
                        <p class="mt-1 text-xs text-gray-500">Template terisi berisi data mata kuliah saat ini, cocok untuk memperbarui data tiap semester.</p>
                        <div class="mt-3 flex flex-wrap gap-3 text-sm font-semibold">
                            <a :href="templateUrl(false)" class="text-primary hover:underline">Template kosong</a>
                            <a :href="templateUrl(true)" class="text-primary hover:underline">Template terisi data saat ini</a>
                        </div>
                    </div>

                    <details class="border-t border-gray-100 pt-4 text-sm text-gray-700">
                        <summary class="cursor-pointer font-bold text-gray-900">Petunjuk pengisian</summary>
                        <ol class="mt-3 list-decimal space-y-1 pl-5 text-xs leading-relaxed">
                            <li>Satu baris = satu kelas. Kolom: Kode MK, Mata Kuliah, SKS, Semester, Kapasitas, Kelas, Dosen Pengampu. Kolom No boleh diisi bebas.</li>
                            <li>Mata Kuliah, SKS, Semester, dan Kapasitas harus sama di semua baris dengan Kode MK yang sama.</li>
                            <li>Kelas berurutan dari A tanpa lompat (A, B, C, ...). Mata kuliah dengan satu kelas memakai A.</li>
                            <li>Kelas X = kelas pengayaan (terbuka untuk prodi lain). Disimpan sebagai kelas A, jadi mata kuliah dengan X hanya boleh punya satu kelas.</li>
                            <li>Dosen Pengampu: ketik atau salin nama dosen (gelar dan huruf besar/kecil tidak berpengaruh). Kosongkan jika dosen belum ditentukan. Dosen prodi lain boleh, tetapi harus sudah terdaftar di sistem. Nama yang tidak cocok bisa dipilih di preview.</li>
                            <li>Kode MK yang sudah ada akan diperbarui. Mata kuliah yang tidak ada di file tidak diubah.</li>
                            <li>Kolom tambahan (misalnya Hari, Sesi, Ruangan) diabaikan.</li>
                        </ol>
                        <div class="mt-3 w-full overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <caption class="mb-1 text-left font-semibold text-gray-900">Contoh</caption>
                                <thead>
                                    <tr class="bg-gray-50 font-bold text-gray-600">
                                        <th v-for="h in exampleHeaders" :key="h" class="border border-gray-200 px-2 py-1">{{ h }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in exampleRows" :key="i">
                                        <td v-for="(cell, j) in row" :key="j" class="border border-gray-200 px-2 py-1">{{ cell }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <div class="border-t border-gray-100 pt-4">
                        <label for="import-file" class="text-sm font-bold text-gray-900">2. Pilih file xlsx</label>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input id="import-file" ref="fileInput" type="file" accept=".xlsx" :disabled="locked" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200 sm:w-auto" @change="pickFile" />
                            <Button :disabled="!file || locked" :loading="processing && !preview" @click="send(true)">Lihat Preview</Button>
                        </div>
                    </div>

                    <Alert v-if="failure" :title="failure.message" />
                </Card>

                <!-- Preview -->
                <template v-if="preview">
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
                    <Card v-if="openMatches.length || resolvedMatches.length" class="space-y-3 border border-gray-100 shadow-sm">
                        <h2 class="text-sm font-bold text-gray-900">Pencocokan Dosen</h2>
                        <div v-for="match in openMatches" :key="match.key" class="flex flex-col gap-2 rounded-lg border border-red-200 bg-red-50 p-3 md:flex-row md:items-center md:justify-between">
                            <div class="text-sm">
                                <p class="font-semibold text-red-800">"{{ match.input }}"</p>
                                <p class="text-xs text-red-700">
                                    {{ match.status === 'ambiguous' ? `Cocok dengan ${match.candidates.length} dosen` : 'Tidak ditemukan' }} · baris {{ match.rows.join(', ') }}
                                </p>
                            </div>
                            <select
                                class="w-full rounded-xl border-gray-200 bg-white text-sm focus:border-primary focus:ring-primary md:w-96"
                                :aria-label="`Pilih dosen untuk ${match.input}`"
                                :disabled="processing"
                                @change="resolve(match, ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="">Pilih dosen…</option>
                                <optgroup v-if="match.candidates.length" label="Saran">
                                    <option v-for="c in match.candidates" :key="`c-${c.id}`" :value="c.id">{{ lecturerName(c) }} ({{ c.study_program_code }})</option>
                                </optgroup>
                                <optgroup label="Semua dosen">
                                    <option v-for="o in preview.lecturer_options" :key="`o-${o.id}`" :value="o.id">{{ lecturerName(o) }} ({{ o.study_program_code }})</option>
                                </optgroup>
                            </select>
                        </div>
                        <div v-for="match in resolvedMatches" :key="match.key" class="flex items-center justify-between rounded-lg border border-gray-200 p-3 text-sm">
                            <p>
                                <span class="font-semibold text-gray-900">"{{ match.input }}"</span>
                                <span class="text-gray-500"> → {{ lecturerName(match.lecturer) }} (dipilih manual)</span>
                            </p>
                            <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-900" :disabled="processing" @click="unresolve(match)">Batalkan</button>
                        </div>
                    </Card>

                    <!-- Errors and warnings -->
                    <Card v-if="preview.errors.length || preview.warnings.length" class="space-y-3 border border-gray-100 shadow-sm">
                        <div v-if="preview.errors.length">
                            <h2 class="text-sm font-bold text-red-700">Error ({{ preview.errors.length }})</h2>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(issue, i) in preview.errors" :key="`e-${i}`" class="text-red-700">
                                    <span class="font-semibold">Baris {{ issue.row }} · {{ issue.column }}</span>
                                    <span v-if="issue.value !== null && issue.value !== undefined" class="text-red-500"> ("{{ issue.value }}")</span>
                                    · {{ issue.message }}
                                </li>
                            </ul>
                        </div>
                        <div v-if="preview.warnings.length">
                            <h2 class="text-sm font-bold text-amber-700">Peringatan ({{ preview.warnings.length }})</h2>
                            <ul class="mt-2 space-y-1 text-sm text-amber-700">
                                <li v-for="(issue, i) in preview.warnings" :key="`w-${i}`">
                                    <span class="font-semibold">Baris {{ issue.row }} · {{ issue.column }}</span> · {{ issue.message }}
                                </li>
                            </ul>
                        </div>
                    </Card>

                    <!-- Courses -->
                    <Card class="!p-0 border border-gray-100 shadow-sm">
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
                                    <tr v-for="course in sortedCourses" :key="course.code" class="align-top" :class="{ 'bg-red-50/60': course.status === 'invalid' }">
                                        <td class="px-4 py-3">
                                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold" :class="statusLabel[course.status].class">{{ statusLabel[course.status].text }}</span>
                                            <span class="mt-1 block text-[10px] text-gray-400">Baris {{ course.rows.join(', ') }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-[10px] font-bold text-blue-700">{{ course.code }}</span>
                                            <span class="block font-bold text-gray-900">{{ course.fields.name }}</span>
                                            <ul v-if="course.changes.length" class="mt-1 text-[11px] text-amber-700">
                                                <li v-for="c in course.changes" :key="c.field">{{ fieldLabel[c.field] ?? c.field }}: {{ c.from }} → {{ c.to }}</li>
                                            </ul>
                                        </td>
                                        <td class="px-4 py-3 text-center">{{ course.fields.sks }}</td>
                                        <td class="px-4 py-3 text-center">{{ course.fields.semester }}</td>
                                        <td class="px-4 py-3 text-center">{{ course.fields.class_capacity }}</td>
                                        <td class="px-4 py-3">
                                            <ul class="space-y-0.5">
                                                <li v-for="cls in course.classes" :key="cls.number" :class="{ 'text-gray-400 line-through': cls.change === 'removed' }">
                                                    <span class="font-semibold">{{ cls.label }}</span> · {{ lecturerName(cls.lecturer) }}
                                                    <span v-if="cls.change === 'lecturer_changed'" class="text-amber-700">(sebelumnya {{ lecturerName(cls.previous_lecturer) }})</span>
                                                    <span v-else-if="classChange[cls.change] && course.status !== 'created'" class="text-amber-700">({{ classChange[cls.change] }})</span>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <div class="flex flex-col items-end gap-2">
                        <Button :disabled="!preview.can_commit || locked" :loading="processing" @click="send(false)">Import {{ preview.summary.courses }} Mata Kuliah</Button>
                        <p v-if="!preview.can_commit" class="text-xs text-red-600">Tombol aktif setelah semua error diperbaiki.</p>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>