<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useRunSync } from '@/composables/useRunSync';
import type { RunListItem } from '@/types/run';

const { items, loading, uploading, error, load, upload, remove } = useRunSync();
const filter = ref<'all' | 'pending' | 'uploaded' | 'failed'>('all');
const toDelete = ref<RunListItem | null>(null);
const deleteError = ref('');

const shown = computed(() => items.value.filter((i) => filter.value === 'all' || i.upload.status === filter.value));
const counts = computed(() => ({
  all: items.value.length,
  pending: items.value.filter((i) => i.upload.status === 'pending').length,
  uploaded: items.value.filter((i) => i.upload.status === 'uploaded').length,
  failed: items.value.filter((i) => i.upload.status === 'failed').length,
}));

const LABEL = { all: 'Semua', pending: 'Belum dikirim', uploaded: 'Terkirim', failed: 'Gagal' } as const;

onMounted(load);

function finished(ms: number) {
  return new Date(ms).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

async function confirmDelete() {
  if (!toDelete.value) return;
  deleteError.value = '';
  try {
    await remove(toDelete.value.run_id);
    toDelete.value = null;
  } catch (e) {
    deleteError.value = String(e);
  }
}
</script>

<template>
  <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-900">Sinkronisasi</h1>
      <Button variant="ghost" :loading="loading" @click="load">Muat ulang</Button>
    </div>

    <Alert v-if="error" title="Gagal memuat hasil">{{ error }}</Alert>

    <div class="flex gap-2">
      <button
        v-for="f in (['all', 'pending', 'uploaded', 'failed'] as const)"
        :key="f"
        type="button"
        class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
        :class="filter === f ? 'border-primary text-primary bg-primary/[0.06]' : 'border-slate-200 text-gray-600'"
        @click="filter = f"
      >
        {{ LABEL[f] }} ({{ counts[f] }})
      </button>
    </div>

    <Card no-padding class="overflow-hidden">
      <p v-if="!shown.length" class="p-6 text-sm text-gray-500">Tidak ada hasil run.</p>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100">
            <th class="py-2 px-4">Run</th>
            <th class="py-2 px-4">Periode</th>
            <th class="py-2 px-4 text-right">Fitness</th>
            <th class="py-2 px-4 text-right">HC</th>
            <th class="py-2 px-4">Selesai</th>
            <th class="py-2 px-4">Status</th>
            <th class="py-2 px-4" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in shown" :key="item.run_id" class="border-b border-gray-50 align-top">
            <td class="py-2 px-4 font-semibold text-gray-900">{{ item.operator }} · run {{ item.run_index + 1 }}</td>
            <td class="py-2 px-4 text-gray-600">{{ item.period.replace('-', ' ') }}</td>
            <td class="py-2 px-4 text-right text-gray-900">{{ item.summary.fitness_percent.toFixed(1) }}%</td>
            <td class="py-2 px-4 text-right" :class="item.summary.hc_violations ? 'text-red-600' : 'text-gray-700'">{{ item.summary.hc_violations }}</td>
            <td class="py-2 px-4 text-gray-600">{{ finished(item.finished_at_ms) }}</td>
            <td class="py-2 px-4">
              <span v-if="item.upload.status === 'uploaded'" class="text-green-700">Terkirim · #{{ item.upload.server_run_id }}</span>
              <span v-else-if="item.upload.status === 'failed'" class="text-red-600">Gagal</span>
              <span v-else class="text-gray-500">Belum dikirim</span>
              <p v-if="item.upload.error" class="mt-0.5 max-w-xs text-xs text-red-600">{{ item.upload.error }}</p>
            </td>
            <td class="py-2 px-4 text-right whitespace-nowrap">
              <Button
                v-if="item.upload.status !== 'uploaded' && !item.cancelled"
                variant="ghost"
                :loading="uploading.has(item.run_id)"
                @click="upload([item.run_id])"
              >
                {{ item.upload.status === 'failed' ? 'Coba lagi' : 'Kirim' }}
              </Button>
              <button type="button" class="ml-2 text-xs text-gray-500 hover:text-red-600" @click="toDelete = item">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </Card>

    <ConfirmDialog
      :show="toDelete !== null"
      title="Hapus hasil run?"
      confirm-label="Hapus"
      danger
      :error="deleteError || null"
      @confirm="confirmDelete"
      @close="toDelete = null"
    >
      Hasil run ini hanya dihapus dari komputer ini. Run yang sudah terkirim tetap ada di server.
    </ConfirmDialog>
  </div>
</template>