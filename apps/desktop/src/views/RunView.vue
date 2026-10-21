<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { useGaRunner } from '@/composables/useGaRunner';
import { useRunSync } from '@/composables/useRunSync';
import RunDetail from '@/views/run/RunDetail.vue';
import RunProgressList from '@/views/run/RunProgressList.vue';
import RunResultTable from '@/views/run/RunResultTable.vue';

const { batchId, runs, running, error, maxGenerations, cancel, listen } = useGaRunner();
const { items, uploading, error: syncError, load, upload } = useRunSync();

const selected = ref<Set<string>>(new Set());
const activeId = ref<string | null>(null);
const cancelling = ref(false);

const shownBatch = computed(() => batchId.value ?? items.value[0]?.batch_id ?? null);
const batchItems = computed(() =>
  items.value.filter((i) => i.batch_id === shownBatch.value).sort((a, b) => a.operator.localeCompare(b.operator) || a.run_index - b.run_index),
);

const recommendedId = computed(() => {
  const clean = batchItems.value.filter((i) => i.summary.hc_violations === 0 && !i.cancelled);
  return clean.sort((a, b) => b.best_fitness - a.best_fitness)[0]?.run_id ?? null;
});

onMounted(async () => {
  await listen();
  await load();
});

watch(running, (now, before) => {
  if (before && !now) {
    cancelling.value = false;
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
  <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">Run Penjadwalan</h1>
      <Button v-if="running" variant="danger" :loading="cancelling" @click="onCancel">Batalkan</Button>
    </div>

    <Alert v-if="error" title="Run gagal">{{ error }}</Alert>
    <Alert v-if="syncError" title="Gagal memuat hasil">{{ syncError }}</Alert>

    <Card v-if="running || runs.length">
      <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">
        {{ running ? 'Sedang berjalan' : 'Run terakhir' }} · {{ runs.filter((r) => r.finished).length }}/{{ runs.length }} selesai
      </p>
      <RunProgressList :runs="runs" :max-generations="maxGenerations" />
    </Card>

    <Card v-if="!running && !batchItems.length && !runs.length">
      <p class="text-sm text-gray-500">
        Belum ada hasil run. Mulai dari halaman
        <RouterLink :to="{ name: 'config' }" class="font-semibold text-primary hover:underline">Konfigurasi GA</RouterLink>.
      </p>
    </Card>

    <template v-if="batchItems.length && !running">
      <Card no-padding class="overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-700">Perbandingan run · periode {{ batchItems[0].period.replace('-', ' ') }}</p>
          <Button :disabled="!selected.size" :loading="uploading.size > 0" @click="send">
            Kirim ke server ({{ selected.size }})
          </Button>
        </div>
        <div class="overflow-x-auto">
          <RunResultTable
            :items="batchItems"
            :selected="selected"
            :active-id="activeId"
            :recommended-id="recommendedId"
            @toggle="toggle"
            @open="activeId = $event"
          />
        </div>
      </Card>

      <Card v-if="activeId">
        <RunDetail :run-id="activeId" />
      </Card>
    </template>
  </div>
</template>
