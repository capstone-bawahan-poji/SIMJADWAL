<script setup lang="ts">
import { computed, ref } from 'vue';
import Card from '@/components/ui/Card.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import type { DatasetConstraintType, DatasetStudyProgram } from '@/types/scheduling';

const props = defineProps<{
  constraintTypes: DatasetConstraintType[];
  programs: DatasetStudyProgram[];
}>();

const { scOverrides, setOverride, clearOverrides } = useGaConfig();

const tab = ref<'default' | 'override'>('default');
const hard = computed(() => props.constraintTypes.filter((t) => t.category === 'HC'));
const soft = computed(() => props.constraintTypes.filter((t) => t.category === 'SC'));
const included = computed(() => props.programs.filter((p) => p.included));

function onInput(programId: number, code: string, event: Event) {
  const raw = (event.target as HTMLInputElement).value;
  setOverride(programId, code, raw === '' ? null : Number(raw));
  (event.target as HTMLInputElement).value = String(overrideOf(programId, code));
}

function overrideOf(programId: number, code: string) {
  return scOverrides.value[String(programId)]?.[code] ?? '';
}
</script>

<template>
  <Card>
    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Bobot Constraint</p>
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-4 w-fit">
      <button
        v-for="t in (['default', 'override'] as const)"
        :key="t"
        type="button"
        class="px-4 py-1.5 rounded-lg text-xs font-semibold transition"
        :class="tab === t ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        @click="tab = t"
      >
        {{ t === 'default' ? 'Default Fakultas' : 'Override per Prodi' }}
      </button>
    </div>

    <div v-if="tab === 'default'">
      <ul class="divide-y divide-gray-50">
        <li v-for="c in [...hard, ...soft]" :key="c.code" class="flex items-center gap-3 py-2">
          <span class="w-24 shrink-0 font-mono text-xs font-bold text-gray-700">{{ c.code }}</span>
          <span class="flex-1 text-sm text-gray-700">{{ c.description }}</span>
          <span class="w-16 text-right text-sm font-semibold text-gray-900">{{ c.weight }}</span>
        </li>
      </ul>
      <p class="mt-3 text-[11px] text-gray-400">Bobot global diatur di web: Kelola Constraint.</p>
    </div>

    <div v-else class="overflow-x-auto">
      <p v-if="!included.length" class="text-sm text-gray-500">Belum ada prodi yang ikut dijadwalkan.</p>
      <table v-else class="w-full text-xs">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left py-2 pr-4 font-semibold text-gray-600">Prodi</th>
            <th v-for="c in soft" :key="c.code" class="py-2 px-2 font-semibold text-gray-600 text-center whitespace-nowrap">{{ c.code }}</th>
            <th class="py-2 pl-2" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in included" :key="p.id" class="border-b border-gray-50 last:border-0">
            <td class="py-2 pr-4 font-medium text-gray-700 whitespace-nowrap">{{ p.name }}</td>
            <td v-for="c in soft" :key="c.code" class="py-1 px-1 text-center">
              <input
                type="number"
                min="0"
                step="0.5"
                :value="overrideOf(p.id, c.code)"
                :placeholder="String(c.weight)"
                :aria-label="`${c.code} untuk ${p.name}`"
                class="w-20 h-8 rounded border border-slate-200 px-2 text-xs text-right focus:outline-none focus:ring-1 focus:ring-primary"
                @change="onInput(p.id, c.code, $event)"
              />
            </td>
            <td class="py-1 pl-2">
              <button
                type="button"
                class="text-[11px] text-gray-500 hover:text-gray-800 px-1.5 py-0.5 rounded hover:bg-gray-100 transition"
                :disabled="!scOverrides[String(p.id)]"
                @click="clearOverrides(p.id)"
              >
                Reset
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="mt-3 text-[11px] text-gray-400">Kosongkan sel untuk memakai bobot global. Bobot harus lebih dari 0. Hard constraint tidak dapat di-override.</p>
    </div>
  </Card>
</template>