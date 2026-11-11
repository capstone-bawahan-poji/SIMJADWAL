<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { classLetter } from '@/lib/format';
import { COHORT_LABELS } from '@/lib/tpb';
import type { RunResult } from '@/types/run';
import type { SchedulingDataset } from '@/types/scheduling';

const props = defineProps<{ result: RunResult; dataset: SchedulingDataset | null }>();
const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const rows = computed(() => {
  const d = props.dataset;
  if (!d) return [];
  const slot = new Map(d.time_slots.map((s) => [s.id, s]));
  const room = new Map(d.rooms.map((r) => [r.id, r.code]));
  return d.courses.map((course) => {
    const groups = (d.tpb_groups ?? []).filter((g) => g.course_id === course.id).map((g) => {
      const classes = d.classes.filter((k) => k.tpb_group_id === g.id);
      const details = props.result.schedule_details.filter((s) => s.course_id === course.id && classes.some((k) => k.class_number === s.class_number));
      const slots = [...new Set(details.map((s) => s.time_slot_id))].map((id) => slot.get(id)).filter((s) => !!s)
        .map((s) => `${DAYS[s.day - 1]} sesi ${s.session}`);
      return { group: g, classes, slots, rooms: details.filter((s) => s.meeting_number === 1).map((s) => room.get(s.room_id ?? -1) ?? '-') };
    });
    return { course, groups };
  });
});
</script>

<template>
  <EmptyState v-if="!rows.length" title="Tidak ada data" dashed />
  <div v-else class="overflow-hidden rounded-control border border-line">
    <ul class="max-h-[560px] divide-y divide-line-soft overflow-y-auto">
      <li v-for="r in rows" :key="r.course.id" class="px-4 py-3">
        <p class="text-sm font-semibold text-ink"><span class="mr-2 font-mono text-xs text-ink-tertiary">{{ r.course.code }}</span>{{ r.course.name }}</p>
        <ul class="mt-2 flex flex-col gap-1.5">
          <li v-for="x in r.groups" :key="x.group.id" class="flex flex-wrap items-center gap-2 text-xs text-ink-secondary">
            <span class="font-mono font-semibold">{{ x.group.code }}</span>
            <Badge>{{ COHORT_LABELS[x.group.cohort] }}</Badge>
            <span>Kelas {{ x.classes.map((k) => classLetter(k.class_number)).join(', ') }}</span>
            <span class="font-semibold text-ink">{{ x.slots.join(' dan ') }}</span>
            <span class="text-ink-muted">Ruang {{ x.rooms.join(', ') }}</span>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</template>
