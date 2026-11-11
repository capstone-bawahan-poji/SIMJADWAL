<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import SearchSelect from '@/Components/shared/SearchSelect.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useCollection } from '@/composables/useApiList';
import { usePeriodPicker } from '@/composables/usePeriodPicker';
import { onResourcesChanged } from '@/composables/useResourceChanges';
import { api, toApiError, type ApiError } from '@/lib/api';
import { classLabel, COHORT_LABELS, DAY_LABELS, formatTime } from '@/lib/labels';
import type { Schedule, ScheduleFilters, TimeSlot, TpbCohort } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// The published TPB schedule, laid out like the institute's spreadsheet: one block per group
// meeting (all its classes share the slot), filterable by program, cohort, course and lecturer.
type Detail = Schedule['schedule_details'][number];
type Block = {
    key: string;
    courseCode: string;
    courseName: string;
    groupCode: string;
    cohort: TpbCohort;
    meeting: number;
    programs: { id: number; code: string; name: string; target_semester: number | null }[];
    details: Detail[];
};

const controlClass = 'h-10 w-full rounded-lg border-gray-200 bg-white text-sm text-gray-700 focus:border-primary focus:ring-primary';
const COHORT_TONE: Record<TpbCohort, string> = {
    maba: 'border-blue-300 bg-blue-50',
    non_maba: 'border-indigo-300 bg-indigo-50',
    campuran: 'border-teal-300 bg-teal-50',
    mengulang: 'border-amber-300 bg-amber-50',
    pilihan: 'border-gray-300 bg-gray-50',
};

const { periods, period } = usePeriodPicker();
const { items: timeSlots } = useCollection<TimeSlot>('time-slots');
const schedule = ref<Schedule | null>(null);
const loading = ref(false);
const failure = ref<ApiError | null>(null);
const programFilter = ref<number | ''>('');
const cohortFilter = ref<TpbCohort | ''>('');
const courseFilter = ref<string | number>('');
const lecturerFilter = ref<string | number>('');
const exportOpen = ref(false);
const opened = ref<Block | null>(null);

const run = computed(() => schedule.value?.runs[0] ?? null);
const blocks = computed<Block[]>(() => {
    const map = new Map<string, Block>();
    for (const d of schedule.value?.schedule_details ?? []) {
        if (!d.tpb_group) continue;
        const key = `${d.tpb_group.id}:${d.meeting_number}`;
        const block = map.get(key) ?? {
            key,
            courseCode: d.course.code,
            courseName: d.course.name,
            groupCode: d.tpb_group.code,
            cohort: d.tpb_group.cohort,
            meeting: d.meeting_number,
            programs: d.tpb_group.study_programs,
            details: [],
        };
        block.details.push(d);
        map.set(key, block);
    }
    return [...map.values()];
});
const programs = computed(() => {
    const map = new Map<number, { id: number; code: string; name: string }>();
    blocks.value.forEach((b) => b.programs.forEach((p) => map.set(p.id, p)));
    return [...map.values()].sort((a, b) => a.code.localeCompare(b.code));
});
const courseOptions = computed(() => [...new Map(blocks.value.map((b) => [b.courseCode, b.courseName])).entries()]
    .sort((a, b) => a[1].localeCompare(b[1]))
    .map(([code, name]) => ({ value: code, label: name, hint: code })));
const lecturerOptions = computed(() => {
    const map = new Map<number, string>();
    blocks.value.forEach((b) => b.details.forEach((d) => map.set(d.lecturer.id, d.lecturer.name)));
    return [...map.entries()].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => ({ value: id, label: name }));
});
const visibleBlocks = computed(() => blocks.value.filter((b) =>
    (!programFilter.value || b.programs.some((p) => p.id === programFilter.value))
    && (!cohortFilter.value || b.cohort === cohortFilter.value)
    && (!courseFilter.value || b.courseCode === courseFilter.value)
    && (!lecturerFilter.value || b.details.some((d) => d.lecturer.id === lecturerFilter.value))));
const blocksByCell = computed(() => {
    const cells = new Map<string, Block[]>();
    for (const block of visibleBlocks.value) {
        const slot = block.details[0].time_slot;
        const key = `${slot.day}-${slot.session}`;
        cells.set(key, [...(cells.get(key) ?? []), block].sort((a, b) => a.courseName.localeCompare(b.courseName)));
    }
    return cells;
});
const openedSlot = computed(() => (opened.value ? opened.value.details[0].time_slot : null));
const days = computed(() => [...new Set(timeSlots.value.map((s) => s.day))].sort((a, b) => a - b));
const sessions = computed(() => [...new Set(timeSlots.value.map((s) => s.session))].sort((a, b) => a - b));
const EXPORT_FORMATS = [
    { format: 'xlsx', label: 'Excel (.xlsx)', hint: 'Matriks dan daftar jadwal' },
    { format: 'pdf', label: 'PDF', hint: 'Siap cetak, kertas A4 landscape' },
] as const;

function exportUrl(format: string): string {
    return `/api/internal/schedules/export?${new URLSearchParams({ period: period.value, scope: 'tpb', format }).toString()}`;
}

function onExportFocusOut(event: FocusEvent): void {
    if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) exportOpen.value = false;
}

function slotTime(day: number, session: number): string {
    const slot = timeSlots.value.find((s) => s.day === day && s.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

async function load(): Promise<void> {
    if (!period.value) return;
    loading.value = true;
    failure.value = null;
    try {
        const response = await api.get<{ data: Schedule }>('schedules', { params: { period: period.value, scope: 'tpb' } satisfies ScheduleFilters });
        schedule.value = response.data.data;
    } catch (e) {
        failure.value = toApiError(e);
    } finally {
        loading.value = false;
    }
}

watch(period, () => void load(), { immediate: true });
onResourcesChanged(['scheduling_runs', 'schedule_details'], () => void load());
</script>

<template>
    <Head title="Jadwal TPB" />
    <AppLayout>
        <div class="space-y-6">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Jadwal TPB</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        <template v-if="run">Hasil Run #{{ run.id }}, {{ run.published_at ? 'sudah dipublikasikan' : 'aktif tetapi belum dipublikasikan' }}. Tiap kotak adalah satu grup kelas, dan semua kelasnya kuliah di waktu yang sama.</template>
                        <template v-else>Belum ada jadwal TPB aktif untuk periode ini.</template>
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-sm font-medium text-gray-700">Periode
                        <select v-model="period" class="mt-1 block sm:w-56" :class="controlClass">
                            <option v-for="item in periods" :key="item.id" :value="item.code">{{ item.label }}</option>
                        </select>
                    </label>
                    <div v-if="run" class="relative" @focusout="onExportFocusOut" @keydown.esc="exportOpen = false">
                        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2" aria-haspopup="menu" :aria-expanded="exportOpen" @click="exportOpen = !exportOpen">
                            Unduh jadwal
                            <ChevronDown class="size-4 transition-transform" :class="exportOpen ? 'rotate-180' : ''" aria-hidden="true" />
                        </button>
                        <div v-if="exportOpen" role="menu" class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                            <a v-for="item in EXPORT_FORMATS" :key="item.format" :href="exportUrl(item.format)" role="menuitem" class="block rounded-md px-3 py-2 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" @click="exportOpen = false">
                                <span class="block text-sm font-semibold text-gray-900">{{ item.label }}</span>
                                <span class="block text-xs text-gray-500">{{ item.hint }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <Alert v-if="failure" :title="failure.message" />

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <select v-model="programFilter" :class="controlClass" aria-label="Filter program studi">
                    <option value="">Semua program studi</option>
                    <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="cohortFilter" :class="controlClass" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>
                    <option v-for="(label, value) in COHORT_LABELS" :key="value" :value="value">{{ label }}</option>
                </select>
                <SearchSelect v-model="courseFilter" :options="courseOptions" label="mata kuliah" placeholder="Cari mata kuliah" />
                <SearchSelect v-model="lecturerFilter" :options="lecturerOptions" label="dosen" placeholder="Cari dosen" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white" :class="{ 'opacity-60': loading }">
                <table class="w-full min-w-[1100px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                            <th class="w-28 px-4 py-3 text-left font-medium">Sesi</th>
                            <th v-for="day in days" :key="day" class="border-l border-gray-200 px-4 py-3 text-left font-medium">{{ DAY_LABELS[day] }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y-2 divide-gray-200">
                        <tr v-for="session in sessions" :key="session" class="align-top">
                            <th class="whitespace-nowrap px-4 py-4 text-left font-medium text-gray-900">Sesi {{ session }}</th>
                            <td v-for="day in days" :key="day" class="border-l border-gray-200 p-2">
                                <p class="mb-1.5 text-xs font-semibold tabular-nums text-gray-700">{{ slotTime(day, session) }}</p>
                                <div class="space-y-1.5">
                                    <button
                                        v-for="block in blocksByCell.get(`${day}-${session}`) ?? []"
                                        :key="block.key"
                                        type="button"
                                        class="block w-full rounded-lg border-l-4 px-2.5 py-2 text-left hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        :class="COHORT_TONE[block.cohort]"
                                        @click="opened = block"
                                    >
                                        <span class="block text-xs font-semibold leading-snug text-gray-900">{{ block.courseName }}</span>
                                        <span class="block text-[11px] text-gray-600">
                                            Kelas {{ block.details.map((d) => classLabel(d.class_number)).join(', ') }}
                                            <template v-if="block.meeting > 1"> · Pertemuan {{ block.meeting }}</template>
                                        </span>
                                        <span class="mt-0.5 line-clamp-2 block text-[11px] leading-snug text-gray-500" :title="block.programs.map((p) => p.name).join(', ')">{{ block.cohort === 'pilihan' ? 'Semua program studi' : block.programs.map((p) => p.name).join(', ') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="flex flex-wrap gap-4 text-xs text-gray-600" aria-label="Keterangan warna">
                <li v-for="(label, value) in COHORT_LABELS" :key="value" class="flex items-center gap-1.5"><span class="size-3 rounded border-l-4" :class="COHORT_TONE[value]" />{{ label }}</li>
            </ul>
        </div>

        <Modal :show="opened !== null" max-width="2xl" close-button @close="opened = null">
            <template v-if="opened && openedSlot">
                <div class="rounded-t-lg border-b border-gray-100 bg-gray-50 py-6 pl-6 pr-14">
                    <p class="text-xs text-gray-500"><span class="font-mono">{{ opened.courseCode }}</span> · Grup {{ opened.groupCode }} · {{ COHORT_LABELS[opened.cohort] }}</p>
                    <h2 class="mt-1 text-lg font-bold text-gray-900">{{ opened.courseName }}</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ DAY_LABELS[openedSlot.day] }}, sesi {{ openedSlot.session }} ({{ formatTime(openedSlot.start_time) }}–{{ formatTime(openedSlot.end_time) }})<template v-if="opened.meeting > 1"> · Pertemuan {{ opened.meeting }}</template>
                    </p>
                </div>
                <div class="space-y-5 p-6">
                    <section>
                        <h3 class="text-xs font-semibold text-gray-500">Program studi peserta</h3>
                        <p v-if="opened.cohort === 'pilihan'" class="mt-1 text-sm text-gray-900">Semua program studi</p>
                        <ul v-else class="mt-1 space-y-0.5 text-sm text-gray-900">
                            <li v-for="p in opened.programs" :key="p.id">{{ p.name }}<span v-if="p.target_semester" class="text-gray-500"> (semester {{ p.target_semester }})</span></li>
                        </ul>
                    </section>
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b text-xs font-semibold text-gray-500"><th class="py-2">Kelas</th><th class="py-2">Dosen pengampu</th><th class="py-2">Ruang</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="d in opened.details" :key="d.id">
                                <td class="py-2 font-semibold">{{ classLabel(d.class_number) }}</td>
                                <td class="py-2">{{ d.lecturer.name }}</td>
                                <td class="py-2">{{ d.room?.code ?? 'Belum ada ruang' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </Modal>
    </AppLayout>
</template>
