<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PreferenceGrid from '@/views/data/PreferenceGrid.vue';
import type { DatasetStudyProgram, SchedulingDataset } from '@/types/scheduling';

const props = defineProps<{
  program: DatasetStudyProgram;
  dataset: SchedulingDataset;
}>();

type Tab = 'courses' | 'preferences' | 'hard';
const tab = ref<Tab>('courses');
const TABS: { key: Tab; label: string }[] = [
  { key: 'courses', label: 'Mata Kuliah & Kelas' },
  { key: 'preferences', label: 'Preferensi Dosen' },
  { key: 'hard', label: 'Hard Constraint' },
];

const lecturerById = computed(() => new Map(props.dataset.lecturers.map((l) => [l.id, l])));

const courses = computed(() =>
  props.dataset.courses
    .filter((c) => c.study_program_id === props.program.id)
    .sort((a, b) => a.semester - b.semester || a.code.localeCompare(b.code))
    .map((course) => ({
      ...course,
      classes: props.dataset.classes
        .filter((k) => k.course_id === course.id)
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

watch(() => props.program.id, () => {
  tab.value = 'courses';
  selectedLecturerId.value = null;
});

function classLabel(n: number) {
  return String.fromCharCode(64 + n);
}
</script>

<template>
  <div>
    <div class="flex items-baseline justify-between gap-4">
      <div>
        <h2 class="text-lg font-bold text-gray-900">{{ program.name }}</h2>
        <p class="text-xs text-gray-400">{{ program.code }}</p>
      </div>
    </div>

    <p v-if="!program.included" class="mt-6 text-sm text-gray-500">
      Data prodi ini tidak termasuk dataset penjadwalan.
    </p>

    <template v-else>
      <div class="mt-4 flex gap-1 border-b border-gray-100">
        <button
          v-for="t in TABS"
          :key="t.key"
          type="button"
          class="px-3 py-2 text-sm font-semibold -mb-px border-b-2 transition-colors"
          :class="tab === t.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="tab = t.key"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- Mata kuliah & kelas -->
      <div v-if="tab === 'courses'" class="mt-4 overflow-x-auto">
        <p v-if="!courses.length" class="text-sm text-gray-500">Tidak ada mata kuliah pada periode ini.</p>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100">
              <th class="py-2 pr-3">Kode</th>
              <th class="py-2 pr-3">Mata Kuliah</th>
              <th class="py-2 pr-3 text-center">SKS</th>
              <th class="py-2 pr-3 text-center">Smt</th>
              <th class="py-2 pr-3">Kelas & Dosen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="course in courses" :key="course.id" class="border-b border-gray-50 align-top">
              <td class="py-2 pr-3 font-mono text-xs text-gray-600">{{ course.code }}</td>
              <td class="py-2 pr-3 text-gray-900">{{ course.name }}</td>
              <td class="py-2 pr-3 text-center text-gray-700">{{ course.sks }}</td>
              <td class="py-2 pr-3 text-center text-gray-700">{{ course.semester }}</td>
              <td class="py-2 pr-3">
                <p v-for="k in course.classes" :key="k.class_number" class="text-gray-700">
                  <span class="font-semibold">{{ classLabel(k.class_number) }}</span>
                  · {{ k.lecturer?.name ?? '–' }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Preferensi dosen -->
      <div v-else-if="tab === 'preferences'" class="mt-4 grid grid-cols-12 gap-4">
        <p v-if="!lecturers.length" class="col-span-12 text-sm text-gray-500">Belum ada dosen pengampu.</p>
        <template v-else>
          <ul class="col-span-4 max-h-96 overflow-y-auto space-y-1">
            <li v-for="l in lecturers" :key="l.id">
              <button
                type="button"
                class="w-full rounded-lg px-3 py-2 text-left text-sm transition-colors"
                :class="selectedLecturer?.id === l.id ? 'bg-primary/[0.08] text-primary font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                @click="selectedLecturerId = l.id"
              >
                {{ l.name }}
                <span class="block text-[11px] text-gray-400">{{ l.preferences.length }} preferensi</span>
              </button>
            </li>
          </ul>
          <div class="col-span-8">
            <PreferenceGrid v-if="selectedLecturer" :slots="dataset.time_slots" :preferences="selectedLecturer.preferences" />
          </div>
        </template>
      </div>

      <!-- Hard constraint -->
      <ul v-else class="mt-4 space-y-2">
        <li v-for="hc in hardConstraints" :key="hc.code" class="flex items-start gap-3 rounded-lg border border-gray-100 p-3">
          <span class="font-mono text-xs font-bold text-gray-700">{{ hc.code }}</span>
          <span class="flex-1 text-sm text-gray-700">{{ hc.description }}</span>
          <span class="text-xs text-gray-400">bobot {{ hc.weight }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>
