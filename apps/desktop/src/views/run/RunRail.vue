<script setup lang="ts">
import { PanelLeftClose } from 'lucide-vue-next';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import { runLabel } from '@/lib/format';
import type { RunListItem } from '@/types/run';

defineProps<{
  items: RunListItem[];
  selected: Set<string>;
  activeId: string | null;
  recommendedId: string | null;
  hasCleanRun: boolean;
  sending: boolean;
}>();

defineEmits<{ toggle: [runId: string]; open: [runId: string]; send: []; collapse: [] }>();
</script>

<!-- Runs of one batch, best first. Check runs to send them; click a run to open it. -->
<template>
  <div class="flex max-h-[calc(100vh-72px-64px)] flex-col overflow-hidden rounded-panel border border-line bg-white">
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-line pl-4 pr-2">
      <h2 class="text-sm font-semibold text-ink">Run <span class="tabular text-ink-muted">{{ items.length }}</span></h2>
      <Button variant="tertiary" size="sm" aria-label="Sembunyikan daftar run" title="Sembunyikan daftar run" @click="$emit('collapse')">
        <PanelLeftClose class="size-4" aria-hidden="true" />
      </Button>
    </div>

    <ul class="flex-1 overflow-y-auto p-2">
      <li v-for="item in items" :key="item.run_id">
        <div
          class="flex items-start gap-3 rounded-control px-3 py-3 transition-colors"
          :class="activeId === item.run_id ? 'bg-primary-soft' : 'hover:bg-canvas'"
        >
          <input
            type="checkbox"
            class="mt-0.5 shrink-0"
            :checked="selected.has(item.run_id)"
            :disabled="item.upload.status === 'uploaded' || item.cancelled"
            :aria-label="`Pilih ${runLabel(item)}`"
            @change="$emit('toggle', item.run_id)"
          />
          <button type="button" class="min-w-0 flex-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" @click="$emit('open', item.run_id)">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold" :class="activeId === item.run_id ? 'text-primary' : 'text-ink'">{{ runLabel(item) }}</span>
              <Badge v-if="recommendedId === item.run_id" :tone="hasCleanRun ? 'primary' : 'warning'">
                {{ hasCleanRun ? 'Rekomendasi' : 'Terbaik, melanggar HC' }}
              </Badge>
              <Badge v-if="item.upload.status === 'uploaded'" tone="success">Terkirim</Badge>
              <Badge v-else-if="item.upload.status === 'failed'" tone="danger">Gagal dikirim</Badge>
              <Badge v-if="item.cancelled">Dibatalkan</Badge>
            </span>
            <span class="tabular mt-1 flex gap-4 text-xs text-ink-muted">
              <span>{{ item.summary.fitness_percent.toFixed(1) }}%</span>
              <span :class="item.summary.hc_violations ? 'font-semibold text-danger-ink' : ''">HC {{ item.summary.hc_violations }}</span>
              <span>SC {{ item.summary.sc_unmet }}</span>
            </span>
          </button>
        </div>
      </li>
    </ul>

    <div class="flex shrink-0 items-center justify-between gap-4 border-t border-line p-4">
      <span class="tabular text-xs text-ink-muted">{{ selected.size }} run dipilih</span>
      <Button variant="primary" :disabled="!selected.size" :loading="sending" @click="$emit('send')">Kirim ke server</Button>
    </div>
  </div>
</template>
