<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useAuth } from '@/composables/useAuth';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaRunner } from '@/composables/useGaRunner';
import { useGaPresets } from '@/composables/useGaPresets';
import ConstraintWeights from '@/views/config/ConstraintWeights.vue';
import ParameterForm from '@/views/config/ParameterForm.vue';
import PresetBar from '@/views/config/PresetBar.vue';

const router = useRouter();
const { user } = useAuth();
const { dataset, period, facultyId, loading, error, load } = useDataset();
const { params, errors, runMode, multiOperators, presetId, applyPreset, resetDefault } = useGaConfig();
const { presets, load: loadPresets } = useGaPresets();

const isSuperAdmin = computed(() => user.value?.role === 'superadmin');
const showRunDialog = ref(false);
const { start, running } = useGaRunner();
const startError = ref('');

const operators = computed(() => (runMode.value === 'multi' ? multiOperators.value : [params.selection_operator]));
const totalRuns = computed(() => operators.value.length * params.run_count);
const canRun = computed(() =>
  Object.keys(errors.value).length === 0 && operators.value.length > 0 && (dataset.value?.courses.length ?? 0) > 0,
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

onMounted(() => {
  if (!dataset.value && !loading.value && (!isSuperAdmin.value || facultyId.value)) load();
});

watch(() => dataset.value?.faculty.id, async (id) => {
  if (!id) return;
  await loadPresets(id);
  if (!presets.value.some((p) => p.id === presetId.value)) {
    const operator = period.value?.endsWith('-GENAP') ? 'TSR' : 'TS';
    const preset = presets.value.find((p) => p.selection_operator === operator) ?? presets.value[0];
    if (preset) applyPreset(preset);
    else resetDefault(period.value);
  }
}, { immediate: true });

async function confirmRun() {
  startError.value = '';
  try {
    await start();
    showRunDialog.value = false;
    router.push({ name: 'run' });
  } catch (e) {
    startError.value = String(e);
  }
}
</script>

<template>
  <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">Konfigurasi Parameter GA</h1>

    <Alert v-if="error" title="Gagal memuat data">{{ error }}</Alert>

    <Card v-if="isSuperAdmin && !facultyId">
      <p class="text-sm text-gray-500">
        Pilih fakultas di halaman
        <RouterLink :to="{ name: 'data' }" class="font-semibold text-primary hover:underline">Data &amp; Constraint</RouterLink>
        terlebih dahulu.
      </p>
    </Card>

    <Card v-else-if="!dataset">
      <div class="space-y-3 animate-pulse">
        <div class="h-4 w-1/3 rounded bg-gray-100" />
        <div class="h-4 w-2/3 rounded bg-gray-100" />
      </div>
    </Card>

    <template v-else>
      <PresetBar :faculty-id="dataset.faculty.id" :super-admin="isSuperAdmin" />

      <div class="grid grid-cols-12 gap-5 items-start">
        <div class="col-span-8 space-y-5">
          <ParameterForm />
          <ConstraintWeights :constraint-types="dataset.constraint_types" :programs="dataset.study_programs" />
        </div>

        <div class="col-span-4">
          <div class="sticky top-4">
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-4">Ringkasan Run</p>
              <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Periode</dt><dd class="font-semibold text-gray-900">{{ dataset.period.replace('-', ' ') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Mode</dt><dd class="font-semibold text-gray-900">{{ runMode === 'single' ? 'Single-run' : 'Multi-run' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Operator</dt><dd class="font-semibold text-gray-900">{{ operators.join(', ') || '–' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Populasi</dt><dd class="font-semibold text-gray-900">{{ params.population_size }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Generasi</dt><dd class="font-semibold text-gray-900">{{ params.min_generations }}–{{ params.max_generations }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Cr / Mr</dt><dd class="font-semibold text-gray-900">{{ params.crossover_rate }} / {{ params.mutation_rate }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Total run</dt><dd class="font-semibold text-gray-900">{{ totalRuns }}</dd></div>
              </dl>

              <div class="my-4 border-t border-gray-100" />

              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Data Dipakai</p>
              <dl class="space-y-1.5 text-sm">
                <div v-for="item in summary" :key="item.label" class="flex justify-between">
                  <dt class="text-gray-500">{{ item.label }}</dt><dd class="font-semibold text-gray-900">{{ item.value }}</dd>
                </div>
              </dl>

              <div class="mt-5 space-y-2">
                <Button class="w-full h-11" :disabled="!canRun || running" @click="showRunDialog = true">{{ running ? 'Run sedang berjalan' : 'Mulai Run' }}</Button>
                <Button variant="ghost" class="w-full" @click="resetDefault(dataset.period)">Reset ke default TA</Button>
              </div>
            </Card>
          </div>
        </div>
      </div>
    </template>

    <ConfirmDialog
      :show="showRunDialog"
      title="Mulai run?"
      confirm-label="Mulai"
      :error="startError || null"
      @confirm="confirmRun"
      @close="showRunDialog = false"
    >
      <p>{{ totalRuns }} run dengan operator <strong>{{ operators.join(', ') }}</strong>. Proses mungkin membutuhkan waktu beberapa menit.</p>
    </ConfirmDialog>
  </div>
</template>