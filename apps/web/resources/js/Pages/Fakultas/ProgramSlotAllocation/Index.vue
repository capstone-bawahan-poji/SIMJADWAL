<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Alert from '@/Components/ui/Alert.vue';
import Button from '@/Components/ui/Button.vue';
import {
    FACULTY_PROGRAMS,
    FACULTY_PROGRAM_CLASSES,
    FACULTY_PROGRAM_SLOT_STORAGE_KEY,
    type FacultyProgramSlotAllocation,
} from '@/lib/facultyProgramSlotAllocation';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type Building = 'E' | 'F' | 'G';
type Allocation = FacultyProgramSlotAllocation;

const DAYS = [
    { id: 1, label: 'Senin' },
    { id: 2, label: 'Selasa' },
    { id: 3, label: 'Rabu' },
    { id: 4, label: 'Kamis' },
    { id: 5, label: 'Jumat' },
] as const;
const SESSIONS = [
    { id: 1, time: '07.30–10.00' },
    { id: 2, time: '10.20–12.00' },
    { id: 3, time: '13.00–15.30' },
    { id: 4, time: '16.00–17.40' },
] as const;
const PROGRAMS = FACULTY_PROGRAMS;
const CLASSES = FACULTY_PROGRAM_CLASSES;
const ROOMS: Record<Building, string[]> = {
    E: ['E101', 'E102', 'E103', 'E104', 'E105', 'E106', 'E201', 'E202', 'E203', 'E204', 'E205', 'E206'],
    F: ['F101', 'F102', 'F103', 'F104', 'F105', 'F106', 'F201', 'F202', 'F203', 'F204', 'F205', 'F206'],
    G: ['G101', 'G102', 'G103', 'G104', 'G105', 'G106', 'G201', 'G202', 'G203', 'G204', 'G205', 'G206'],
};
const TPB_OCCUPIED = new Set([
    'E101:1:1', 'E102:1:1', 'E103:2:2', 'E201:3:3', 'E202:4:2',
    'F101:1:2', 'F102:2:1', 'F201:3:4', 'F202:5:2',
    'G101:1:3', 'G102:2:3', 'G201:4:1', 'G202:5:4',
]);

const building = ref<Building>('E');
const programId = ref<(typeof PROGRAMS)[number]['id']>(PROGRAMS[0].id);
const classLabel = ref<(typeof CLASSES)[number]>('A');
const dayId = ref<number>(DAYS[0].id);
const allocations = ref<Record<string, Allocation>>({});
const savedAllocations = ref('');
const saveError = ref('');
const selectedProgram = computed(() => PROGRAMS.find((program) => program.id === programId.value) ?? PROGRAMS[0]);
const rooms = computed(() => ROOMS[building.value]);
const selectedDay = computed(() => DAYS.find((day) => day.id === dayId.value) ?? DAYS[0]);
const allocationCount = computed(() => Object.values(allocations.value).filter((allocation) => allocation.programId === programId.value).length);
const selectedClassCount = computed(() => Object.values(allocations.value).filter((allocation) => allocation.programId === programId.value && allocation.classLabel === classLabel.value).length);
const hasUnsavedChanges = computed(() => JSON.stringify(allocations.value) !== savedAllocations.value);
const allocationRows = computed(() => Object.entries(allocations.value).map(([key, allocation]) => {
    const [room, dayId, sessionId] = key.split(':');
    const program = PROGRAMS.find((item) => item.id === allocation.programId);
    const day = DAYS.find((item) => item.id === Number(dayId));
    return {
        key,
        room,
        program: program ? `${program.code} · ${program.name}` : allocation.programId,
        classLabel: allocation.classLabel,
        day: day?.label ?? `Hari ${dayId}`,
        session: Number(sessionId),
    };
}).sort((a, b) => a.day.localeCompare(b.day) || a.session - b.session || a.room.localeCompare(b.room)));
const filtersClass = 'h-10 rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';

onMounted(() => {
    try {
        const stored = localStorage.getItem(FACULTY_PROGRAM_SLOT_STORAGE_KEY);
        if (!stored) {
            savedAllocations.value = JSON.stringify(allocations.value);
            return;
        }

        const parsed: unknown = JSON.parse(stored);
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            throw new Error('Format data alokasi tersimpan tidak valid.');
        }

        const entries = Object.entries(parsed);
        for (const [key, allocation] of entries) {
            const [room, day, session] = key.split(':');
            if (
                !Object.values(ROOMS).some((buildingRooms) => buildingRooms.includes(room))
                || !DAYS.some((item) => item.id === Number(day))
                || !SESSIONS.some((item) => item.id === Number(session))
                || !allocation
                || typeof allocation !== 'object'
                || !PROGRAMS.some((program) => program.id === allocation.programId)
                || !CLASSES.includes(allocation.classLabel as (typeof CLASSES)[number])
            ) {
                throw new Error('Format data alokasi tersimpan tidak valid.');
            }
        }

        allocations.value = parsed as Record<string, Allocation>;
        savedAllocations.value = JSON.stringify(allocations.value);
    } catch (error) {
        saveError.value = error instanceof Error ? error.message : 'Data alokasi tersimpan tidak dapat dibaca.';
    }
});

function slotKey(room: string, day: number, session: number): string {
    return `${room}:${day}:${session}`;
}

function isTpbOccupied(room: string, day: number, session: number): boolean {
    return TPB_OCCUPIED.has(slotKey(room, day, session));
}

function allocatedTo(room: string, day: number, session: number): Allocation | undefined {
    return allocations.value[slotKey(room, day, session)];
}

function allocate(room: string, day: number, session: number): void {
    const key = slotKey(room, day, session);
    if (isTpbOccupied(room, day, session)) return;

    const existing = allocations.value[key];
    if (existing?.programId === programId.value && existing.classLabel === classLabel.value) {
        const next = { ...allocations.value };
        delete next[key];
        allocations.value = next;
        return;
    }

    if (existing) return;
    allocations.value = { ...allocations.value, [key]: { programId: programId.value, classLabel: classLabel.value } };
}

function clearCurrentProgram(): void {
    allocations.value = Object.fromEntries(
        Object.entries(allocations.value).filter(([, allocation]) => allocation.programId !== programId.value),
    );
}

function removeAllocation(key: string): void {
    const next = { ...allocations.value };
    delete next[key];
    allocations.value = next;
}

function saveAllocations(): void {
    saveError.value = '';
    try {
        const serialized = JSON.stringify(allocations.value);
        localStorage.setItem(FACULTY_PROGRAM_SLOT_STORAGE_KEY, serialized);
        savedAllocations.value = serialized;
    } catch (error) {
        saveError.value = error instanceof Error ? error.message : 'Alokasi gagal disimpan di browser.';
    }
}
</script>

<template>
    <Head title="Input Alokasi Slot Prodi" />
    <AppLayout>
        <div class="mx-auto w-full max-w-[1280px] space-y-4 p-5 md:p-6">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Input Alokasi Slot Prodi</h1>
                <p class="mt-1 text-sm text-gray-500">Tentukan ruang, hari, dan sesi untuk kelas dari setiap program studi.</p>
            </header>

            <section class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1.3fr)_140px_170px_170px_auto] xl:items-end">
                <label class="block text-sm font-medium text-gray-700">
                    Program studi tujuan
                    <select v-model="programId" class="mt-1 block w-full" :class="filtersClass">
                        <option v-for="program in PROGRAMS" :key="program.id" :value="program.id">{{ program.code }} · {{ program.name }}</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Kelas
                    <select v-model="classLabel" class="mt-1 block w-full" :class="filtersClass">
                        <option v-for="label in CLASSES" :key="label" :value="label">Kelas {{ label }}</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Gedung
                    <select v-model="building" class="mt-1 block w-full" :class="filtersClass">
                        <option value="E">Gedung E</option>
                        <option value="F">Gedung F</option>
                        <option value="G">Gedung G</option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Hari
                    <select v-model.number="dayId" class="mt-1 block w-full" :class="filtersClass">
                        <option v-for="day in DAYS" :key="day.id" :value="day.id">{{ day.label }}</option>
                    </select>
                </label>
                <Button type="button" variant="secondary" size="sm" :disabled="allocationCount === 0" @click="clearCurrentProgram">
                    Hapus alokasi {{ selectedProgram.code }} ({{ allocationCount }})
                </Button>
            </section>

            <Alert v-if="saveError" variant="error" title="Gagal menyimpan alokasi">{{ saveError }}</Alert>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-600" aria-label="Legenda status slot">
                <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm bg-blue-100 ring-1 ring-blue-200" aria-hidden="true" />Kosong</span>
                <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm bg-gray-200" aria-hidden="true" />Dipakai TPB</span>
                <span class="inline-flex items-center gap-2"><i class="size-3 rounded-sm bg-primary" aria-hidden="true" />Sudah dialokasikan</span>
                <div class="flex w-full flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 sm:w-auto sm:flex-1 sm:border-0 sm:pt-0">
                    <span class="text-gray-500">{{ selectedProgram.code }} · Kelas {{ classLabel }}: {{ selectedClassCount }} slot dipilih</span>
                    <Button type="button" size="sm" :disabled="!hasUnsavedChanges" @click="saveAllocations">Simpan</Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-gray-200 bg-gray-50 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Pilih slot ruang</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ selectedDay.label }} · Gedung {{ building }} · klik slot kosong untuk mengalokasikan ke {{ selectedProgram.code }} kelas {{ classLabel }}</p>
                    </div>
                    <p class="text-xs font-medium text-gray-500">{{ rooms.length }} ruang · {{ SESSIONS.length }} sesi</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-20 min-w-28 border-b border-r border-gray-200 bg-gray-50 px-4 py-3 text-left font-semibold text-gray-700">Ruang</th>
                                <th v-for="session in SESSIONS" :key="session.id" class="min-w-32 border-b border-r border-gray-200 bg-gray-50 px-3 py-3 text-center font-semibold text-gray-700">
                                    <span class="block">Sesi {{ session.id }}</span>
                                    <span class="mt-0.5 block whitespace-nowrap text-[11px] font-normal text-gray-500">{{ session.time }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="room in rooms" :key="room">
                                <th class="sticky left-0 z-10 border-r border-gray-200 bg-white px-4 py-2.5 text-left font-semibold text-gray-900">{{ room }}</th>
                                <td v-for="session in SESSIONS" :key="`${room}:${dayId}:${session.id}`" class="border-r border-gray-100 p-1.5">
                                        <span
                                            v-if="isTpbOccupied(room, dayId, session.id)"
                                            class="flex min-h-10 items-center justify-center rounded-md bg-gray-100 px-2 text-xs font-medium text-gray-500"
                                            title="Ruang sudah dipakai TPB"
                                        >TPB</span>
                                        <button
                                            v-else
                                            type="button"
                                            class="flex min-h-10 w-full items-center justify-center rounded-md px-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                            :class="allocatedTo(room, dayId, session.id)
                                                ? allocatedTo(room, dayId, session.id)?.programId === programId && allocatedTo(room, dayId, session.id)?.classLabel === classLabel
                                                    ? 'bg-primary text-white'
                                                    : 'cursor-not-allowed bg-indigo-50 text-indigo-700'
                                                : 'bg-blue-50 text-gray-900 hover:bg-blue-100'"
                                            :disabled="!!allocatedTo(room, dayId, session.id) && !(allocatedTo(room, dayId, session.id)?.programId === programId && allocatedTo(room, dayId, session.id)?.classLabel === classLabel)"
                                            :aria-label="`${room}, ${selectedDay.label}, sesi ${session.id}${allocatedTo(room, dayId, session.id) ? `, dialokasikan ke ${allocatedTo(room, dayId, session.id)?.programId}, kelas ${allocatedTo(room, dayId, session.id)?.classLabel}` : ', kosong'}`"
                                            :title="allocatedTo(room, dayId, session.id)
                                                ? allocatedTo(room, dayId, session.id)?.programId === programId && allocatedTo(room, dayId, session.id)?.classLabel === classLabel ? `Dialokasikan ke ${selectedProgram.code} kelas ${classLabel}; klik untuk membatalkan` : `Sudah dialokasikan ke ${allocatedTo(room, dayId, session.id)?.programId} kelas ${allocatedTo(room, dayId, session.id)?.classLabel}`
                                                : `Alokasikan ${room} · ${selectedDay.label} sesi ${session.id} ke ${selectedProgram.code} kelas ${classLabel}`"
                                            @click="allocate(room, dayId, session.id)"
                                        >{{ allocatedTo(room, dayId, session.id) ? `${allocatedTo(room, dayId, session.id)?.programId} · ${allocatedTo(room, dayId, session.id)?.classLabel}` : 'Pilih slot' }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <section class="space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Daftar alokasi untuk prodi</h2>
                        <p class="text-sm text-gray-500">Ruang, kelas, hari, dan sesi yang sudah ditetapkan · {{ allocationRows.length }} alokasi</p>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3">Program studi</th>
                                <th class="px-4 py-3">Kelas</th>
                                <th class="px-4 py-3">Ruang</th>
                                <th class="px-4 py-3">Hari</th>
                                <th class="px-4 py-3">Sesi</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="allocationRows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada alokasi. Pilih prodi dan kelas, lalu klik slot kosong pada tabel.</td>
                            </tr>
                            <tr v-for="allocation in allocationRows" :key="allocation.key" class="text-gray-700">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ allocation.program }}</td>
                                <td class="px-4 py-3">Kelas {{ allocation.classLabel }}</td>
                                <td class="px-4 py-3 font-semibold">{{ allocation.room }}</td>
                                <td class="px-4 py-3">{{ allocation.day }}</td>
                                <td class="px-4 py-3">Sesi {{ allocation.session }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button type="button" size="sm" :aria-label="`Hapus alokasi ${allocation.room}, kelas ${allocation.classLabel}`" @click="removeAllocation(allocation.key)">Hapus</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
