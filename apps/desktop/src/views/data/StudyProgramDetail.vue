<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { classLetter } from '@/lib/format';
import { colorOf, type ProgramColor } from '@/lib/programColors';
import type { DatasetStudyProgram, SchedulingDataset } from '@/types/scheduling';
import PreferenceGrid from '@/views/data/PreferenceGrid.vue';

const props = defineProps<{
  program: DatasetStudyProgram;
  dataset: SchedulingDataset;
  colors: Map<number, ProgramColor>;
}>();

type Tab = 'courses' | 'preferences' | 'hard';
const tab = ref<Tab>('courses');

const color = computed(() => colorOf(props.colors, props.program.id));
const lecturerById = computed(() => new Map(props.dataset.lecturers.map((l) => [l.id, l])));

const attendedGroups = computed(() => new Set(
  (props.dataset.tpb_groups ?? []).filter((g) => g.study_programs.some((p) => p.id === props.program.id)).map((g) => g.id),
));

const courses = computed(() =>
  props.dataset.courses
    .filter((c) => c.study_program_id === props.program.id
      || props.dataset.classes.some((k) => k.course_id === c.id && k.tpb_group_id != null && attendedGroups.value.has(k.tpb_group_id)))
    .sort((a, b) => (a.semester ?? 0) - (b.semester ?? 0) || a.code.localeCompare(b.code))
    .map((course) => ({
      ...course,
      classes: props.dataset.classes
        .filter((k) => k.course_id === course.id && (k.tpb_group_id == null || attendedGroups.value.has(k.tpb_group_id)))
        .map((k) => ({ ...k, lecturer: lecturerById.value.get(k.lecturer_id) })),
    })),
);

const lecturers = computed(() => {
  const ids = new Set(courses.value.flatMap((c) => c.classes.map((k) => k.lecturer_id)));
  return props.dataset.lecturers.filter((l) => ids.has(l.id)).sort((a, b) => a.name.localeCompare(b.name));
});

const selectedLecturerId = ref<number | null>(null);
const selectedLecturer = computed(() => lecturers.value.find((l) => l.id === selectedLecturerId.value) ?? lecturers.value[0]);

const hardConstraints = computed(() => props.dataset.constraint_types.filter((t) => t.category === 'HC'));

const tabs = computed(() => [
  { key: 'courses' as const, label: `Mata kuliah dan kelas (${courses.value.length})` },
  { key: 'preferences' as const, label: `Preferensi dosen (${lecturers.value.length})` },
  { key: 'hard' as const, label: 'Hard constraint' },
]);

watch(() => props.program.id, () => {
  tab.value = 'courses';
  selectedLecturerId.value = null;
});
</script>

<template>
  <div>
    <div class="flex items-center gap-4 px-6 pt-6">
      <span class="size-8 shrink-0 rounded-control border" :style="{ background: color.bg, borderColor: color.border }" aria-hidden="true" />
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-base font-semibold text-ink">{{ program.name }}</h2>
        <p class="font-mono text-xs text-ink-muted">{{ program.code }}</p>
      </div>
    </div>

    <div v-if="!program.included" class="p-6">
      <EmptyState title="Program studi tidak termasuk dataset" dashed>
        Program studi ini tidak termasuk dalam dataset penjadwalan sehingga algoritma genetika tidak menyusun jadwal untuknya.
      </EmptyState>
    </div>

    <template v-else>
      <div class="mt-4 px-6">
        <Tabs v-model="tab" :tabs="tabs" />
      </div>

      <div v-if="tab === 'courses'">
        <EmptyState v-if="!courses.length" title="Belum ada mata kuliah">Program studi ini tidak memiliki mata kuliah pada periode yang dipilih.</EmptyState>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-line text-left text-xs font-semibold text-ink-muted">
              <th class="py-3 pl-6 pr-4 font-semibold">Kode</th>
              <th class="py-3 pr-4 font-semibold">Mata kuliah</th>
              <th class="py-3 pr-4 text-right font-semibold">SKS</th>
              <th class="py-3 pr-4 text-right font-semibold">Smt</th>
              <th class="py-3 pr-6 font-semibold">Kelas dan dosen pengampu</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="course in courses" :key="course.id" class="align-top">
              <td class="py-3 pl-6 pr-4 font-mono text-xs text-ink-tertiary">{{ course.code }}</td>
              <td class="py-3 pr-4 text-ink">{{ course.name }}</td>
              <td class="tabular py-3 pr-4 text-right text-ink-secondary">{{ course.sks }}</td>
              <td class="tabular py-3 pr-4 text-right text-ink-secondary">{{ course.semester ?? 'TPB' }}</td>
              <td class="py-3 pr-6">
                <div class="flex flex-col gap-2">
                  <p v-for="k in course.classes" :key="k.class_number" class="flex items-center gap-2 text-ink-secondary">
                    <span
                      class="inline-flex size-6 shrink-0 items-center justify-center rounded-badge border text-xs font-semibold"
                      :style="{ background: color.bg, borderColor: color.border, color: color.ink }"
                    >{{ classLetter(k.class_number) }}</span>
                    <span class="truncate">{{ k.lecturer?.name ?? 'Dosen belum ditetapkan' }}</span>
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="tab === 'preferences'" class="p-6">
        <EmptyState v-if="!lecturers.length" title="Belum ada dosen pengampu" />
        <div v-else class="grid grid-cols-[240px_minmax(0,1fr)] gap-6">
          <ul class="flex max-h-[480px] flex-col gap-1 overflow-y-auto">
            <li v-for="l in lecturers" :key="l.id">
              <button
                type="button"
                class="w-full rounded-control px-3 py-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                :class="selectedLecturer?.id === l.id ? 'bg-primary-soft' : 'hover:bg-canvas'"
                @click="selectedLecturerId = l.id"
              >
                <span class="block truncate text-sm font-semibold" :class="selectedLecturer?.id === l.id ? 'text-primary' : 'text-ink'">{{ l.name }}</span>
                <span class="tabular block text-xs text-ink-muted">{{ l.preferences.length }} preferensi</span>
              </button>
            </li>
          </ul>
          <PreferenceGrid v-if="selectedLecturer" :slots="dataset.time_slots" :preferences="selectedLecturer.preferences" />
        </div>
      </div>

      <ul v-else class="divide-y divide-line-soft">
        <li v-for="hc in hardConstraints" :key="hc.code" class="flex items-center gap-4 px-6 py-4">
          <span class="w-28 shrink-0 whitespace-nowrap font-mono text-xs font-semibold text-ink-secondary">{{ hc.code }}</span>
          <span class="flex-1 text-sm text-ink-secondary">{{ hc.description }}</span>
          <span class="tabular text-xs text-ink-muted">Bobot {{ hc.weight }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>
