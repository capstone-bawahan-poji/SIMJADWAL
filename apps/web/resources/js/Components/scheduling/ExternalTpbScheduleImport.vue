<script setup lang="ts">
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { fetchAll } from '@/composables/useApiList';
import { api, toApiError, type ApiError } from '@/lib/api';
import { DAY_LABELS, formatTime } from '@/lib/labels';
import type { Collection, Room, TimeSlot } from '@/types/models';
import ExcelJS from 'exceljs';
import { FileSpreadsheet, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Field = 'courseName' | 'courseCode' | 'day' | 'session' | 'credits' | 'room' | 'classSize';
type ImportedRow = {
    row: number;
    courseName: string;
    courseCode: string;
    day: number | null;
    session: number | null;
    credits: number | null;
    roomCode: string;
    classSize: number | null;
    room: Room | null;
    slot: TimeSlot | null;
    issues: string[];
    capacityWarning: boolean;
};

const HEADERS: Record<Field, { label: string; aliases: string[] }> = {
    courseName: { label: 'Nama Mata Kuliah', aliases: ['namamatakuliah', 'matakuliah', 'namamk', 'course', 'coursename'] },
    courseCode: { label: 'Kode MK', aliases: ['kodemk', 'kodematakuliah', 'kodecourse', 'coursecode', 'kode'] },
    day: { label: 'Hari', aliases: ['hari', 'day'] },
    session: { label: 'Sesi', aliases: ['sesi', 'session'] },
    credits: { label: 'SKS', aliases: ['sks', 'kredit', 'credits'] },
    room: { label: 'Ruang', aliases: ['ruang', 'ruangan', 'koderuang', 'namaruang', 'room', 'roomcode', 'classroom'] },
    classSize: { label: 'Jumlah Peserta', aliases: ['jumlahpeserta', 'peserta', 'kapasitas', 'kapasitaskelas', 'jumlahmahasiswa', 'students'] },
};

const TEMPLATE_HEADERS = Object.values(HEADERS).map(({ label }) => label);
const TEMPLATE_EXAMPLE = ['Matematika 1', 'KU2512015', 'Senin', 1, 3, 'E102', 38];
const DAYS = Object.entries(DAY_LABELS).map(([number, label]) => [Number(number), label] as const);

const { periods, period, error: periodError } = usePeriodPicker();
const rooms = ref<Room[]>([]);
const timeSlots = ref<TimeSlot[]>([]);
const loadingReferences = ref(true);
const referenceError = ref<ApiError | null>(null);
const file = ref<File | null>(null);
const rows = ref<ImportedRow[]>([]);
const fileError = ref<string | null>(null);
const dragging = ref(false);
const reading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const roomByCode = computed(() => new Map(rooms.value.flatMap((room) => [
    [normalize(room.code), room],
    ...(room.name ? [[normalize(room.name), room] as [string, Room]] : []),
])));
const usedPairs = computed(() => new Set(rows.value.flatMap((row) =>
    row.room && row.slot ? [`${row.room.id}:${row.slot.id}`] : [],
)));
const validRows = computed(() => rows.value.filter((row) => row.issues.length === 0));
const availableRows = computed(() => [...timeSlots.value]
    .sort((a, b) => a.day - b.day || a.session - b.session)
    .map((slot) => ({
        slot,
        availableRooms: rooms.value.filter((room) => !usedPairs.value.has(`${room.id}:${slot.id}`)).sort((a, b) => a.code.localeCompare(b.code)),
    })));
const matrixDays = computed(() => [...new Set(timeSlots.value.map((slot) => slot.day))].sort((a, b) => a - b));
const matrixSessions = computed(() => [...new Set(timeSlots.value.map((slot) => slot.session))].sort((a, b) => a - b));
const scheduleMatrix = computed(() => matrixDays.value.map((day) => ({
    day,
    cells: matrixSessions.value.map((session) => {
        const slot = timeSlots.value.find((item) => item.day === day && item.session === session);
        const availability = availableRows.value.find((item) => item.slot.id === slot?.id);

        return {
            session,
            slot,
            availableRooms: availability?.availableRooms ?? [],
        };
    }),
})));
const availablePairCount = computed(() => availableRows.value.reduce((sum, item) => sum + item.availableRooms.length, 0));
const issueCount = computed(() => rows.value.reduce((sum, row) => sum + row.issues.length, 0));
const unrecognizedRoomCount = computed(() => rows.value.filter((row) => !row.room).length);
const capacityWarningCount = computed(() => rows.value.filter((row) => row.capacityWarning).length);
const periodLabel = computed(() => periods.value.find((item) => item.code === period.value)?.label ?? period.value);
const controlClass = 'h-10 rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';

function normalize(value: string): string {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().replace(/[^a-z0-9]/g, '');
}

function numericValue(value: unknown): number | null {
    const match = String(value ?? '').replace(',', '.').match(/\d+(?:\.\d+)?/);
    if (!match) return null;
    const number = Number(match[0]);
    return Number.isFinite(number) ? number : null;
}

function dayValue(value: unknown): number | null {
    const text = normalize(String(value ?? ''));
    const byName = DAYS.find(([, label]) => normalize(label) === text);
    if (byName) return byName[0];
    const number = numericValue(value);
    return number !== null && number >= 1 && number <= 7 ? number : null;
}

function cellText(value: unknown): string {
    return String(value ?? '').trim();
}

function parseRows(sheetRows: unknown[][]): ImportedRow[] {
    const header = (sheetRows[0] ?? []).map((value) => normalize(cellText(value)));
    const columns = new Map<Field, number>();
    for (const [field, config] of Object.entries(HEADERS) as [Field, typeof HEADERS[Field]][]) {
        const index = header.findIndex((value) => config.aliases.includes(value));
        if (index >= 0) columns.set(field, index);
    }

    const missing = (Object.keys(HEADERS) as Field[]).filter((field) => !columns.has(field));
    if (missing.length) {
        throw new Error(`Kolom wajib tidak ditemukan: ${missing.map((field) => HEADERS[field].label).join(', ')}.`);
    }

    return sheetRows.slice(1).flatMap((values, index) => {
        if (values.every((value) => !cellText(value))) return [];
        const get = (field: Field) => values[columns.get(field)!];
        const day = dayValue(get('day'));
        const session = numericValue(get('session'));
        const credits = numericValue(get('credits'));
        const classSize = numericValue(get('classSize'));
        const courseName = cellText(get('courseName'));
        const courseCode = cellText(get('courseCode'));
        const roomCode = cellText(get('room'));
        const room = roomByCode.value.get(normalize(roomCode)) ?? null;
        const slot = day !== null && session !== null
            ? timeSlots.value.find((item) => item.day === day && item.session === session) ?? null
            : null;
        const issues: string[] = [];

        if (!courseName) issues.push('Nama mata kuliah kosong.');
        if (!courseCode) issues.push('Kode MK kosong.');
        if (day === null) issues.push('Hari tidak dikenali.');
        if (session === null) issues.push('Nomor sesi tidak valid.');
        else if (!slot) issues.push('Hari dan sesi tidak ditemukan di data slot waktu.');
        if (credits === null) issues.push('SKS tidak valid.');
        if (!roomCode) issues.push('Ruang kosong.');
        else if (!room) issues.push(`Ruang ${roomCode} tidak ditemukan di data fakultas.`);
        if (classSize === null) issues.push('Jumlah peserta tidak valid.');

        return [{
            row: index + 2,
            courseName,
            courseCode,
            day,
            session,
            credits,
            roomCode,
            classSize,
            room,
            slot,
            issues,
            capacityWarning: room !== null && classSize !== null && classSize > room.capacity,
        }];
    });
}

async function loadReferences(): Promise<void> {
    loadingReferences.value = true;
    referenceError.value = null;
    try {
        const [roomRows, slotResponse] = await Promise.all([
            fetchAll<Room>('rooms'),
            api.get<Collection<TimeSlot>>('time-slots'),
        ]);
        rooms.value = roomRows;
        timeSlots.value = slotResponse.data.data;
    } catch (error) {
        referenceError.value = toApiError(error);
    } finally {
        loadingReferences.value = false;
    }
}

async function chooseFile(next: File | null): Promise<void> {
    file.value = next;
    rows.value = [];
    fileError.value = null;
    if (!next) return;
    if (!/\.xlsx$/i.test(next.name)) {
        fileError.value = 'Pilih file Excel dengan format .xlsx.';
        return;
    }
    if (loadingReferences.value || referenceError.value) {
        fileError.value = 'Data ruang dan slot waktu belum siap. Periksa kembali setelah data selesai dimuat.';
        return;
    }

    reading.value = true;
    try {
        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(await next.arrayBuffer());
        const sheet = workbook.worksheets[0];
        if (!sheet) throw new Error('File Excel tidak memiliki sheet.');
        const sheetRows: string[][] = [];
        sheet.eachRow({ includeEmpty: false }, (row) => {
            const values: string[] = [];
            row.eachCell({ includeEmpty: true }, (cell, column) => {
                values[column - 1] = cell.text;
            });
            sheetRows.push(values);
        });
        const parsed = parseRows(sheetRows);
        if (parsed.length === 0) throw new Error('Tidak ada baris jadwal pada sheet pertama.');
        rows.value = parsed;
    } catch (error) {
        fileError.value = error instanceof Error ? error.message : 'File Excel tidak dapat dibaca.';
    } finally {
        reading.value = false;
    }
}

function dropFile(event: DragEvent): void {
    dragging.value = false;
    void chooseFile(event.dataTransfer?.files?.[0] ?? null);
}

function clearFile(): void {
    file.value = null;
    rows.value = [];
    fileError.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function downloadTemplate(): void {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Jadwal TPB');
    sheet.addRow(TEMPLATE_HEADERS);
    sheet.addRow(TEMPLATE_EXAMPLE);
    void workbook.xlsx.writeBuffer().then((buffer) => {
        const blob = new Blob([new Uint8Array(buffer)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'template-jadwal-tpb-eksternal.xlsx';
        link.click();
        URL.revokeObjectURL(url);
    }).catch((error: unknown) => {
        fileError.value = error instanceof Error ? `Template gagal dibuat: ${error.message}` : 'Template gagal dibuat.';
    });
}

function dayLabel(day: number): string {
    return DAY_LABELS[day] ?? `Hari ${day}`;
}

function sessionTime(slot: TimeSlot): string {
    return `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}`;
}

onMounted(() => void loadReferences());
</script>

<template>
    <div class="space-y-6">
        <Alert v-if="periodError" :title="periodError.message" />
        <Alert v-if="referenceError" :title="referenceError.message" />

        <section class="space-y-4" aria-label="Import jadwal TPB">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <label class="block w-full max-w-xs text-sm font-semibold text-gray-700">Periode jadwal
                    <select v-model="period" class="mt-1 block w-full" :class="controlClass" :disabled="loadingReferences || rows.length > 0">
                        <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                    </select>
                </label>
                <Button type="button" variant="secondary" @click="downloadTemplate">Unduh template Excel</Button>
            </div>

            <p class="text-sm text-gray-600">
                Kolom: Nama Mata Kuliah, Kode MK, Hari, Sesi, SKS, Ruang, dan Jumlah Peserta. Satu baris untuk tiap kelas. File dibaca di browser dan tidak disimpan ke server.
            </p>

            <label
                class="group relative flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed px-6 py-6 text-center transition"
                :class="[
                    dragging ? 'border-primary bg-blue-50' : file ? 'border-blue-300 bg-blue-50/50' : 'border-gray-300 hover:border-primary/50 hover:bg-blue-50/30',
                    loadingReferences || reading ? 'cursor-not-allowed opacity-60' : '',
                ]"
                @dragenter.prevent="dragging = true"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="dropFile"
            >
                <input ref="fileInput" type="file" accept=".xlsx" class="sr-only" :disabled="loadingReferences || reading" @change="void chooseFile(($event.target as HTMLInputElement).files?.[0] ?? null)" />
                <FileSpreadsheet class="size-7 text-primary" aria-hidden="true" />
                <span class="mt-2 max-w-full truncate text-sm font-semibold text-gray-900">{{ reading ? 'Membaca file…' : file ? file.name : 'Pilih atau seret file Excel jadwal TPB' }}</span>
                <span class="mt-0.5 text-xs text-gray-500">{{ file ? `${rows.length} baris terbaca · klik untuk mengganti file` : 'Format .xlsx' }}</span>
                <button v-if="file" type="button" class="absolute right-3 top-3 rounded-md p-1.5 text-gray-400 hover:bg-white hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" aria-label="Hapus file" @click.stop.prevent="clearFile">
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </label>
            <Alert v-if="fileError" :title="fileError" />
        </section>

        <template v-if="rows.length">
            <div>
                <h2 class="text-base font-bold text-gray-900">Hasil import jadwal TPB</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ file?.name }} · {{ periodLabel }}</p>
            </div>

            <Alert v-if="issueCount > 0" variant="warning" :title="`${issueCount} masalah ditemukan pada data import.`">
                Baris dengan ruang atau sesi yang tidak dikenali tidak mengurangi ketersediaan.
            </Alert>
            <Alert v-if="unrecognizedRoomCount > 0" variant="warning" :title="`${unrecognizedRoomCount} baris tidak cocok dengan data ruang fakultas.`">
                Pastikan nama ruang di Excel sesuai dengan daftar ruang fakultas.
            </Alert>
            <Alert v-if="capacityWarningCount > 0" variant="warning" :title="`${capacityWarningCount} kelas melebihi kapasitas ruang terdaftar.`">
                Periksa jumlah peserta kelas dan kapasitas ruang.
            </Alert>

            <section>
                <div class="border-b border-gray-200 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Ruang kosong per sesi</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ periodLabel }}</p>
                </div>
                <div class="overflow-auto border-b border-gray-200">
                    <table class="w-full min-w-[1100px] border-collapse text-left text-sm">
                        <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50 text-gray-700">
                            <tr>
                                <th class="sticky left-0 z-20 min-w-28 border-r border-gray-200 bg-gray-50 px-5 py-4 font-semibold">Hari</th>
                                <th v-for="session in matrixSessions" :key="session" class="min-w-48 border-r border-gray-200 px-5 py-4 text-left font-semibold last:border-r-0">
                                    <span class="block">Sesi {{ session }}</span>
                                    <span class="mt-1 block text-xs font-normal text-gray-500">{{ timeSlots.find((slot) => slot.session === session) ? sessionTime(timeSlots.find((slot) => slot.session === session)!) : '' }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in scheduleMatrix" :key="row.day" class="align-top">
                                <th class="sticky left-0 z-[5] whitespace-nowrap border-r border-gray-200 bg-white px-5 py-5 text-left font-semibold text-gray-900">{{ dayLabel(row.day) }}</th>
                                <td v-for="cell in row.cells" :key="cell.session" class="border-r border-gray-100 px-5 py-5 last:border-r-0">
                                    <template v-if="cell.slot">
                                        <ul v-if="cell.availableRooms.length" class="flex flex-wrap gap-x-2 gap-y-2 text-sm leading-6 text-gray-700">
                                            <li v-for="room in cell.availableRooms" :key="room.id" class="whitespace-nowrap">{{ room.code }}</li>
                                        </ul>
                                        <span v-else class="text-sm text-gray-400">Tidak ada ruang kosong</span>
                                    </template>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
