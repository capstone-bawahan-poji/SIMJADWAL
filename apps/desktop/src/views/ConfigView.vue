<script setup lang="ts">
import { ref, reactive } from 'vue';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';

const runMode = ref<'single' | 'multi'>('single');
const operator = ref<'TS' | 'TSR'>('TS');
const activeWeightTab = ref<'default' | 'override'>('default');
const selectedPreset = ref('Default Fakultas');
const showRunDialog = ref(false);

const params = reactive({
  pop_size: 100,
  generasi_maks: 500,
  batas_stagnasi: 200,
  crossover_rate: 0.85,
  mutation_rate: 0.02,
  selection_size: 5,
  elitism_count: 2,
  target_fitness: '',
});

const errors = reactive({
  pop_size: '',
  generasi_maks: '',
  crossover_rate: '',
  mutation_rate: '',
  selection_size: '',
  elitism_count: '',
});

const presets = ref(['Default Fakultas', 'Eksplorasi Luas', 'Konvergensi Cepat']);

const dataStats = {
  prodi: 8,
  matkul: 312,
  dosen: 140,
  ruangan: 62,
};

const hardConstraints = [
  { id: 'HC1', label: 'HC1 — Dosen sesuai mata kuliah', weight: ref(1000) },
  { id: 'HC2', label: 'HC2 — Tidak bentrok jadwal dosen', weight: ref(1000) },
  { id: 'HC3', label: 'HC3 — Tidak bentrok semester & kelas', weight: ref(1000) },
  { id: 'HC4', label: 'HC4 — Batas kelas paralel', weight: ref(1000) },
];

const softConstraints = [
  { id: 'SC1', label: 'Preferensi sesi dosen', weight: ref(10) },
  { id: 'SC9', label: 'Kesesuaian SKS–waktu (SC9)', weight: ref(10) },
];

function validate() {
  let ok = true;
  errors.pop_size = params.pop_size < 10 ? 'Wajib diisi.' : '';
  errors.generasi_maks = params.generasi_maks < 500 ? 'Generasi minimal 500.' : '';
  errors.crossover_rate = (params.crossover_rate < 0 || params.crossover_rate > 1) ? 'Nilai harus antara 0 dan 1.' : '';
  errors.mutation_rate = (params.mutation_rate < 0 || params.mutation_rate > 1) ? 'Nilai harus antara 0 dan 1.' : '';
  errors.selection_size = params.selection_size > params.pop_size ? 'Tournament size tidak boleh melebihi ukuran populasi.' : '';
  errors.elitism_count = params.elitism_count >= params.pop_size ? 'Elitism harus lebih kecil dari ukuran populasi.' : '';
  Object.values(errors).forEach(e => { if (e) ok = false; });
  return ok;
}

function handleStartRun() {
  if (validate()) showRunDialog.value = true;
}

function confirmRun() {
  showRunDialog.value = false;
  alert('Run dimulai! (akan dihubungkan ke Tauri command)');
}

function resetDefault() {
  params.pop_size = 100;
  params.generasi_maks = 500;
  params.batas_stagnasi = 200;
  params.crossover_rate = 0.85;
  params.mutation_rate = 0.02;
  params.selection_size = 5;
  params.elitism_count = 2;
  params.target_fitness = '';
  operator.value = 'TS';
  runMode.value = 'single';
}
</script>

<template>
  <div class="flex flex-col h-full overflow-hidden">
    <!-- Scrollable body -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

        <!-- Page title -->
        <h1 class="text-2xl font-bold text-gray-900">Konfigurasi Parameter GA</h1>

        <p role="status" class="text-sm text-gray-500">
          <span class="font-semibold text-gray-700">Perhatian:</span>
          Pastikan data dan constraint sudah diperbarui sebelum memulai run.
        </p>

        <!-- Preset row -->
        <Card>
          <div class="flex flex-wrap items-center gap-3">
            <label class="text-xs font-bold uppercase tracking-wider text-gray-500">Preset</label>
            <select
              v-model="selectedPreset"
              class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option v-for="p in presets" :key="p">{{ p }}</option>
            </select>
            <button class="h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">Simpan Preset</button>
            <button
              :disabled="selectedPreset === 'Default Fakultas'"
              class="h-9 px-3 rounded-lg bg-red-600 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
            >Hapus</button>
          </div>
        </Card>

        <!-- Two-column grid -->
        <div class="grid grid-cols-12 gap-5 items-start">

          <!-- LEFT: form cards -->
          <div class="col-span-8 space-y-5">

            <!-- Card 1: Mode Run -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">1. Mode Run</p>
              <div class="grid grid-cols-2 gap-3">
                <label
                  v-for="mode in (['single', 'multi'] as const)"
                  :key="mode"
                  class="flex flex-col gap-1 p-4 rounded-xl border-2 cursor-pointer transition"
                  :class="runMode === mode ? 'border-primary bg-primary/[0.06]' : 'border-slate-200 hover:border-slate-300'"
                  @click="runMode = mode"
                >
                  <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0"
                         :class="runMode === mode ? 'border-primary' : 'border-slate-300'">
                      <div v-if="runMode === mode" class="w-2 h-2 rounded-full bg-primary" />
                    </div>
                    <span class="text-sm font-bold" :class="runMode === mode ? 'text-primary' : 'text-gray-700'">
                      {{ mode === 'single' ? 'Single-run' : 'Multi-run' }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 pl-6">
                    {{ mode === 'single' ? 'Satu kali jalan dengan satu kombinasi parameter.' : 'Beberapa kombinasi parameter dan operator, lalu dibandingkan.' }}
                  </p>
                </label>
              </div>

              <!-- Multi-run extras -->
              <div v-if="runMode === 'multi'" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                <p class="text-xs font-semibold text-gray-600">Operator yang dipakai:</p>
                <div class="flex gap-4">
                  <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" checked class="rounded text-primary focus:ring-primary" /> TS
                  </label>
                  <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" checked class="rounded text-primary focus:ring-primary" /> TSR
                  </label>
                </div>
                <label class="block text-xs font-semibold text-gray-600">Pengulangan per operator
                  <input type="number" value="3" min="1" max="10"
                    class="ml-2 w-16 h-8 rounded-lg border border-slate-300 px-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </label>
              </div>
            </Card>

            <!-- Card 2: Operator Seleksi -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">2. Operator Seleksi</p>
              <div class="grid grid-cols-2 gap-3 mb-4">
                <label
                  v-for="op in (['TS', 'TSR'] as const)"
                  :key="op"
                  class="flex flex-col gap-1.5 p-4 rounded-xl border-2 cursor-pointer transition"
                  :class="operator === op ? 'border-primary bg-primary/[0.06]' : 'border-slate-200 hover:border-slate-300'"
                  @click="operator = op"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-bold" :class="operator === op ? 'text-primary' : 'text-gray-700'">{{ op }}</span>
                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                         :class="operator === op ? 'border-primary' : 'border-slate-300'">
                      <div v-if="operator === op" class="w-2 h-2 rounded-full bg-primary" />
                    </div>
                  </div>
                  <p class="text-xs text-gray-500 leading-relaxed">
                    {{ op === 'TS' ? 'Tournament Selection. Dua kromosom acak diadu, fitness lebih tinggi menang.' : 'Tournament Selection + Roulette Wheel. Kandidat dipilih lewat roulette, lalu diadu.' }}
                  </p>
                  <span v-if="op === 'TS'" class="self-start text-[10px] font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded-full">Rekomendasi</span>
                </label>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Tournament size (k)</label>
                  <input v-model.number="params.selection_size" type="number" min="2" max="20"
                    class="w-full h-10 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    :class="errors.selection_size ? 'border-red-400' : 'border-slate-300'"
                    />
                  <p v-if="errors.selection_size" class="text-xs text-red-600">{{ errors.selection_size }}</p>
                  <p v-else class="text-[11px] text-gray-400">Jumlah peserta tiap turnamen</p>
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Elitism count</label>
                  <input v-model.number="params.elitism_count" type="number" min="0" max="20"
                    class="w-full h-10 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    :class="errors.elitism_count ? 'border-red-400' : 'border-slate-300'"
                    />
                  <p v-if="errors.elitism_count" class="text-xs text-red-600">{{ errors.elitism_count }}</p>
                  <p v-else class="text-[11px] text-gray-400">Kromosom terbaik yang dipertahankan</p>
                </div>
              </div>
            </Card>

            <!-- Card 3: Populasi & Generasi -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">3. Populasi &amp; Generasi</p>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Ukuran populasi (N)</label>
                  <input v-model.number="params.pop_size" type="number" min="10" max="500"
                    class="w-full h-10 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    :class="errors.pop_size ? 'border-red-400' : 'border-slate-300'"
                    />
                  <p v-if="errors.pop_size" class="text-xs text-red-600">{{ errors.pop_size }}</p>
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Generasi maksimum</label>
                  <input v-model.number="params.generasi_maks" type="number" min="500" max="2000"
                    class="w-full h-10 rounded-lg border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    :class="errors.generasi_maks ? 'border-red-400' : 'border-slate-300'"
                    />
                  <p v-if="errors.generasi_maks" class="text-xs text-red-600">{{ errors.generasi_maks }}</p>
                  <p v-else class="text-[11px] text-gray-400">Minimal 500</p>
                </div>
              </div>
            </Card>

            <!-- Card 4: Crossover & Mutasi -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-4">4. Crossover &amp; Mutasi</p>
              <div class="space-y-5">
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-gray-700">Crossover rate (Pc)</label>
                    <span class="text-sm font-semibold text-primary">{{ params.crossover_rate.toFixed(2) }}</span>
                  </div>
                  <input v-model.number="params.crossover_rate" type="range" min="0" max="1" step="0.01" class="w-full" />
                  <div class="flex justify-between text-[10px] text-gray-400"><span>0</span><span>Std: 0.85</span><span>1</span></div>
                  <p v-if="errors.crossover_rate" class="text-xs text-red-600">{{ errors.crossover_rate }}</p>
                </div>
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-gray-700">Mutation rate (Pm)</label>
                    <span class="text-sm font-semibold text-primary">{{ params.mutation_rate.toFixed(3) }}</span>
                  </div>
                  <input v-model.number="params.mutation_rate" type="range" min="0" max="1" step="0.001" class="w-full" />
                  <div class="flex justify-between text-[10px] text-gray-400"><span>0</span><span>Std: 0.02</span><span>1</span></div>
                  <p v-if="errors.mutation_rate" class="text-xs text-red-600">{{ errors.mutation_rate }}</p>
                </div>
                <p class="text-xs text-gray-400">Pengkodean bilangan asli · Crossover 1 titik · Mutasi 1 titik</p>
              </div>
            </Card>

            <!-- Card 5: Terminasi -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">5. Terminasi</p>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Batas stagnasi</label>
                  <input v-model.number="params.batas_stagnasi" type="number" min="50" max="1000"
                    class="w-full h-10 rounded-lg border border-slate-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                  <p class="text-[11px] text-gray-400">Berhenti jika fitness tidak membaik</p>
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-semibold text-gray-700">Target fitness <span class="text-gray-400 font-normal">(opsional)</span></label>
                  <input v-model="params.target_fitness" type="number" placeholder="—"
                    class="w-full h-10 rounded-lg border border-slate-300 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
              </div>
              <p class="mt-3 text-[11px] text-gray-400">Minimal 500 generasi, maksimum 1.000 generasi.</p>
            </Card>

            <!-- Card 6: Bobot Constraint -->
            <Card>
              <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">6. Bobot Constraint</p>
              <!-- Tabs -->
              <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-4 w-fit">
                <button
                  v-for="tab in (['default', 'override'] as const)"
                  :key="tab"
                  type="button"
                  class="px-4 py-1.5 rounded-lg text-xs font-semibold transition"
                  :class="activeWeightTab === tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                  @click="activeWeightTab = tab"
                >
                  {{ tab === 'default' ? 'Default Fakultas' : 'Override per Prodi' }}
                </button>
              </div>

              <!-- Default tab -->
              <div v-if="activeWeightTab === 'default'" class="space-y-2">
                <div v-for="c in hardConstraints" :key="c.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="shrink-0 text-xs text-gray-500">Hard</span>
                    <span class="text-sm text-gray-700 truncate">{{ c.label }}</span>
                  </div>
                  <input v-model.number="c.weight.value" type="number" min="0"
                    class="w-24 h-8 rounded-lg border border-slate-300 px-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
                <div v-for="c in softConstraints" :key="c.id" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="shrink-0 text-xs text-gray-500">Soft</span>
                    <span class="text-sm text-gray-700 truncate">{{ c.label }}</span>
                  </div>
                  <input v-model.number="c.weight.value" type="number" min="0"
                    class="w-24 h-8 rounded-lg border border-slate-300 px-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
              </div>

              <!-- Override tab -->
              <div v-else class="overflow-x-auto">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-gray-100">
                      <th class="text-left py-2 pr-4 font-semibold text-gray-600 whitespace-nowrap">Prodi</th>
                      <th v-for="c in [...hardConstraints, ...softConstraints]" :key="c.id"
                          class="py-2 px-2 font-semibold text-gray-600 text-center whitespace-nowrap">{{ c.id }}</th>
                      <th class="py-2 pl-2"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="prodi in ['Teknik Informatika','Sistem Informasi','Teknik Sipil']" :key="prodi"
                        class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition">
                      <td class="py-2 pr-4 font-medium text-gray-700 whitespace-nowrap">{{ prodi }}</td>
                      <td v-for="c in [...hardConstraints, ...softConstraints]" :key="c.id" class="py-1 px-1 text-center">
                        <input type="number" :value="c.weight.value" min="0"
                          class="w-16 h-7 rounded border border-slate-200 px-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-primary bg-gray-50" />
                      </td>
                      <td class="py-1 pl-2">
                        <button class="text-[10px] text-gray-400 hover:text-red-500 px-1.5 py-0.5 rounded hover:bg-red-50 transition">Reset</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </Card>

          </div><!-- /left -->

          <!-- RIGHT: sticky summary -->
          <div class="col-span-4">
            <div class="sticky top-4 space-y-4">
              <Card>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-4">Ringkasan Run</p>
                <dl class="space-y-2 text-sm">
                  <div class="flex justify-between"><dt class="text-gray-500">Mode</dt><dd class="font-semibold text-gray-900">{{ runMode === 'single' ? 'Single-run' : 'Multi-run' }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Operator</dt><dd class="font-semibold text-gray-900">{{ operator }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Populasi</dt><dd class="font-semibold text-gray-900">{{ params.pop_size }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Generasi</dt><dd class="font-semibold text-gray-900">{{ params.generasi_maks }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Pc / Pm</dt><dd class="font-semibold text-gray-900">{{ params.crossover_rate.toFixed(2) }} / {{ params.mutation_rate.toFixed(3) }}</dd></div>
                </dl>

                <div class="my-4 border-t border-gray-100" />

                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Data Dipakai</p>
                <dl class="space-y-1.5 text-sm">
                  <div class="flex justify-between"><dt class="text-gray-500">Prodi</dt><dd class="font-semibold text-gray-900">{{ dataStats.prodi }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Mata kuliah</dt><dd class="font-semibold text-gray-900">{{ dataStats.matkul }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Dosen</dt><dd class="font-semibold text-gray-900">{{ dataStats.dosen }}</dd></div>
                  <div class="flex justify-between"><dt class="text-gray-500">Ruangan</dt><dd class="font-semibold text-gray-900">{{ dataStats.ruangan }}</dd></div>
                </dl>

                <div class="mt-5 space-y-2">
                  <Button class="w-full h-11" @click="handleStartRun">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    Mulai Run
                  </Button>
                  <Button variant="ghost" class="w-full" @click="resetDefault">Reset Default</Button>
                </div>
              </Card>
            </div>
          </div><!-- /right -->

        </div><!-- /grid -->
      </div>
    </div><!-- /scroll -->

    <!-- Confirm dialog -->
    <ConfirmDialog
      :show="showRunDialog"
      title="Mulai Run?"
      confirm-label="Mulai"
      @confirm="confirmRun"
      @close="showRunDialog = false"
    >
      <p>Mode <strong>{{ runMode === 'single' ? 'Single-run' : 'Multi-run' }}</strong> dengan operator <strong>{{ operator }}</strong>. Proses mungkin membutuhkan waktu beberapa menit.</p>
      <p class="mt-2 text-amber-700 text-xs font-medium">⚠ Pastikan data sudah diambil dari server sebelum memulai.</p>
    </ConfirmDialog>
  </div>
</template>
