<script setup lang="ts">
import { computed, ref } from 'vue';
import { classLetter } from '@/lib/format';
import { colorOf, programColorMap } from '@/lib/programColors';
import type { RunResult } from '@/types/run';
import type { SchedulingDataset } from '@/types/scheduling';

const props = defineProps<{ result: RunResult; dataset: SchedulingDataset | null }>();

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
const programFilter = ref<number | null>(null);

const colors = computed(() => programColorMap(props.dataset?.study_programs ?? []));
const programs = computed(() => props.dataset?.study_programs.filter((p) => p.included) ?? []);

const lookup = computed(() => {
  const d = props.dataset;
  return {
    course: new Map(d?.courses.map((c) => [c.id, c]) ?? []),
    lecturer: new Map(d?.lecturers.map((l) => [l.id, l.name]) ?? []),
    room: new Map(d?.rooms.map((r) => [r.id, r.code]) ?? []),
    slot: new Map(d?.time_slots.map((s) => [s.id, s]) ?? []),
  };
});

const sessions = computed(() => {
  const slots = props.dataset?.time_slots ?? [];
  return [...new Set(slots.map((s) => s.session))].sort((a, b) => a - b).map((session) => ({
    session,
    start: slots.find((s) => s.session === session)?.start_time.slice(0, 5) ?? '',
  }));
});

const hardIndexes = computed(() => new Set(
  props.result.violations.filter((v) => v.constraint_code.startsWith('HC')).map((v) => v.detail_index),
));

const cells = computed(() => {
  const map = new Map<string, { key: number; code: string; name: string; meta: string; programId?: number; hard: boolean }[]>();
  props.result.schedule_details.forEach((d, i) => {
    const course = lookup.value.course.get(d.course_id);
    const slot = lookup.value.slot.get(d.time_slot_id);
    if (!slot) return;
    const key = `${slot.day}-${slot.session}`;
    const list = map.get(key) ?? [];
    const room = d.room_id ? lookup.value.room.get(d.room_id) ?? `Ruang ${d.room_id}` : 'Tanpa ruangan';
    list.push({
      key: i,
      code: `${course?.code ?? d.course_id} ${classLetter(d.class_number)}${d.meeting_number > 1 ? ` (${d.meeting_number})` : ''}`,
      name: course?.name ?? '',
      meta: `${room}, ${lookup.value.lecturer.get(d.lecturer_id) ?? d.lecturer_id}`,
      programId: course?.study_program_id,
      hard: hardIndexes.value.has(i),
    });
    map.set(key, list);
  });
  return map;
});

function chipStyle(programId: number | undefined, hard: boolean) {
  const c = colorOf(colors.value, programId);
  return {
    background: c.bg,
    borderColor: hard ? '#d92d20' : c.border,
    borderWidth: hard ? '2px' : '1px',
    opacity: programFilter.value !== null && programFilter.value !== programId ? 0.24 : 1,
  };
}

function toggleProgram(id: number) {
  programFilter.value = programFilter.value === id ? null : id;
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Sorot program studi">
      <button
        type="button"
        class="inline-flex h-8 items-center rounded-control border px-3 text-xs font-semibold transition-colors"
        :class="programFilter === null ? 'border-primary bg-primary-soft text-primary' : 'border-line-strong text-ink-secondary hover:bg-canvas'"
        @click="programFilter = null"
      >
        Semua program studi
      </button>
      <button
        v-for="p in programs"
        :key="p.id"
        type="button"
        :title="p.name"
        :aria-pressed="programFilter === p.id"
        class="inline-flex h-8 items-center gap-2 rounded-control border px-3 font-mono text-xs font-semibold transition-opacity"
        :style="{
          background: colorOf(colors, p.id).bg,
          borderColor: programFilter === p.id ? colorOf(colors, p.id).ink : colorOf(colors, p.id).border,
          color: colorOf(colors, p.id).ink,
          opacity: programFilter !== null && programFilter !== p.id ? 0.48 : 1,
        }"
        @click="toggleProgram(p.id)"
      >
        {{ p.code }}
      </button>
      <span class="ml-auto inline-flex items-center gap-2 text-xs text-ink-muted">
        <span class="size-3 rounded-badge border-2 border-danger bg-white" aria-hidden="true" />
        Melanggar hard constraint
      </span>
    </div>

    <div class="overflow-x-auto">
      <div class="grid min-w-[840px] grid-cols-[96px_repeat(5,minmax(144px,1fr))] gap-2">
        <span />
        <span v-for="day in DAYS" :key="day" class="pb-1 text-center text-xs font-semibold text-ink-muted">{{ day }}</span>
        <template v-for="s in sessions" :key="s.session">
          <span class="flex flex-col pt-2 text-xs">
            <span class="font-semibold text-ink-secondary">Sesi {{ s.session }}</span>
            <span class="tabular text-ink-faint">{{ s.start }}</span>
          </span>
          <div v-for="(_, d) in DAYS" :key="d" class="flex min-h-16 flex-col gap-2 rounded-control bg-canvas p-2">
            <div
              v-for="cell in cells.get(`${d + 1}-${s.session}`) ?? []"
              :key="cell.key"
              class="rounded-control border px-2 py-2 transition-opacity"
              :style="chipStyle(cell.programId, cell.hard)"
              :title="`${cell.code} ${cell.name}. ${cell.meta}${cell.hard ? '. Melanggar hard constraint.' : ''}`"
            >
              <p class="font-mono text-xs font-semibold" :style="{ color: colorOf(colors, cell.programId).ink }">{{ cell.code }}</p>
              <p class="mt-1 truncate text-xs font-medium text-ink">{{ cell.name }}</p>
              <p class="truncate text-xs text-ink-muted">{{ cell.meta }}</p>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
