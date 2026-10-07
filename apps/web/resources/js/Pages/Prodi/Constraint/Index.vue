<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import {
    lecturerOptions,
    preferenceDays,
    preferenceMatrix,
    preferenceSessions,
    type Preference,
} from '@/mocks/constraint';
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const selectedLecturer = ref(lecturerOptions[0].id);
const matrix = reactive<Record<string, Record<string, Preference>>>(structuredClone(preferenceMatrix));

const NEXT: Record<Preference, Preference> = { Netral: 'Disukai', Disukai: 'Dihindari', Dihindari: 'Netral' };
const TONE: Record<Preference, string> = {
    Disukai: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    Dihindari: 'bg-red-50 text-red-700 border-red-200',
    Netral: 'bg-gray-50 text-gray-500 border-gray-200',
};

function cycle(session: string, day: string): void {
    matrix[session][day] = NEXT[matrix[session][day]];
}
</script>

<template>
    <Head title="Constraint & Preferensi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Constraint &amp; Preferensi</h1>
                    <SampleDataBadge />
                </div>
                <Link :href="route('prodi.constraints.review')" class="inline-flex w-fit items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50">
                    Review &amp; Preview
                </Link>
            </div>

            <div class="flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                <label class="flex items-center gap-3 text-xs font-semibold text-gray-600">
                    Dosen
                    <select v-model="selectedLecturer" class="rounded-xl border-gray-200 bg-gray-50 text-xs focus:border-primary focus:ring-primary">
                        <option v-for="lecturer in lecturerOptions" :key="lecturer.id" :value="lecturer.id">{{ lecturer.label }}</option>
                    </select>
                </label>
                <div class="flex flex-wrap gap-4 text-[11px] font-semibold text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Disukai</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Dihindari</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>Fleksibel (Netral)</span>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-bold text-gray-900">Matriks Ketersediaan Waktu Mengajar</h2>
                    <span class="text-[11px] text-gray-400">Klik sel untuk mengubah status</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-center text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3 text-left">Sesi &amp; Waktu</th>
                                <th v-for="day in preferenceDays" :key="day" class="px-4 py-3">{{ day }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="session in preferenceSessions" :key="session.id">
                                <td class="px-4 py-3 text-left">
                                    <div class="font-bold text-gray-900">{{ session.id }}</div>
                                    <div class="text-[11px] text-gray-400">{{ session.time }}</div>
                                </td>
                                <td v-for="day in preferenceDays" :key="day" class="p-2">
                                    <button type="button" class="w-full rounded-lg border px-2 py-2 font-semibold transition-colors" :class="TONE[matrix[session.id][day]]" @click="cycle(session.id, day)">
                                        {{ matrix[session.id][day] }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-end">
                <Link :href="route('prodi.constraints.review')" class="rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-primary-hover">
                    Lanjut ke Review &amp; Preview
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
