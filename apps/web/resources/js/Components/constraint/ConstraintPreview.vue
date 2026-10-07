<script setup lang="ts">
import { ref, computed } from 'vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { hardConstraints, lecturerPreferences, softConstraints } from '@/mocks/constraint';

/**
 * Read-only constraint summary of one study program. Shared by the prodi review page and the
 * faculty overview. Renders mock data until the constraint API exists.
 */
defineProps<{ programName: string }>();

const search = ref('');
const lecturers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return query ? lecturerPreferences.filter((l) => l.name.toLowerCase().includes(query)) : lecturerPreferences;
});
const totalWeight = softConstraints.reduce((sum, c) => sum + c.weight, 0);
const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as const;
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Hard Constraint (Statis &amp; Wajib)</h3>
                        <p class="text-xs text-gray-500">Aturan baku tanpa pengecualian</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600">{{ hardConstraints.length }} Rules Aktif</span>
                </div>
                <ul class="space-y-2">
                    <li v-for="hc in hardConstraints" :key="hc.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{{ hc.id }}</span>
                            <span class="truncate text-xs font-semibold text-gray-800">{{ hc.name }}</span>
                        </div>
                        <span class="shrink-0 text-[11px] text-gray-500">{{ hc.description }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Bobot Penalti (Soft Constraint) {{ programName }}</h3>
                        <p class="text-xs text-gray-500">Parameter fitness function yang diatur prodi</p>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Bobot</div>
                        <div class="text-xl font-bold text-gray-900">{{ totalWeight }}</div>
                    </div>
                </div>
                <ul class="space-y-2">
                    <li v-for="sc in softConstraints" :key="sc.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="rounded bg-gray-200 px-1.5 py-0.5 font-mono text-[10px] font-bold text-gray-600">{{ sc.id }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-gray-800">{{ sc.name }}</p>
                                <p class="truncate text-[11px] text-gray-500">{{ sc.description }}</p>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="text-sm font-bold text-gray-900">{{ sc.weight }}</span>
                            <span class="ml-1 text-[11px] font-semibold" :class="sc.tone">{{ sc.level }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section class="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Preferensi Waktu Dosen {{ programName }}</h3>
                    <p class="text-xs text-gray-500">Rincian preferensi waktu dan beban mengajar tiap dosen pengampu</p>
                </div>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Filter nama dosen..."
                    aria-label="Filter nama dosen"
                    class="w-full rounded-xl border-gray-200 bg-gray-50 text-xs placeholder-gray-400 focus:border-primary focus:ring-primary md:w-64"
                />
            </div>
            <p v-if="lecturers.length === 0" class="p-6 text-center text-sm text-gray-500">Tidak ada dosen yang cocok.</p>
            <div v-for="lecturer in lecturers" :key="lecturer.id" class="border-b border-gray-100 p-6 last:border-b-0">
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900">{{ lecturer.name }}</h4>
                            <span class="font-mono text-[11px] text-gray-400">{{ lecturer.identityNumber }}</span>
                            <span v-if="lecturer.role" class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600">{{ lecturer.role }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ lecturer.note }}</p>
                    </div>
                    <div class="flex shrink-0 gap-3 text-[11px] font-semibold">
                        <span class="text-emerald-600">{{ lecturer.liked }} slot disukai</span>
                        <span class="text-red-600">{{ lecturer.avoided }} slot dihindari</span>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    <div v-for="day in days" :key="day" class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ day }}</div>
                        <div class="mt-0.5 text-xs font-semibold text-gray-700">{{ lecturer.week[day] }}</div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end border-t border-gray-100 px-6 py-3"><SampleDataBadge /></div>
        </section>
    </div>
</template>
