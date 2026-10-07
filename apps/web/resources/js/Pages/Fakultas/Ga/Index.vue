<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { gaDays, gaMatrix, gaRuns, gaSessions } from '@/mocks/ga';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const selectedId = ref(gaRuns.find((run) => run.selected)?.id ?? gaRuns[0].id);
const selectedRun = computed(() => gaRuns.find((run) => run.id === selectedId.value) ?? gaRuns[0]);
const best = Math.max(...gaRuns.map((run) => run.fitness));

const STATUS_TONE: Record<string, string> = {
    'Baru Masuk': 'bg-blue-50 text-blue-700 border-blue-100',
    'Sedang Direview': 'bg-amber-50 text-amber-700 border-amber-100',
    Arsip: 'bg-gray-100 text-gray-500 border-gray-200',
};
</script>

<template>
    <Head title="Penjadwalan GA" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Review &amp; Tetapkan Hasil Jadwal GA</h1>
                <SampleDataBadge />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Total Run Tersinkron</span>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ gaRuns.length }} Hasil Run</div>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Fitness Tertinggi</span>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ best.toFixed(3) }} <span class="text-sm font-semibold text-gray-400">/ 1.000</span></div>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">Status Jadwal Aktif</span>
                    <div class="mt-1 text-2xl font-bold text-gray-900">Belum Ditetapkan</div>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-4">ID &amp; Tanggal Run</th>
                                <th class="px-6 py-4">Fitness</th>
                                <th class="px-6 py-4">Hard Constraint</th>
                                <th class="px-6 py-4">Parameter GA</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="run in gaRuns" :key="run.id" :class="run.id === selectedId ? 'bg-primary/5' : ''">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">{{ run.id }}</span>
                                        <span v-if="run.recommended" class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">Rekomendasi</span>
                                    </div>
                                    <span class="text-[11px] text-gray-400">{{ run.date }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-900">{{ run.fitness.toFixed(3) }}</span>
                                    <div class="mt-1 h-1.5 w-24 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-primary" :style="{ width: `${run.fitness * 100}%` }"></div></div>
                                </td>
                                <td class="px-6 py-4">{{ run.hardConstraint }}</td>
                                <td class="px-6 py-4 font-mono text-[11px]">{{ run.parameters }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_TONE[run.status]">{{ run.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50" :disabled="run.id === selectedId" @click="selectedId = run.id">
                                        {{ run.id === selectedId ? 'Sedang Dipilih' : 'Lihat Detail' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-bold text-gray-900"><span class="mr-2 font-mono text-primary">{{ selectedRun.id }}</span>Preview Hasil Jadwal Kuliah</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3 text-left">Sesi</th>
                                <th v-for="day in gaDays" :key="day" class="px-4 py-3 text-center">{{ day }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(session, index) in gaSessions" :key="session" class="align-top">
                                <td class="px-4 py-3 font-bold text-gray-900">{{ session }}</td>
                                <td v-for="day in gaDays" :key="day" class="p-2">
                                    <div v-if="gaMatrix[day][index].empty" class="flex min-h-24 items-center justify-center rounded-xl border border-dashed border-gray-200 text-[11px] text-gray-400">Kosong</div>
                                    <div v-else class="space-y-1 rounded-xl border p-3" :class="gaMatrix[day][index].warning ? 'border-amber-200 bg-amber-50/60' : 'border-gray-100 bg-gray-50'">
                                        <div class="flex justify-between gap-2 text-[10px] font-semibold text-gray-500"><span>{{ gaMatrix[day][index].code }}</span><span>{{ gaMatrix[day][index].className }}</span></div>
                                        <p class="font-bold text-gray-900">{{ gaMatrix[day][index].course }}</p>
                                        <p class="text-[11px] text-gray-500">{{ gaMatrix[day][index].room }}</p>
                                        <p class="text-[11px] text-gray-500">{{ gaMatrix[day][index].lecturer }}</p>
                                        <p v-if="gaMatrix[day][index].warning" class="text-[11px] font-semibold text-amber-700">{{ gaMatrix[day][index].warning }}</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
