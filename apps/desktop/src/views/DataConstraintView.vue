<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { fetchFaculties } from '@/lib/api/scheduling';
import type { FacultyOption } from '@/types/scheduling';
import StudyProgramDetail from '@/views/data/StudyProgramDetail.vue';
import StudyProgramTable from '@/views/data/StudyProgramTable.vue';

const { user } = useAuth();
const { dataset, period, facultyId, loading, error, periodOptions, load, setPeriod, setFaculty } = useDataset();

const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const faculties = ref<FacultyOption[]>([]);
const selectedProgramId = ref<number | null>(null);

const selectedProgram = computed(() =>
  dataset.value?.study_programs.find((p) => p.id === selectedProgramId.value) ?? null,
);

const summary = computed(() => {
  const d = dataset.value;
  if (!d) return [];
  return [
    { label: 'Prodi ikut dijadwalkan', value: d.study_programs.filter((p) => p.included).length },
    { label: 'Mata kuliah', value: d.courses.length },
    { label: 'Kelas', value: d.classes.length },
    { label: 'Dosen', value: d.lecturers.length },
    { label: 'Ruangan', value: d.rooms.length },
  ];
});

const generatedAt = computed(() =>
  dataset.value ? new Date(dataset.value.generated_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '',
);

onMounted(async () => {
  if (isSuperAdmin.value) {
    try { faculties.value = await fetchFaculties(); } catch { /* shown through the dataset error */ }
  }
  if (!dataset.value && (!isSuperAdmin.value || facultyId.value)) await load();
});

async function onFacultyChange(event: Event) {
  selectedProgramId.value = null;
  await setFaculty(Number((event.target as HTMLSelectElement).value) || null);
}

async function onPeriodChange(event: Event) {
  await setPeriod((event.target as HTMLSelectElement).value);
}
</script>

<template>
  <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Data & Constraint</h1>
        <p v-if="dataset" class="mt-1 text-sm text-gray-500">
          {{ dataset.faculty.name }} · diperbarui {{ generatedAt }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <select
          v-if="isSuperAdmin"
          :value="facultyId ?? ''"
          class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary"
          aria-label="Fakultas"
          @change="onFacultyChange"
        >
          <option value="" disabled>Pilih fakultas</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.code }} — {{ f.name }}</option>
        </select>
        <select
          :value="period ?? ''"
          :disabled="!dataset && !period"
          class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-60"
          aria-label="Periode"
          @change="onPeriodChange"
        >
          <option v-for="p in periodOptions" :key="p" :value="p">{{ p.replace('-', ' ') }}</option>
        </select>
        <Button variant="ghost" :loading="loading" :disabled="isSuperAdmin && !facultyId" @click="load">
          <RefreshCw v-if="!loading" class="size-4" aria-hidden="true" />
          Muat ulang
        </Button>
      </div>
    </div>

    <Alert v-if="error" title="Gagal memuat data">{{ error }}</Alert>

    <Card v-if="isSuperAdmin && !facultyId">
      <p class="text-sm text-gray-500">Pilih fakultas untuk memuat data penjadwalan.</p>
    </Card>

    <template v-else-if="dataset">
      <div class="grid grid-cols-5 gap-3">
        <Card v-for="item in summary" :key="item.label">
          <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ item.label }}</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ item.value }}</p>
        </Card>
      </div>

      <div class="grid grid-cols-12 gap-5 items-start">
        <Card no-padding class="col-span-5 overflow-hidden">
          <StudyProgramTable
            :programs="dataset.study_programs"
            :selected-id="selectedProgramId"
            @select="selectedProgramId = $event"
          />
        </Card>
        <Card class="col-span-7">
          <StudyProgramDetail v-if="selectedProgram" :program="selectedProgram" :dataset="dataset" />
          <p v-else class="text-sm text-gray-500">Pilih program studi untuk melihat detail.</p>
        </Card>
      </div>
    </template>

    <Card v-else-if="loading">
      <div class="space-y-3 animate-pulse">
        <div class="h-4 w-1/3 rounded bg-gray-100" />
        <div class="h-4 w-2/3 rounded bg-gray-100" />
        <div class="h-4 w-1/2 rounded bg-gray-100" />
      </div>
    </Card>
  </div>
</template>
