<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import { useGaConfig } from '@/composables/useGaConfig';
import { useGaRunner } from '@/composables/useGaRunner';

defineProps<{ show: boolean }>();
const emit = defineEmits<{ close: [] }>();

const router = useRouter();
const { params, runMode, multiOperators } = useGaConfig();
const { start } = useGaRunner();
const error = ref('');
const starting = ref(false);

const operators = computed(() => (runMode.value === 'multi' ? multiOperators.value : [params.selection_operator]));
const totalRuns = computed(() => operators.value.length * params.run_count);

async function confirm() {
  error.value = '';
  starting.value = true;
  try {
    await start();
    emit('close');
    router.push({ name: 'run' });
  } catch (e) {
    error.value = String(e);
  } finally {
    starting.value = false;
  }
}
</script>

<template>
  <ConfirmDialog
    :show="show"
    title="Jalankan penjadwalan?"
    confirm-label="Mulai run"
    :processing="starting"
    :error="error || null"
    @confirm="confirm"
    @close="error = ''; emit('close')"
  >
    <p>
      Sebanyak {{ totalRuns }} run akan dijalankan dengan operator <strong class="text-ink">{{ operators.join(' dan ') }}</strong>,
      ukuran populasi {{ params.population_size }}, dan maksimum {{ params.max_generations }} generasi.
      Proses ini dapat berlangsung selama beberapa menit.
    </p>
  </ConfirmDialog>
</template>
