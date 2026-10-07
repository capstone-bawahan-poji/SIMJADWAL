<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { scheduleDays, scheduleMatrix, scheduleSessions } from '@/mocks/schedule';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Matriks Jadwal Prodi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Matriks Jadwal Prodi</h1>
                <SampleDataBadge />
            </div>

            <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3 text-left">Sesi</th>
                                <th v-for="day in scheduleDays" :key="day" class="px-4 py-3 text-center">{{ day }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="session in scheduleSessions" :key="session.id" class="align-top">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ session.id }}</div>
                                    <div class="text-[11px] text-gray-400">{{ session.time }}</div>
                                    <div class="text-[10px] text-gray-300">{{ session.duration }}</div>
                                </td>
                                <td v-for="day in scheduleDays" :key="day" class="p-2">
                                    <div v-if="scheduleMatrix[session.id][day].type === 'Kosong'" class="flex h-full min-h-24 items-center justify-center rounded-xl border border-dashed border-gray-200 text-[11px] text-gray-400">
                                        {{ scheduleMatrix[session.id][day].message }}
                                    </div>
                                    <div v-else class="space-y-1 rounded-xl border p-3" :class="scheduleMatrix[session.id][day].type === 'Praktikum' ? 'border-purple-100 bg-purple-50/50' : 'border-blue-100 bg-blue-50/50'">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-[10px] font-bold text-gray-600">{{ scheduleMatrix[session.id][day].code }}</span>
                                            <span class="text-[10px] font-semibold text-gray-500">{{ scheduleMatrix[session.id][day].className }}</span>
                                        </div>
                                        <p class="font-bold leading-snug text-gray-900">{{ scheduleMatrix[session.id][day].course }}</p>
                                        <p class="text-[11px] text-gray-500">{{ scheduleMatrix[session.id][day].lecturer }}</p>
                                        <p class="text-[11px] text-gray-500">
                                            {{ scheduleMatrix[session.id][day].room }}
                                            <template v-if="scheduleMatrix[session.id][day].capacity"> • {{ scheduleMatrix[session.id][day].capacity }} kursi</template>
                                        </p>
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
