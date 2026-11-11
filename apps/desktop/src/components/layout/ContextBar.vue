<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Select from '@/components/ui/Select.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import { useDataset } from '@/composables/useDataset';
import { fetchStudyPrograms } from '@/lib/api/scheduling';
import { periodLabel } from '@/lib/format';
import type { StudyProgramOption } from '@/types/scheduling';

const props = defineProps<{ superAdmin: boolean; scopeName: string | null; workflow: boolean }>();

const { dataset, period, studyProgramId, loading, error, stale, periodOptions, setPeriod, setStudyProgram } = useDataset();

const programs = ref<StudyProgramOption[]>([]);

onMounted(async () => {
  if (!props.superAdmin) return;
  try {
    programs.value = (await fetchStudyPrograms()).sort((a, b) => a.code.localeCompare(b.code));
  } catch { /* the dataset error explains it */ }
});

const programModel = computed({
  get: () => studyProgramId.value,
  set: (value: number | null) => { void setStudyProgram(value); },
});

const periodModel = computed({
  get: () => period.value,
  set: (value: string | null) => { if (value) void setPeriod(value); },
});

const loadedAt = computed(() => dataset.value
  ? new Date(dataset.value.generated_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  : '');

const status = computed<{ tone: 'neutral' | 'success' | 'warning' | 'danger'; text: string }>(() => {
  if (loading.value) return { tone: 'neutral', text: 'Memuat data dari server' };
  if (error.value) return { tone: 'danger', text: 'Data gagal dimuat' };
  if (stale.value) return { tone: 'warning', text: 'Data di server telah berubah' };
  if (dataset.value) return { tone: 'success', text: `Data per pukul ${loadedAt.value}` };
  return { tone: 'neutral', text: 'Data belum dimuat' };
});
</script>

<!-- Context only: which study program (or TPB) and period the pages work on. Page actions never live here. -->
<template>
  <header class="flex h-[72px] shrink-0 items-center gap-6 border-b border-line bg-white px-8">
    <template v-if="workflow">
      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-ink-muted">Program studi</span>
        <Select v-if="superAdmin" v-model="programModel" class="w-80" aria-label="Program studi">
          <option :value="null" disabled>Pilih program studi</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} {{ p.name }}{{ p.faculty ? ` (${p.faculty.code})` : '' }}</option>
        </Select>
        <span v-else class="text-sm font-semibold text-ink">{{ scopeName ?? '-' }}</span>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs font-semibold text-ink-muted">Periode</span>
        <Select v-model="periodModel" class="w-56" aria-label="Periode" :disabled="!dataset && !period">
          <option v-if="!period" :value="null" disabled>Pilih periode</option>
          <option v-for="p in periodOptions" :key="p" :value="p">{{ periodLabel(p) }}</option>
        </Select>
      </div>

      <StatusDot :tone="status.tone" class="ml-auto text-xs font-medium text-ink-tertiary">{{ status.text }}</StatusDot>
    </template>
    <p v-else class="text-sm text-ink-muted">Menampilkan data seluruh fakultas yang tersimpan di server.</p>
  </header>
</template>
