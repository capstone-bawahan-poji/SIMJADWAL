<script setup lang="ts">
import type { RunListItem } from '@/types/run';

defineProps<{
  items: RunListItem[];
  selected: Set<string>;
  activeId: string | null;
  recommendedId: string | null;
}>();

defineEmits<{ toggle: [runId: string]; open: [runId: string] }>();

const UPLOAD = {
  pending: { label: 'Belum dikirim', class: 'text-gray-500' },
  uploaded: { label: 'Terkirim', class: 'text-green-700' },
  failed: { label: 'Gagal', class: 'text-red-600' },
} as const;
</script>

<template>
  <table class="w-full text-sm">
    <thead>
      <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100">
        <th class="py-2 px-3 w-8" />
        <th class="py-2 px-3">Run</th>
        <th class="py-2 px-3 text-right">Fitness</th>
        <th class="py-2 px-3 text-right">HC</th>
        <th class="py-2 px-3 text-right">SC tidak terpenuhi</th>
        <th class="py-2 px-3 text-right">Tanpa ruangan</th>
        <th class="py-2 px-3 text-right">Generasi</th>
        <th class="py-2 px-3">Status</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="item in items"
        :key="item.run_id"
        class="border-b border-gray-50 cursor-pointer"
        :class="activeId === item.run_id ? 'bg-primary/[0.06]' : 'hover:bg-gray-50'"
        @click="$emit('open', item.run_id)"
      >
        <td class="py-2 px-3" @click.stop>
          <input
            type="checkbox"
            class="rounded text-primary focus:ring-primary"
            :checked="selected.has(item.run_id)"
            :disabled="item.upload.status === 'uploaded' || item.cancelled"
            :aria-label="`Pilih ${item.operator} run ${item.run_index + 1}`"
            @change="$emit('toggle', item.run_id)"
          />
        </td>
        <td class="py-2 px-3">
          <span class="font-semibold text-gray-900">{{ item.operator }} · run {{ item.run_index + 1 }}</span>
          <span v-if="recommendedId === item.run_id" class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">Rekomendasi</span>
          <span v-if="item.cancelled" class="ml-2 text-[11px] text-gray-400">dibatalkan</span>
        </td>
        <td class="py-2 px-3 text-right text-gray-900">
          {{ item.best_fitness.toFixed(0) }} / {{ item.max_fitness.toFixed(0) }}
          <span class="block text-[11px] text-gray-400">{{ item.summary.fitness_percent.toFixed(1) }}%</span>
        </td>
        <td class="py-2 px-3 text-right" :class="item.summary.hc_violations ? 'text-red-600 font-semibold' : 'text-gray-700'">{{ item.summary.hc_violations }}</td>
        <td class="py-2 px-3 text-right text-gray-700">{{ item.summary.sc_unmet }}</td>
        <td class="py-2 px-3 text-right text-gray-700">{{ item.summary.unassigned_rooms }}</td>
        <td class="py-2 px-3 text-right text-gray-700">{{ item.best_generation }} / {{ item.final_generation }}</td>
        <td class="py-2 px-3 text-xs" :class="UPLOAD[item.upload.status].class" :title="item.upload.error ?? ''">
          {{ UPLOAD[item.upload.status].label }}
        </td>
      </tr>
    </tbody>
  </table>
</template>