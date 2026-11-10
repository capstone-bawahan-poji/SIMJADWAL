<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import PageHeader from '@/components/layout/PageHeader.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import StatStrip from '@/components/ui/StatStrip.vue';
import StatTile from '@/components/ui/StatTile.vue';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { dateTime } from '@/lib/format';
import { programColorMap } from '@/lib/programColors';
import StudyProgramDetail from '@/views/data/StudyProgramDetail.vue';
import StudyProgramTable from '@/views/data/StudyProgramTable.vue';
import TpbData from '@/views/data/TpbData.vue';

const { user } = useAuth();
const { dataset, facultyId, loading, error, load } = useDataset();

const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const selectedProgramId = ref<number | null>(null);

const colors = computed(() => programColorMap(dataset.value?.study_programs ?? []));
const selectedProgram = computed(() =>
  dataset.value?.study_programs.find((p) => p.id === selectedProgramId.value) ?? null,
);

watch(() => dataset.value?.fingerprint, () => {
  const programs = dataset.value?.study_programs ?? [];
  if (!programs.some((p) => p.id === selectedProgramId.value)) {
    selectedProgramId.value = (programs.find((p) => p.included) ?? programs[0])?.id ?? null;
  }
}, { immediate: true });

const stats = computed(() => {
  const d = dataset.value;
  if (!d) return [];
  return [
    { label: 'Program studi terjadwal', value: `${d.study_programs.filter((p) => p.included).length} dari ${d.study_programs.length}` },
    { label: 'Mata kuliah', value: d.courses.length },
    { label: 'Kelas', value: d.classes.length },
    { label: 'Dosen', value: d.lecturers.length },
    { label: 'Ruangan', value: d.rooms.length },
  ];
});
</script>

<template>
  <div class="flex flex-col gap-6 p-8">
    <PageHeader
      title="Data dan constraint"
      :description="dataset ? `Dataset ${dataset.faculty?.name ?? 'TPB institut'}, diperbarui ${dateTime(dataset.generated_at)}.` : 'Dataset penjadwalan yang digunakan oleh algoritma genetika.'"
    >
      <template #actions>
        <Button :loading="loading" :disabled="isSuperAdmin && !facultyId" @click="load">
          <RefreshCw v-if="!loading" class="size-4" aria-hidden="true" />
          Muat ulang data
        </Button>
      </template>
    </PageHeader>

    <Alert v-if="error" title="Gagal memuat data">{{ error }}</Alert>

    <Card v-if="isSuperAdmin && !facultyId" no-padding>
      <EmptyState title="Fakultas belum dipilih">Pilih fakultas pada bilah atas untuk memuat dataset penjadwalan.</EmptyState>
    </Card>

    <TpbData v-else-if="dataset && dataset.scope === 'tpb'" :dataset="dataset" />

    <template v-else-if="dataset">
      <StatStrip>
        <StatTile v-for="s in stats" :key="s.label" :label="s.label" :value="s.value" />
      </StatStrip>

      <div class="grid grid-cols-[320px_minmax(0,1fr)] items-start gap-6">
        <StudyProgramTable
          :programs="dataset.study_programs"
          :colors="colors"
          :selected-id="selectedProgramId"
          @select="selectedProgramId = $event"
        />
        <Card no-padding>
          <StudyProgramDetail v-if="selectedProgram" :program="selectedProgram" :dataset="dataset" :colors="colors" />
          <EmptyState v-else title="Program studi belum dipilih">Pilih program studi pada daftar di sebelah kiri.</EmptyState>
        </Card>
      </div>
    </template>

    <Card v-else-if="loading">
      <Skeleton :lines="4" />
    </Card>
  </div>
</template>
