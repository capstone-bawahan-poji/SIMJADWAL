<script setup lang="ts">
import { tpbConstraintTypes } from '@/lib/tpb';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaPresets } from '@/composables/useGaPresets';
import { useGaRunner } from '@/composables/useGaRunner';
import { periodLabel } from '@/lib/format';
import ConstraintWeights from '@/views/config/ConstraintWeights.vue';
import ParameterForm from '@/views/config/ParameterForm.vue';
import PresetBar from '@/views/config/PresetBar.vue';
import StartRunDialog from '@/views/config/StartRunDialog.vue';

const { user } = useAuth();
const { dataset, period, facultyId, error } = useDataset();
const { params, errors, runMode, multiOperators, presetId, applyPreset, resetDefault } = useGaConfig();
const { presets, load: loadPresets } = useGaPresets();
const { running } = useGaRunner();

const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const showRunDialog = ref(false);

const operators = computed(() => (runMode.value === 'multi' ? multiOperators.value : [params.selection_operator]));
const totalRuns = computed(() => operators.value.length * params.run_count);
const errorCount = computed(() => Object.keys(errors.value).length);

const blockReason = computed(() => {
  if (running.value) return 'Proses run lain sedang berjalan.';
  if (errorCount.value) return `Terdapat ${errorCount.value} isian yang belum valid.`;
  if (!operators.value.length) return 'Pilih minimal satu operator seleksi.';
  if (!dataset.value?.courses.length) return 'Dataset belum memiliki mata kuliah.';
  return '';
});

const runSummary = computed(() => [
  { label: 'Periode', value: dataset.value ? periodLabel(dataset.value.period) : '-' },
  { label: 'Mode', value: runMode.value === 'single' ? 'Single-run' : 'Multi-run' },
  { label: 'Operator', value: operators.value.join(', ') || '-' },
  { label: 'Populasi', value: params.population_size },
  { label: 'Generasi', value: `${params.min_generations} hingga ${params.max_generations}` },
  { label: 'Cr dan Mr', value: `${params.crossover_rate} dan ${params.mutation_rate}` },
  { label: 'Total run', value: totalRuns.value },
]);

const dataSummary = computed(() => {
  const d = dataset.value;
  if (!d) return [];
  return [
    { label: 'Program studi', value: d.study_programs.filter((p) => p.included).length },
    { label: 'Mata kuliah', value: d.courses.length },
    { label: 'Kelas', value: d.classes.length },
    { label: 'Dosen', value: d.lecturers.length },
    { label: 'Ruangan', value: d.rooms.length },
  ];
});

watch(() => (dataset.value ? `${dataset.value.scope}:${dataset.value.faculty?.id ?? ''}` : null), async (key) => {
  if (!key || !dataset.value) return;
  await loadPresets(dataset.value.faculty?.id ?? null);
  if (!presets.value.some((p) => p.id === presetId.value)) {
    const operator = period.value?.endsWith('-GENAP') ? 'TSR' : 'TS';
    const preset = presets.value.find((p) => p.selection_operator === operator) ?? presets.value[0];
    if (preset) applyPreset(preset);
    else resetDefault(period.value);
  }
}, { immediate: true });
</script>

<template>
  <div class="flex flex-col gap-6 p-8">
    <PageHeader title="Konfigurasi GA" description="Tetapkan parameter algoritma genetika dan bobot constraint sebelum menjalankan penjadwalan." />

    <Alert v-if="error" title="Gagal memuat data">{{ error }}</Alert>

    <Card v-if="isSuperAdmin && !facultyId" no-padding>
      <EmptyState title="Fakultas belum dipilih">Pilih fakultas pada bilah atas untuk memuat dataset dan preset.</EmptyState>
    </Card>

    <Card v-else-if="!dataset">
      <Skeleton :lines="4" />
    </Card>

    <template v-else>
      <PresetBar :faculty-id="dataset.faculty?.id ?? null" :super-admin="isSuperAdmin" />

      <div class="grid grid-cols-[minmax(0,1fr)_320px] items-start gap-6">
        <div class="flex min-w-0 flex-col gap-6">
          <ParameterForm />
          <ConstraintWeights
            :constraint-types="dataset.scope === 'tpb' ? tpbConstraintTypes(dataset) : dataset.constraint_types"
            :programs="dataset.scope === 'tpb' ? [] : dataset.study_programs"
            :tpb="dataset.scope === 'tpb'"
          />
        </div>

        <aside class="sticky top-8">
          <Card no-padding>
            <div class="p-6">
              <h2 class="text-sm font-semibold text-ink">Ringkasan konfigurasi</h2>
              <dl class="mt-4 flex flex-col gap-3 text-sm">
                <div v-for="item in runSummary" :key="item.label" class="flex justify-between gap-4">
                  <dt class="text-ink-muted">{{ item.label }}</dt>
                  <dd class="tabular text-right font-semibold text-ink">{{ item.value }}</dd>
                </div>
              </dl>
            </div>

            <div class="border-t border-line-soft p-6">
              <h2 class="text-sm font-semibold text-ink">Data yang digunakan</h2>
              <dl class="mt-4 flex flex-col gap-3 text-sm">
                <div v-for="item in dataSummary" :key="item.label" class="flex justify-between gap-4">
                  <dt class="text-ink-muted">{{ item.label }}</dt>
                  <dd class="tabular font-semibold text-ink">{{ item.value }}</dd>
                </div>
              </dl>
            </div>

            <div class="flex flex-col gap-2 border-t border-line-soft p-6">
              <Button variant="primary" size="lg" class="w-full" :disabled="!!blockReason" @click="showRunDialog = true">
                Mulai run
              </Button>
              <p v-if="blockReason" class="text-xs text-ink-muted">{{ blockReason }}</p>
              <Button variant="tertiary" class="w-full" @click="resetDefault(dataset.period)">Kembalikan ke nilai acuan TA</Button>
            </div>
          </Card>
        </aside>
      </div>
    </template>

    <StartRunDialog :show="showRunDialog" @close="showRunDialog = false" />
  </div>
</template>
