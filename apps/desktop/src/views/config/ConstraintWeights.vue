<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import { colorOf, programColorMap } from '@/lib/programColors';
import type { DatasetConstraintType, DatasetStudyProgram } from '@/types/scheduling';

const props = defineProps<{
  constraintTypes: DatasetConstraintType[];
  programs: DatasetStudyProgram[];
  tpb?: boolean;
}>();

const { scOverrides, setOverride, clearOverrides } = useGaConfig();

const tab = ref<'default' | 'override'>('default');
const hard = computed(() => props.constraintTypes.filter((t) => t.category === 'HC'));
const soft = computed(() => props.constraintTypes.filter((t) => t.category === 'SC'));
const included = computed(() => props.programs.filter((p) => p.included));
const colors = computed(() => programColorMap(props.programs));
const overrideCount = computed(() => Object.keys(scOverrides.value).length);

function onInput(programId: number, code: string, event: Event) {
  const raw = (event.target as HTMLInputElement).value;
  setOverride(programId, code, raw === '' ? null : Number(raw));
  (event.target as HTMLInputElement).value = String(overrideOf(programId, code));
}

function hasOverride(programId: number, code: string) {
  return scOverrides.value[String(programId)]?.[code] !== undefined;
}

function overrideOf(programId: number, code: string) {
  return scOverrides.value[String(programId)]?.[code] ?? '';
}
</script>

<template>
  <Card no-padding>
    <div class="flex flex-wrap items-center justify-between gap-4 px-6 pt-6">
      <div>
        <h2 class="text-base font-semibold text-ink">Bobot constraint</h2>
        <p class="mt-1 text-xs text-ink-muted">{{ tpb ? 'Constraint khusus TPB. Bobot default ditetapkan melalui aplikasi web.' : 'Bobot default ditetapkan melalui menu Kelola Constraint pada aplikasi web.' }}</p>
      </div>
      <SegmentedControl
        v-if="!tpb"
        v-model="tab"
        label="Sumber bobot"
        :options="[
          { value: 'default', label: 'Default fakultas' },
          { value: 'override', label: 'Override per program studi', count: overrideCount },
        ]"
      />
    </div>

    <ul v-if="tpb || tab === 'default'" class="mt-4 divide-y divide-line-soft border-t border-line-soft">
      <li v-for="c in [...hard, ...soft]" :key="c.code" class="flex items-center gap-4 px-6 py-3">
        <span
          class="w-28 shrink-0 whitespace-nowrap font-mono text-xs font-semibold"
          :class="c.category === 'HC' ? 'text-danger-ink' : 'text-ink-secondary'"
        >{{ c.code }}</span>
        <span class="flex-1 text-sm text-ink-secondary">{{ c.description }}</span>
        <span class="tabular w-16 text-right text-sm font-semibold text-ink">{{ c.weight }}</span>
      </li>
    </ul>

    <div v-else class="mt-4 border-t border-line-soft">
      <p v-if="!included.length" class="px-6 py-8 text-sm text-ink-muted">Belum ada program studi yang termasuk dataset.</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-line text-xs text-ink-muted">
              <th class="py-3 pl-6 pr-4 text-left font-semibold">Program studi</th>
              <th v-for="c in soft" :key="c.code" class="whitespace-nowrap px-2 py-3 text-right font-mono font-semibold" :title="c.description">
                {{ c.code }}
                <span class="block font-sans font-normal text-ink-faint">default {{ c.weight }}</span>
              </th>
              <th class="py-3 pl-2 pr-6" />
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="p in included" :key="p.id">
              <td class="py-2 pl-6 pr-4">
                <span class="flex items-center gap-2">
                  <span class="size-3 shrink-0 rounded-badge border" :style="{ background: colorOf(colors, p.id).bg, borderColor: colorOf(colors, p.id).border }" />
                  <span class="font-mono text-xs font-semibold text-ink-tertiary">{{ p.code }}</span>
                  <span class="truncate text-ink">{{ p.name }}</span>
                </span>
              </td>
              <td v-for="c in soft" :key="c.code" class="px-2 py-2 text-right">
                <input
                  type="number"
                  min="0"
                  step="0.5"
                  :value="overrideOf(p.id, c.code)"
                  :placeholder="String(c.weight)"
                  :aria-label="`${c.code} untuk ${p.name}`"
                  class="tabular h-8 w-20 rounded-control border px-2 text-right text-xs placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                  :class="hasOverride(p.id, c.code) ? 'border-primary-tint bg-primary-soft font-semibold text-primary' : 'border-line-strong text-ink'"
                  @change="onInput(p.id, c.code, $event)"
                />
              </td>
              <td class="py-2 pl-2 pr-6 text-right">
                <Button variant="tertiary" size="sm" :disabled="!scOverrides[String(p.id)]" @click="clearOverrides(p.id)">Reset</Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="border-t border-line-soft px-6 py-4 text-xs text-ink-muted">
        Kosongkan sel untuk menggunakan bobot default. Nilai bobot harus lebih dari 0. Bobot hard constraint tidak dapat diubah per program studi.
      </p>
    </div>
  </Card>
</template>
