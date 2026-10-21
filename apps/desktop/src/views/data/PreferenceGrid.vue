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

const LABEL = { want: 'Ingin', avoid: 'Hindari' } as const;
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-xs border-separate border-spacing-1">
      <thead>
        <tr>
          <th class="w-24 text-left font-semibold text-gray-500">Sesi</th>
          <th v-for="day in DAYS" :key="day" class="font-semibold text-gray-500">{{ day }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="session in sessions" :key="session">
          <td class="text-gray-600">
            Sesi {{ session }}
            <span class="block text-[11px] text-gray-400">{{ slotAt(1, session)?.start_time }}–{{ slotAt(1, session)?.end_time }}</span>
          </td>
          <td v-for="(_, index) in DAYS" :key="index" class="h-9 rounded-md text-center font-semibold"
              :class="{
                'bg-teal-100 text-teal-800': cellType(index + 1, session) === 'want',
                'bg-rose-100 text-rose-800': cellType(index + 1, session) === 'avoid',
                'bg-gray-50 text-gray-300': !cellType(index + 1, session),
              }">
            {{ cellType(index + 1, session) ? LABEL[cellType(index + 1, session)!] : '–' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
