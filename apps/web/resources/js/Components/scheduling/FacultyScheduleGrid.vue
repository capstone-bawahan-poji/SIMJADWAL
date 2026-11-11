<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import { classLabel, DAY_LABELS, formatTime } from '@/lib/labels';
import { programTone } from '@/lib/programTone';
import type { ScheduleDetailBase, TimeSlot } from '@/types/models';
import { computed, ref, watch } from 'vue';

type Detail = ScheduleDetailBase & { violations_count?: number };
type Program = { id: number; code: string; name: string };

const props = defineProps<{
    details: Detail[];
    timeSlots: TimeSlot[];
    days: number[];
    fallbackProgram?: Program | null;
}>();

const opened = ref<Detail | null>(null);
const sessions = computed(() => [...new Set(props.timeSlots.map((slot) => slot.session))].sort((a, b) => a - b));
const cells = computed(() => {
    const result = new Map<string, Detail[]>();
    for (const detail of props.details) {
        const key = `${detail.time_slot.day}-${detail.time_slot.session}`;
        result.set(key, [...(result.get(key) ?? []), detail]);
    }
    for (const details of result.values()) {
        details.sort((a, b) => a.course.code.localeCompare(b.course.code) || a.class_number - b.class_number || a.meeting_number - b.meeting_number);
    }
    return result;
});
const programs = computed(() => {
    const result = new Map<number, Program>();
    for (const detail of props.details) {
        const program = programOf(detail);
        if (program) result.set(program.id, program);
    }
    return [...result.values()].sort((a, b) => a.code.localeCompare(b.code));
});

function programOf(detail: Detail): Program | null {
    return detail.course.study_program ?? (detail.course.is_tpb ? null : props.fallbackProgram ?? null);
}

function slotTime(day: number, session: number): string {
    const slot = props.timeSlots.find((item) => item.day === day && item.session === session);
    return slot ? `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}` : '';
}

watch(() => props.details, () => { opened.value = null; });
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white">
        <table class="w-full min-w-[1100px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                    <th scope="col" class="sticky left-0 z-10 w-28 bg-gray-50 px-4 py-3 text-left font-medium">Sesi</th>
                    <th v-for="day in days" :key="day" scope="col" class="min-w-52 border-l border-gray-200 px-4 py-3 text-left font-medium">{{ DAY_LABELS[day] ?? day }}</th>
                </tr>
            </thead>
            <tbody class="divide-y-2 divide-gray-200">
                <tr v-for="session in sessions" :key="session" class="align-top">
                    <th scope="row" class="sticky left-0 z-10 whitespace-nowrap bg-white px-4 py-4 text-left font-medium text-gray-900">Sesi {{ session }}</th>
                    <td v-for="day in days" :key="day" class="border-l border-gray-200 p-2">
                        <p class="mb-1.5 text-xs font-semibold tabular-nums text-gray-700">{{ slotTime(day, session) }}</p>
                        <div class="space-y-1.5">
                            <button
                                v-for="detail in cells.get(`${day}-${session}`) ?? []"
                                :key="detail.id"
                                type="button"
                                class="block w-full rounded-lg border-l-4 px-2.5 py-2 text-left hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                :class="programTone(programOf(detail)?.code).card"
                                @click="opened = detail"
                            >
                                <span class="block text-xs font-semibold leading-snug text-gray-900">{{ detail.course.name }}</span>
                                <span class="block text-[11px] text-gray-600">{{ detail.course.code }} · Kelas {{ classLabel(detail.class_number) }}<template v-if="detail.meeting_number > 1"> · Pertemuan {{ detail.meeting_number }}</template></span>
                                <span class="mt-0.5 block text-[11px] text-gray-600">{{ programOf(detail)?.code ?? (detail.course.is_tpb ? 'TPB' : 'Prodi tidak tersedia') }} · {{ detail.room?.code ?? 'Belum mendapat ruang' }}</span>
                                <span v-if="detail.violations_count" class="mt-0.5 block text-[11px] font-medium text-amber-800">{{ detail.violations_count }} pelanggaran tercatat</span>
                                <span v-if="detail.is_overridden" class="mt-0.5 block text-[11px] text-gray-600">Diubah manual</span>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <ul v-if="programs.length" class="flex flex-wrap gap-4 text-xs text-gray-600" aria-label="Keterangan warna program studi">
        <li v-for="program in programs" :key="program.id" class="flex items-center gap-1.5">
            <span class="size-3 rounded-sm" :class="programTone(program.code).dot" aria-hidden="true" />{{ program.code }} · {{ program.name }}
        </li>
    </ul>

    <Modal :show="opened !== null" max-width="lg" close-button centered @close="opened = null">
        <template v-if="opened">
            <div class="border-b border-gray-100 bg-gray-50 py-6 pl-6 pr-14">
                <p class="text-xs text-gray-500">{{ opened.course.code }} · {{ programOf(opened)?.code ?? (opened.course.is_tpb ? 'TPB' : 'Prodi tidak tersedia') }}</p>
                <h2 class="mt-1 text-lg font-bold text-gray-900">{{ opened.course.name }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ DAY_LABELS[opened.time_slot.day] }}, sesi {{ opened.time_slot.session }} ({{ formatTime(opened.time_slot.start_time) }}–{{ formatTime(opened.time_slot.end_time) }})</p>
            </div>
            <dl class="grid gap-4 p-6 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Kelas</dt><dd class="font-semibold text-gray-900">{{ classLabel(opened.class_number) }} · Pertemuan {{ opened.meeting_number }}</dd></div>
                <div><dt class="text-gray-500">Program studi</dt><dd class="font-semibold text-gray-900">{{ programOf(opened)?.name ?? (opened.course.is_tpb ? 'TPB' : 'Tidak tersedia') }}</dd></div>
                <div><dt class="text-gray-500">Dosen</dt><dd class="font-semibold text-gray-900">{{ opened.lecturer.name }}<template v-if="opened.lecturer.title">, {{ opened.lecturer.title }}</template></dd></div>
                <div><dt class="text-gray-500">Ruangan</dt><dd class="font-semibold text-gray-900">{{ opened.room?.code ?? 'Belum mendapat ruang' }}</dd></div>
                <div v-if="opened.course.semester !== null"><dt class="text-gray-500">Semester</dt><dd class="font-semibold text-gray-900">{{ opened.course.semester }}</dd></div>
                <div v-if="opened.tpb_group"><dt class="text-gray-500">Grup TPB</dt><dd class="font-semibold text-gray-900">{{ opened.tpb_group.code }}</dd></div>
                <div v-if="opened.violations_count"><dt class="text-gray-500">Pelanggaran tercatat</dt><dd class="font-semibold text-amber-800">{{ opened.violations_count }}</dd></div>
                <div v-if="opened.is_overridden"><dt class="text-gray-500">Perubahan</dt><dd class="font-semibold text-gray-900">Diubah manual</dd></div>
            </dl>
            <div class="flex justify-end border-t border-gray-100 px-6 py-4"><Button variant="secondary" @click="opened = null">Tutup</Button></div>
        </template>
    </Modal>
</template>
