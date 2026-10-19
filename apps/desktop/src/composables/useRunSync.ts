import { computed, ref } from 'vue';
import { ApiError } from '@/lib/http';
import { uploadRun } from '@/lib/api/schedulingRuns';
import { gaDeleteResult, gaGetResult, gaListResults, gaMarkUploadFailed, gaMarkUploaded } from '@/lib/tauri';
import type { RunListItem } from '@/types/run';

const items = ref<RunListItem[]>([]);
const loading = ref(false);
const uploading = ref<Set<string>>(new Set());
const error = ref('');

export function useRunSync() {
  async function load() {
    loading.value = true;
    error.value = '';
    try {
      items.value = await gaListResults();
    } catch (e) {
      error.value = String(e);
    } finally {
      loading.value = false;
    }
  }

  function replace(item: RunListItem) {
    items.value = items.value.map((i) => (i.run_id === item.run_id ? item : i));
  }

  async function upload(runIds: string[]) {
    for (const runId of runIds) {
      uploading.value = new Set([...uploading.value, runId]);
      try {
        const record = await gaGetResult(runId);
        const server = await uploadRun(record.result);
        replace(await gaMarkUploaded(runId, server.id));
      } catch (e) {
        const message = e instanceof ApiError && e.code === 'DATASET_CHANGED'
          ? 'Data di server sudah berubah. Muat ulang data lalu jalankan ulang.'
          : (e as Error).message ?? String(e);
        try { replace(await gaMarkUploadFailed(runId, message)); } catch { error.value = message; }
      } finally {
        const next = new Set(uploading.value);
        next.delete(runId);
        uploading.value = next;
      }
    }
  }

  async function remove(runId: string) {
    await gaDeleteResult(runId);
    items.value = items.value.filter((i) => i.run_id !== runId);
  }

  return {
    items: computed(() => items.value),
    loading: computed(() => loading.value),
    uploading: computed(() => uploading.value),
    error: computed(() => error.value),
    load, upload, remove,
  };
}
