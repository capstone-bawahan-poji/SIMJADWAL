<script setup lang="ts">
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaPresets } from '@/composables/useGaPresets';
import type { GaPresetPayload } from '@/types/ga';

const props = defineProps<{ facultyId: number; superAdmin: boolean }>();

const { params, scOverrides, presetId, dirty, errors, applyPreset, markSaved } = useGaConfig();
const { presets, error: loadError, create, update, remove } = useGaPresets();

const selected = computed(() => presets.value.find((p) => p.id === presetId.value) ?? null);
const hasErrors = computed(() => Object.keys(errors.value).length > 0);

const saving = ref(false);
const actionError = ref('');
const dialog = ref<'save-as' | 'delete' | null>(null);
const newName = ref('');

function payload(name: string): GaPresetPayload {
  return {
    ...params,
    name,
    sc_overrides: scOverrides.value,
    ...(props.superAdmin ? { faculty_id: props.facultyId } : {}),
  };
}

function onSelect(event: Event) {
  const preset = presets.value.find((p) => p.id === Number((event.target as HTMLSelectElement).value));
  if (preset) applyPreset(preset);
}

async function run(action: () => Promise<void>) {
  saving.value = true;
  actionError.value = '';
  try {
    await action();
    dialog.value = null;
  } catch (e) {
    actionError.value = (e as Error).message;
  } finally {
    saving.value = false;
  }
}

const save = () => run(async () => {
  if (!selected.value) return;
  markSaved((await update(selected.value.id, payload(selected.value.name))).id);
});

const saveAs = () => run(async () => {
  markSaved((await create(payload(newName.value.trim()))).id);
});

const destroy = () => run(async () => {
  if (!selected.value) return;
  await remove(selected.value.id);
  markSaved(null);
});

function openSaveAs() {
  newName.value = selected.value ? `${selected.value.name} (salinan)` : '';
  actionError.value = '';
  dialog.value = 'save-as';
}
</script>

<template>
  <Card>
    <div class="flex flex-wrap items-center gap-3">
      <label for="preset" class="text-xs font-bold uppercase tracking-wider text-gray-500">Preset</label>
      <select
        id="preset"
        :value="presetId ?? ''"
        class="h-9 min-w-56 rounded-lg border border-slate-300 bg-white px-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary"
        @change="onSelect"
      >
        <option value="" disabled>Tanpa preset</option>
        <option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <span v-if="dirty" class="text-xs font-medium text-amber-700">Belum disimpan</span>

      <div class="ml-auto flex gap-2">
        <Button variant="ghost" :disabled="!selected?.can_update || !dirty || hasErrors" :loading="saving && !dialog" @click="save">Simpan</Button>
        <Button variant="ghost" :disabled="hasErrors" @click="openSaveAs">Simpan sebagai…</Button>
        <Button variant="danger" :disabled="!selected?.can_delete" @click="actionError = ''; dialog = 'delete'">Hapus</Button>
      </div>
    </div>
    <div v-if="loadError || (actionError && !dialog)" class="mt-3">
      <Alert title="Preset gagal diproses">{{ loadError || actionError }}</Alert>
    </div>

    <ConfirmDialog
      :show="dialog === 'save-as'"
      title="Simpan sebagai preset baru"
      confirm-label="Simpan"
      :processing="saving"
      :error="actionError || null"
      @confirm="newName.trim() && saveAs()"
      @close="dialog = null"
    >
      <label for="preset-name" class="text-xs font-semibold text-gray-700">Nama preset</label>
      <input
        id="preset-name"
        v-model="newName"
        maxlength="100"
        class="mt-1 w-full h-10 rounded-lg border border-slate-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
      />
    </ConfirmDialog>

    <ConfirmDialog
      :show="dialog === 'delete'"
      title="Hapus preset?"
      confirm-label="Hapus"
      danger
      :processing="saving"
      :error="actionError || null"
      @confirm="destroy"
      @close="dialog = null"
    >
      Preset <strong>{{ selected?.name }}</strong> akan dihapus untuk semua admin fakultas ini.
    </ConfirmDialog>
  </Card>
</template>