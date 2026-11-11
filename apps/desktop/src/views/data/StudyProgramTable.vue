<script setup lang="ts">
import { computed, ref } from 'vue';
import { Search } from 'lucide-vue-next';
import UiInput from '@/components/ui/Input.vue';
import { colorOf, type ProgramColor } from '@/lib/programColors';
import type { DatasetStudyProgram } from '@/types/scheduling';

const props = defineProps<{
  programs: DatasetStudyProgram[];
  colors: Map<number, ProgramColor>;
  selectedId: number | null;
}>();

defineEmits<{ select: [id: number] }>();

const query = ref('');
const shown = computed(() => {
  const q = query.value.trim().toLowerCase();
  return q ? props.programs.filter((p) => `${p.code} ${p.name}`.toLowerCase().includes(q)) : props.programs;
});
</script>

<template>
  <div class="flex max-h-[calc(100vh-72px-64px)] flex-col overflow-hidden rounded-panel border border-line bg-white">
    <div class="border-b border-line p-4">
      <UiInput v-model="query" type="search" placeholder="Cari program studi" aria-label="Cari program studi">
        <template #leading><Search class="size-4" aria-hidden="true" /></template>
      </UiInput>
    </div>

    <ul class="flex-1 overflow-y-auto p-2" role="listbox" aria-label="Program studi">
      <li v-for="program in shown" :key="program.id">
        <button
          type="button"
          role="option"
          :aria-selected="selectedId === program.id"
          class="flex w-full items-start gap-3 rounded-control px-3 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :class="selectedId === program.id ? 'bg-primary-soft' : 'hover:bg-canvas'"
          @click="$emit('select', program.id)"
        >
          <span
            class="mt-0.5 size-4 shrink-0 rounded-badge border"
            :style="{ background: colorOf(colors, program.id).bg, borderColor: colorOf(colors, program.id).border }"
            aria-hidden="true"
          />
          <span class="min-w-0 flex-1">
            <span class="font-mono text-xs font-semibold text-ink-tertiary">{{ program.code }}</span>
            <span class="mt-1 block truncate text-sm font-semibold" :class="program.included ? 'text-ink' : 'text-ink-muted'">{{ program.name }}</span>
            <span class="tabular mt-1 block text-xs text-ink-muted">{{ program.lecturers_count }} dosen, {{ program.courses_count }} mata kuliah</span>
            <span v-if="!program.included" class="mt-1 block text-xs text-ink-faint">Tidak termasuk dataset</span>
          </span>
        </button>
      </li>
      <li v-if="!shown.length" class="px-3 py-8 text-center text-sm text-ink-muted">Tidak ditemukan program studi yang sesuai.</li>
    </ul>
  </div>
</template>
