<script setup lang="ts">
import type { ConstraintStatus, DatasetStudyProgram } from '@/types/scheduling';

defineProps<{
  programs: DatasetStudyProgram[];
  selectedId: number | null;
}>();

defineEmits<{ select: [id: number] }>();

const STATUS: Record<ConstraintStatus, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600' },
  submitted: { label: 'Disubmit', class: 'bg-blue-50 text-blue-700' },
  accepted: { label: 'Diterima', class: 'bg-green-50 text-green-700' },
};

function formatDate(value: string | null) {
  return value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–';
}
</script>

<template>
  <table class="w-full text-sm">
    <thead>
      <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100">
        <th class="py-3 px-4">Program Studi</th>
        <th class="py-3 px-4">Status</th>
        <th class="py-3 px-4">Diterima</th>
        <th class="py-3 px-4 text-right">Dosen</th>
        <th class="py-3 px-4 text-right">Mata Kuliah</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="program in programs"
        :key="program.id"
        class="border-b border-gray-50 cursor-pointer transition-colors"
        :class="selectedId === program.id ? 'bg-primary/[0.06]' : 'hover:bg-gray-50'"
        @click="$emit('select', program.id)"
      >
        <td class="py-3 px-4">
          <p class="font-semibold text-gray-900">{{ program.name }}</p>
          <p class="text-xs text-gray-400">{{ program.code }}</p>
        </td>
        <td class="py-3 px-4">
          <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="STATUS[program.constraint_status].class">
            {{ STATUS[program.constraint_status].label }}
          </span>
        </td>
        <td class="py-3 px-4 text-gray-600">{{ formatDate(program.constraint_reviewed_at) }}</td>
        <td class="py-3 px-4 text-right text-gray-700">{{ program.lecturers_count }}</td>
        <td class="py-3 px-4 text-right text-gray-700">{{ program.courses_count }}</td>
      </tr>
    </tbody>
  </table>
</template>
