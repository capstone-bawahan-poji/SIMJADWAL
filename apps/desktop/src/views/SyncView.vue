<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Trash2, Upload } from 'lucide-vue-next';
import PageHeader from '@/components/layout/PageHeader.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import StatusDot from '@/components/ui/StatusDot.vue';
import { useRunSync } from '@/composables/useRunSync';
import { dateTime, periodLabel, runLabel } from '@/lib/format';
import type { RunListItem } from '@/types/run';

type Filter = 'all' | 'pending' | 'uploaded' | 'failed';

const { items, uploading, error, load, upload, remove } = useRunSync();
const filter = ref<Filter>('all');
const selected = ref<Set<string>>(new Set());
const confirmingDelete = ref(false);
const deleting = ref(false);
const deleteError = ref('');

const sendable = (i: RunListItem) => i.upload.status !== 'uploaded' && !i.cancelled;

const shown = computed(() => items.value.filter((i) => filter.value === 'all' || i.upload.status === filter.value));
const unsentRunIds = computed(() => items.value.filter(sendable).map((i) => i.run_id));
const selectedSendable = computed(() => items.value.filter((i) => selected.value.has(i.run_id) && sendable(i)).map((i) => i.run_id));
const allShownSelected = computed(() => shown.value.length > 0 && shown.value.every((i) => selected.value.has(i.run_id)));
const sending = computed(() => uploading.value.size > 0);

const filterOptions = computed(() => [
  { value: 'all' as const, label: 'Semua', count: items.value.length },
  { value: 'pending' as const, label: 'Belum dikirim', count: items.value.filter((i) => i.upload.status === 'pending').length },
  { value: 'uploaded' as const, label: 'Terkirim', count: items.value.filter((i) => i.upload.status === 'uploaded').length },
  { value: 'failed' as const, label: 'Gagal', count: items.value.filter((i) => i.upload.status === 'failed').length },
]);

const STATUS = {
  pending: { tone: 'neutral', label: 'Belum dikirim' },
  uploaded: { tone: 'success', label: 'Terkirim' },
  failed: { tone: 'danger', label: 'Gagal' },
} as const;

onMounted(load);

watch(items, () => {
  const ids = new Set(items.value.map((i) => i.run_id));
  selected.value = new Set([...selected.value].filter((id) => ids.has(id)));
});

function toggle(runId: string) {
  const next = new Set(selected.value);
  if (next.has(runId)) next.delete(runId);
  else next.add(runId);
  selected.value = next;
}

function toggleAll() {
  selected.value = allShownSelected.value ? new Set() : new Set(shown.value.map((i) => i.run_id));
}

async function sendSelected() {
  await upload(selectedSendable.value);
  selected.value = new Set();
}

async function confirmDelete() {
  deleting.value = true;
  deleteError.value = '';
  try {
    for (const runId of selected.value) await remove(runId);
    selected.value = new Set();
    confirmingDelete.value = false;
  } catch (e) {
    deleteError.value = String(e);
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 p-8">
    <PageHeader title="Sinkronisasi" description="Hasil run disimpan di perangkat ini. Kirim hasil ke server agar dapat ditinjau dan dipublikasikan melalui aplikasi web." />

    <Alert v-if="error" title="Hasil run gagal dimuat atau dikirim">{{ error }}</Alert>

    <Card no-padding>
      <div class="flex h-[72px] items-center gap-4 border-b border-line px-6">
        <template v-if="selected.size">
          <span class="tabular text-sm font-semibold text-ink">{{ selected.size }} run dipilih</span>
          <Button variant="tertiary" @click="selected = new Set()">Batalkan pilihan</Button>
          <div class="ml-auto flex items-center gap-2">
            <Button variant="danger-secondary" @click="deleteError = ''; confirmingDelete = true">
              <Trash2 class="size-4" aria-hidden="true" />
              Hapus dari perangkat
            </Button>
            <Button variant="primary" :disabled="!selectedSendable.length" :loading="sending" @click="sendSelected">
              <Upload v-if="!sending" class="size-4" aria-hidden="true" />
              Kirim {{ selectedSendable.length }} run
            </Button>
          </div>
        </template>
        <template v-else>
          <SegmentedControl v-model="filter" label="Filter status" :options="filterOptions" />
          <Button variant="primary" class="ml-auto" :disabled="!unsentRunIds.length" :loading="sending" @click="upload(unsentRunIds)">
            <Upload v-if="!sending" class="size-4" aria-hidden="true" />
            Kirim semua run yang belum terkirim
          </Button>
        </template>
      </div>

      <EmptyState v-if="!shown.length" :title="items.length ? 'Tidak terdapat run dengan status ini' : 'Belum ada hasil run'">
        <template v-if="!items.length">Hasil dari halaman Run penjadwalan akan ditampilkan di sini.</template>
      </EmptyState>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-line text-left text-xs text-ink-muted">
            <th class="w-12 py-3 pl-6">
              <input type="checkbox" :checked="allShownSelected" aria-label="Pilih semua run yang tampil" @change="toggleAll" />
            </th>
            <th class="py-3 pr-4 font-semibold">Run</th>
            <th class="py-3 pr-4 font-semibold">Periode</th>
            <th class="py-3 pr-4 text-right font-semibold">Fitness</th>
            <th class="py-3 pr-4 text-right font-semibold">HC</th>
            <th class="py-3 pr-4 font-semibold">Selesai</th>
            <th class="py-3 pr-6 font-semibold">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr
            v-for="item in shown"
            :key="item.run_id"
            class="h-14 transition-colors"
            :class="selected.has(item.run_id) ? 'bg-primary-soft' : 'hover:bg-canvas'"
          >
            <td class="pl-6">
              <input type="checkbox" :checked="selected.has(item.run_id)" :aria-label="`Pilih ${runLabel(item)}`" @change="toggle(item.run_id)" />
            </td>
            <td class="pr-4 font-semibold text-ink">
              {{ runLabel(item) }}
              <span v-if="item.cancelled" class="ml-2 text-xs font-normal text-ink-muted">dibatalkan</span>
            </td>
            <td class="pr-4 text-ink-secondary">{{ periodLabel(item.period) }}</td>
            <td class="tabular pr-4 text-right text-ink">{{ item.summary.fitness_percent.toFixed(1) }}%</td>
            <td class="tabular pr-4 text-right" :class="item.summary.hc_violations ? 'font-semibold text-danger-ink' : 'text-ink-secondary'">{{ item.summary.hc_violations }}</td>
            <td class="tabular pr-4 text-ink-secondary">{{ dateTime(item.finished_at_ms) }}</td>
            <td class="py-2 pr-6">
              <StatusDot :tone="uploading.has(item.run_id) ? 'neutral' : STATUS[item.upload.status].tone" class="text-ink-secondary">
                <template v-if="uploading.has(item.run_id)">Sedang dikirim</template>
                <template v-else>
                  {{ STATUS[item.upload.status].label }}
                  <span v-if="item.upload.server_run_id" class="tabular text-xs text-ink-muted">#{{ item.upload.server_run_id }}</span>
                </template>
              </StatusDot>
              <p v-if="item.upload.error && item.upload.status === 'failed'" class="mt-1 max-w-xs truncate pl-4 text-xs text-danger-ink" :title="item.upload.error">{{ item.upload.error }}</p>
            </td>
          </tr>
        </tbody>
      </table>

      <p class="border-t border-line-soft px-6 py-4 text-xs text-ink-muted">
        Penghapusan hanya berlaku untuk salinan di perangkat ini. Run yang telah terkirim tetap tersimpan di server.
      </p>
    </Card>

    <ConfirmDialog
      :show="confirmingDelete"
      :title="`Hapus ${selected.size} run dari perangkat ini?`"
      confirm-label="Ya, hapus"
      danger
      :processing="deleting"
      :error="deleteError || null"
      @confirm="confirmDelete"
      @close="confirmingDelete = false"
    >
      Hasil run yang dipilih akan dihapus dari perangkat ini. Run yang belum terkirim tidak dapat dipulihkan.
    </ConfirmDialog>
  </div>
</template>
