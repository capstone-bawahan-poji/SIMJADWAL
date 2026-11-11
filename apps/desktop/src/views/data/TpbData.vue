<script setup lang="ts">
import { computed, ref } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatStrip from '@/components/ui/StatStrip.vue';
import StatTile from '@/components/ui/StatTile.vue';
import Tabs from '@/components/ui/Tabs.vue';
import Alert from '@/components/ui/Alert.vue';
import { useDataset } from '@/composables/useDataset';
import { classLetter } from '@/lib/format';
import { COHORT_LABELS, tpbConstraintTypes } from '@/lib/tpb';
import type { SchedulingDataset } from '@/types/scheduling';

const props = defineProps<{ dataset: SchedulingDataset }>();
const { setRevision, reviseGroupIds, loading } = useDataset();

type TabKey = 'courses' | 'groups' | 'classes' | 'lecturers' | 'constraints';
const tab = ref<TabKey>('courses');
const tabs = [
  { key: 'courses' as const, label: 'Mata kuliah' },
  { key: 'groups' as const, label: 'Grup TPB' },
  { key: 'classes' as const, label: 'Kelas dan pengampu' },
  { key: 'lecturers' as const, label: 'Dosen' },
  { key: 'constraints' as const, label: 'Constraint' },
];

const courseById = computed(() => new Map(props.dataset.courses.map((c) => [c.id, c])));
const lecturerById = computed(() => new Map(props.dataset.lecturers.map((l) => [l.id, l])));
const programById = computed(() => new Map(props.dataset.study_programs.map((p) => [p.id, p])));
const groups = computed(() => props.dataset.tpb_groups ?? []);
const classesOf = (groupId: number) => props.dataset.classes.filter((k) => k.tpb_group_id === groupId);
const stats = computed(() => [
  { label: 'Mata kuliah', value: props.dataset.courses.length },
  { label: 'Grup', value: groups.value.length },
  { label: 'Kelas', value: props.dataset.classes.length },
  { label: 'Dosen', value: props.dataset.lecturers.length },
  { label: 'Ruang bersama', value: props.dataset.rooms.length },
]);
const constraints = computed(() => tpbConstraintTypes(props.dataset));

const revision = computed(() => props.dataset.revision);
const picked = ref<Set<number>>(new Set(reviseGroupIds.value));
function toggle(id: number) {
  const next = new Set(picked.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  picked.value = next;
}
const lecturerName = (id: number) => lecturerById.value.get(id)?.name ?? `Dosen ${id}`;
</script>

<template>
  <div class="flex flex-col gap-6">
    <StatStrip>
      <StatTile v-for="s in stats" :key="s.label" :label="s.label" :value="s.value" />
    </StatStrip>

    <Alert v-if="revision?.required" title="Jadwal TPB sudah dipublikasikan dan fakultas sudah dijadwalkan">
      Generate ulang hanya dapat menggeser grup yang dipilih, ke slot yang masih kosong di semua jadwal aktif. Grup lain tetap di slot dan ruangnya.
      <span v-if="!revision.revise_group_ids.length" class="font-semibold"> Pilih grup di tab Grup TPB, lalu klik Terapkan revisi.</span>
      <span v-else class="font-semibold"> Revisi aktif untuk {{ revision.revise_group_ids.length }} grup.</span>
    </Alert>

    <Card no-padding>
      <div class="px-6"><Tabs v-model="tab" :tabs="tabs" /></div>

      <div v-if="tab === 'courses'" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-line text-xs text-ink-muted">
            <th class="py-3 pl-6 text-left font-semibold">Kode</th><th class="py-3 text-left font-semibold">Mata kuliah</th>
            <th class="py-3 text-right font-semibold">SKS</th><th class="py-3 text-right font-semibold">Kelas</th><th class="py-3 pr-6 text-right font-semibold">Kapasitas</th>
          </tr></thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="c in dataset.courses" :key="c.id">
              <td class="py-3 pl-6 font-mono text-xs font-semibold text-ink-tertiary">{{ c.code }}</td>
              <td class="py-3 text-ink">{{ c.name }}</td>
              <td class="tabular py-3 text-right">{{ c.sks }}</td>
              <td class="tabular py-3 text-right">{{ c.parallel_class_count }}</td>
              <td class="tabular py-3 pr-6 text-right">{{ c.class_capacity }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="tab === 'groups'" class="flex flex-col">
        <div v-if="revision?.published_run_id && revision.required" class="flex items-center justify-between border-b border-line-soft px-6 py-3 text-xs text-ink-muted">
          <span>{{ picked.size }} grup dipilih untuk direvisi</span>
          <Button size="sm" variant="primary" :loading="loading" :disabled="!picked.size" @click="setRevision([...picked])">Terapkan revisi</Button>
        </div>
        <EmptyState v-if="!groups.length" title="Belum ada grup" />
        <ul v-else class="divide-y divide-line-soft">
          <li v-for="g in groups" :key="g.id" class="flex gap-4 px-6 py-4">
            <input v-if="revision?.published_run_id && revision.required" type="checkbox" class="mt-1" :checked="picked.has(g.id)" :aria-label="`Revisi grup ${g.code}`" @change="toggle(g.id)" />
            <div class="min-w-0 flex-1">
              <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                <span class="font-mono text-xs text-ink-tertiary">{{ courseById.get(g.course_id)?.code }}</span>
                {{ courseById.get(g.course_id)?.name }} · {{ g.code }}
                <Badge>{{ COHORT_LABELS[g.cohort] }}</Badge>
              </p>
              <p class="mt-1 text-xs text-ink-muted">Kelas {{ classesOf(g.id).map((k) => classLetter(k.class_number)).join(', ') }}</p>
              <p class="mt-1 flex flex-wrap gap-1.5">
                <Badge v-for="p in g.study_programs" :key="p.id" tone="primary">{{ programById.get(p.id)?.code }}<template v-if="p.target_semester"> · smt {{ p.target_semester }}</template></Badge>
                <span v-if="g.cohort === 'pilihan'" class="text-xs text-ink-muted">Semua prodi</span>
              </p>
            </div>
          </li>
        </ul>
      </div>

      <div v-else-if="tab === 'classes'" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-line text-xs text-ink-muted">
            <th class="py-3 pl-6 text-left font-semibold">Mata kuliah</th><th class="py-3 text-left font-semibold">Kelas</th>
            <th class="py-3 text-left font-semibold">Grup</th><th class="py-3 pr-6 text-left font-semibold">Dosen pengampu</th>
          </tr></thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="k in dataset.classes" :key="`${k.course_id}-${k.class_number}`">
              <td class="py-3 pl-6 text-ink">{{ courseById.get(k.course_id)?.name }}</td>
              <td class="py-3 font-semibold">{{ classLetter(k.class_number) }}</td>
              <td class="py-3 font-mono text-xs">{{ groups.find((g) => g.id === k.tpb_group_id)?.code }}</td>
              <td class="py-3 pr-6 text-ink-secondary">{{ lecturerName(k.lecturer_id) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="tab === 'lecturers'" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-line text-xs text-ink-muted">
            <th class="py-3 pl-6 text-left font-semibold">Dosen</th><th class="py-3 text-left font-semibold">NIP</th><th class="py-3 pr-6 text-right font-semibold">Kelas TPB</th>
          </tr></thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="l in dataset.lecturers" :key="l.id">
              <td class="py-3 pl-6 text-ink">{{ l.name }}</td>
              <td class="py-3 font-mono text-xs">{{ l.nip }}</td>
              <td class="tabular py-3 pr-6 text-right">{{ dataset.classes.filter((k) => k.lecturer_id === l.id).length }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <ul v-else class="divide-y divide-line-soft">
        <li v-for="c in constraints" :key="c.code" class="flex items-center gap-4 px-6 py-4">
          <span class="w-20 shrink-0 font-mono text-xs font-semibold" :class="c.category === 'HC' ? 'text-danger-ink' : 'text-ink-secondary'">{{ c.code }}</span>
          <span class="flex-1 text-sm text-ink-secondary">{{ c.description }}</span>
          <span class="tabular w-16 text-right text-sm font-semibold text-ink">{{ c.weight }}</span>
        </li>
        <li class="px-6 py-4 text-xs text-ink-muted">Dosen TPB tidak mengatur preferensi slot (tanpa SC_INGIN dan SC_HINDARI). Slot dan ruang ditentukan algoritma; hasil run final dan tidak diubah manual.</li>
      </ul>
    </Card>
  </div>
</template>
