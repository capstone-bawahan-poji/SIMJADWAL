<script setup lang="ts">
import { computed } from 'vue';
import type { DatasetPreference, DatasetTimeSlot } from '@/types/scheduling';

const props = defineProps<{
  slots: DatasetTimeSlot[];
  preferences: DatasetPreference[];
}>();

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

const sessions = computed(() => [...new Set(props.slots.map((s) => s.session))].sort((a, b) => a - b));
const typeBySlot = computed(() => new Map(props.preferences.map((p) => [p.time_slot_id, p.type])));

function slotAt(day: number, session: number) {
  return props.slots.find((s) => s.day === day && s.session === session);
}

function cellType(day: number, session: number) {
  const slot = slotAt(day, session);
  return slot ? typeBySlot.value.get(slot.id) : undefined;
}

const LABEL = { want: 'Diinginkan', avoid: 'Dihindari' } as const;
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="grid grid-cols-[96px_repeat(5,minmax(0,1fr))] gap-2 text-xs">
      <span />
      <span v-for="day in DAYS" :key="day" class="text-center font-semibold text-ink-muted">{{ day }}</span>
      <template v-for="session in sessions" :key="session">
        <span class="flex flex-col justify-center text-ink-secondary">
          Sesi {{ session }}
          <span class="tabular text-ink-faint">{{ slotAt(1, session)?.start_time.slice(0, 5) }}</span>
        </span>
        <span
          v-for="(_, index) in DAYS"
          :key="index"
          class="flex h-12 items-center justify-center rounded-control font-semibold"
          :class="{
            'bg-success-tint text-success-ink': cellType(index + 1, session) === 'want',
            'bg-warning-border text-warning-ink': cellType(index + 1, session) === 'avoid',
            'bg-canvas text-ink-faint': !cellType(index + 1, session),
          }"
        >
          {{ cellType(index + 1, session) ? LABEL[cellType(index + 1, session)!] : '' }}
        </span>
      </template>
    </div>
    <div class="flex gap-4 text-xs text-ink-muted">
      <span class="inline-flex items-center gap-2"><span class="size-3 rounded-badge bg-success-tint" />Diinginkan</span>
      <span class="inline-flex items-center gap-2"><span class="size-3 rounded-badge bg-warning-border" />Dihindari</span>
      <span class="inline-flex items-center gap-2"><span class="size-3 rounded-badge bg-canvas" />Tanpa preferensi</span>
    </div>
  </div>
</template>
