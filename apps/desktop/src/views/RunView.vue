<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { PanelLeftOpen, Play, SlidersHorizontal } from 'lucide-vue-next';
import PageHeader from '@/components/layout/PageHeader.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatStrip from '@/components/ui/StatStrip.vue';
import StatTile from '@/components/ui/StatTile.vue';
import { useDataset } from '@/composables/useDataset';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaRunner } from '@/composables/useGaRunner';
import { useRunSync } from '@/composables/useRunSync';
import { dateTime, periodLabel, runLabel } from '@/lib/format';
import StartRunDialog from '@/views/config/StartRunDialog.vue';
import RunDetail from '@/views/run/RunDetail.vue';
import RunProgressList from '@/views/run/RunProgressList.vue';
import RunRail from '@/views/run/RunRail.vue';

const router = useRouter();
const { dataset } = useDataset();
const { errors, runMode, multiOperators } = useGaConfig();
const { batchId, runs, running, error, maxGenerations, cancel, listen } = useGaRunner();
const { items, uploading, error: syncError, load, upload } = useRunSync();

const selected = ref<Set<string>>(new Set());
const activeId = ref<string | null>(null);
const cancelling = ref(false);
const railCollapsed = ref(false);
const showRunDialog = ref(false);

const canStart = computed(() =>
  !running.value
  && !!dataset.value?.courses.length
  && Object.keys(errors.value).length === 0
  && (runMode.value === 'single' || multiOperators.value.length > 0),
);

const shownBatch = computed(() => batchId.value ?? items.value[0]?.batch_id ?? null);
const batchItems = computed(() => items.value
  .filter((i) => i.batch_id === shownBatch.value)
  .sort((a, b) =>
    b.best_fitness - a.best_fitness
    || a.summary.hc_violations - b.summary.hc_violations
    || a.operator.localeCompare(b.operator)
    || a.run_index - b.run_index,
  ));

const hasCleanRun = computed(() => batchItems.value.some((i) => i.summary.hc_violations === 0 && !i.cancelled));
const recommendedId = computed(() => {
  const clean = batchItems.value.filter((i) => i.summary.hc_violations === 0 && !i.cancelled);
  return clean[0]?.run_id
    ?? batchItems.value.find((i) => !i.cancelled)?.run_id
    ?? null;
});
const recommended = computed(() => batchItems.value.find((i) => i.run_id === recommendedId.value) ?? null);
const openId = computed(() =>
  batchItems.value.some((i) => i.run_id === activeId.value) ? activeId.value : recommendedId.value,
);
const openItem = computed(() => batchItems.value.find((i) => i.run_id === openId.value) ?? null);

const batchMeta = computed(() => {
  const list = batchItems.value;
  if (!list.length) return [];
  return [
    periodLabel(list[0].period),
    `${list.length} run`,
    `Operator ${[...new Set(list.map((i) => i.operator))].join(' dan ')}`,
    `Selesai ${dateTime(Math.max(...list.map((i) => i.finished_at_ms)))}`,
  ];
});

const stats = computed(() => {
  const best = recommended.value;
  if (!best) return [];
  const clean = batchItems.value.filter((i) => i.summary.hc_violations === 0 && !i.cancelled).length;
  return [
    { label: 'Fitness run rekomendasi', value: `${best.summary.fitness_percent.toFixed(1)}%`, caption: `${best.best_fitness.toFixed(1)} dari ${best.max_fitness.toFixed(0)}`, tone: 'default' as const },
    { label: 'Run tanpa pelanggaran HC', value: `${clean} dari ${batchItems.value.length}`, caption: clean ? 'Memenuhi seluruh hard constraint' : 'Seluruh run melanggar hard constraint', tone: clean ? 'success' as const : 'danger' as const },
    { label: 'SC tidak terpenuhi', value: best.summary.sc_unmet, caption: runLabel(best), tone: 'default' as const },
    { label: 'Kelas tanpa ruangan', value: best.summary.unassigned_rooms, caption: runLabel(best), tone: best.summary.unassigned_rooms ? 'danger' as const : 'default' as const },
  ];
});

onMounted(async () => {
  await listen();
  await load();
});

watch(running, (now, before) => {
  if (before && !now) {
    cancelling.value = false;
    activeId.value = null;
    selected.value = recommendedId.value ? new Set([recommendedId.value]) : new Set();
  }
});

function toggle(runId: string) {
  const next = new Set(selected.value);
  if (next.has(runId)) next.delete(runId);
  else next.add(runId);
  selected.value = next;
}

async function onCancel() {
  cancelling.value = true;
  await cancel();
}

async function send() {
  await upload([...selected.value]);
  selected.value = new Set();
}
</script>

<template>
  <div class="flex flex-col gap-6 p-8">
    <PageHeader title="Run penjadwalan">
      <template #description>
        <span v-if="running">Proses run sedang berjalan. Hasil ditampilkan setelah seluruh run selesai.</span>
        <span v-else>Pilih run untuk meninjau jadwal yang dihasilkan, kemudian kirim run terbaik ke server.</span>
      </template>
      <template #actions>
        <Button @click="router.push({ name: 'config' })">
          <SlidersHorizontal class="size-4" aria-hidden="true" />
          Ubah konfigurasi
        </Button>
        <Button v-if="running" variant="danger-secondary" :loading="cancelling" @click="onCancel">Batalkan run</Button>
        <Button v-else variant="primary" :disabled="!canStart" @click="showRunDialog = true">
          <Play class="size-4" aria-hidden="true" />
          Run baru
        </Button>
      </template>
    </PageHeader>

    <Alert v-if="error" title="Run gagal">{{ error }}</Alert>
    <Alert v-if="syncError" title="Hasil run gagal dimuat atau dikirim">{{ syncError }}</Alert>

    <Card v-if="running" no-padding>
      <div class="flex items-center justify-between border-b border-line px-6 py-4">
        <h2 class="text-sm font-semibold text-ink">Proses berjalan</h2>
        <span class="tabular text-xs text-ink-muted">{{ runs.filter((r) => r.finished).length }} dari {{ runs.length }} selesai</span>
      </div>
      <RunProgressList :runs="runs" :max-generations="maxGenerations" />
    </Card>

    <Card v-if="!running && !batchItems.length" no-padding>
      <EmptyState title="Belum ada hasil run">
        Tetapkan parameter pada halaman Konfigurasi GA, kemudian jalankan penjadwalan. Hasil run disimpan di perangkat ini.
        <template #action>
          <Button variant="primary" @click="router.push({ name: 'config' })">Buka Konfigurasi GA</Button>
        </template>
      </EmptyState>
    </Card>

    <template v-if="batchItems.length && !running">
      <div class="flex flex-wrap items-center gap-2">
        <Badge v-for="m in batchMeta" :key="m">{{ m }}</Badge>
      </div>

      <StatStrip>
        <StatTile v-for="s in stats" :key="s.label" :label="s.label" :value="s.value" :caption="s.caption" :tone="s.tone" />
      </StatStrip>

      <div class="grid items-start gap-6" :class="railCollapsed ? 'grid-cols-[minmax(0,1fr)]' : 'grid-cols-[320px_minmax(0,1fr)]'">
        <RunRail
          v-if="!railCollapsed"
          :items="batchItems"
          :selected="selected"
          :active-id="openId"
          :recommended-id="recommendedId"
          :has-clean-run="hasCleanRun"
          :sending="uploading.size > 0"
          @toggle="toggle"
          @open="activeId = $event"
          @send="send"
          @collapse="railCollapsed = true"
        />

        <Card no-padding class="min-w-0">
          <RunDetail
            v-if="openId"
            :run-id="openId"
            :recommended="openId === recommendedId"
            :has-clean-run="hasCleanRun"
            :server-run-id="openItem?.upload.status === 'uploaded' ? openItem.upload.server_run_id : null"
          >
            <template #lead>
              <Button v-if="railCollapsed" variant="tertiary" size="sm" aria-label="Tampilkan daftar run" title="Tampilkan daftar run" @click="railCollapsed = false">
                <PanelLeftOpen class="size-4" aria-hidden="true" />
              </Button>
            </template>
          </RunDetail>
          <EmptyState v-else title="Seluruh run dibatalkan" />
        </Card>
      </div>
    </template>

    <StartRunDialog :show="showRunDialog" @close="showRunDialog = false" />
  </div>
</template>
