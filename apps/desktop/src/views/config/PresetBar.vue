<script setup lang="ts">
import { computed, ref } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import UiInput from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaPresets } from '@/composables/useGaPresets';
import type { GaPresetPayload } from '@/types/ga';

const props = defineProps<{ facultyId: number | null; superAdmin: boolean }>();

const { params, scOverrides, presetId, dirty, errors, applyPreset, markSaved } = useGaConfig();
const { presets, error: loadError, create, update, remove } = useGaPresets();

const selected = computed(() => presets.value.find((p) => p.id === presetId.value) ?? null);
const hasErrors = computed(() => Object.keys(errors.value).length > 0);

const saving = ref(false);
const actionError = ref('');
const dialog = ref<'save-as' | 'delete' | null>(null);
const newName = ref('');

const presetModel = computed({
  get: () => presetId.value,
  set: (id: number | null) => {
    const preset = presets.value.find((p) => p.id === id);
    if (preset) applyPreset(preset);
  },
});

function payload(name: string): GaPresetPayload {
  return {
    ...params,
    name,
    sc_overrides: scOverrides.value,
    ...(props.superAdmin ? (props.facultyId === null ? { scope: 'tpb' as const } : { faculty_id: props.facultyId }) : {}),
  };
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
  <Card no-padding class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center gap-4">
      <label for="preset" class="text-xs font-semibold text-ink-muted">Preset</label>
      <Select id="preset" v-model="presetModel" class="w-72">
        <option :value="null" disabled>Tanpa preset</option>
        <option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name }}</option>
      </Select>
      <Badge v-if="dirty" tone="warning">Perubahan belum disimpan</Badge>

      <div class="ml-auto flex items-center gap-2">
        <Button variant="danger-secondary" :disabled="!selected?.can_delete" @click="actionError = ''; dialog = 'delete'">Hapus preset</Button>
        <Button :disabled="hasErrors" @click="openSaveAs">Simpan sebagai</Button>
        <Button :disabled="!selected?.can_update || !dirty || hasErrors" :loading="saving && !dialog" @click="save">Simpan</Button>
      </div>
    </div>
    <Alert v-if="loadError || (actionError && !dialog)" title="Preset gagal diproses">{{ loadError || actionError }}</Alert>

    <ConfirmDialog
      :show="dialog === 'save-as'"
      title="Simpan sebagai preset baru"
      confirm-label="Simpan"
      :processing="saving"
      :error="actionError || null"
      :confirm-disabled="!newName.trim()"
      @confirm="newName.trim() && saveAs()"
      @close="dialog = null"
    >
      <div class="flex flex-col gap-2">
        <label for="preset-name" class="text-xs font-semibold text-ink-secondary">Nama preset</label>
        <UiInput id="preset-name" v-model="newName" maxlength="100" />
      </div>
    </ConfirmDialog>

    <ConfirmDialog
      :show="dialog === 'delete'"
      title="Hapus preset?"
      confirm-label="Ya, hapus"
      danger
      :processing="saving"
      :error="actionError || null"
      @confirm="destroy"
      @close="dialog = null"
    >
      Preset <strong class="text-ink">{{ selected?.name }}</strong> akan dihapus bagi seluruh admin fakultas ini.
    </ConfirmDialog>
  </Card>
</template>
