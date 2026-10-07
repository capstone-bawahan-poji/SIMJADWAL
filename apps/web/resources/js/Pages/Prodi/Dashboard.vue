<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { prodiDashboard as data } from '@/mocks/dashboard';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
</script>

<template>
    <Head title="Dashboard Koor Prodi" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-7">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard Koor Prodi</h1>
                <SampleDataBadge />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ data.constraintCompleteness }}%</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Kelengkapan Constraint &amp; Preferensi</div>
                        <ul class="mt-4 space-y-2">
                            <li v-for="item in data.checklist" :key="item.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-xs">
                                <span class="flex items-center gap-2 text-gray-700">
                                    <span class="w-2 h-2 rounded-full" :class="item.state === 'success' ? 'bg-emerald-500' : 'bg-amber-500'"></span>{{ item.label }}
                                </span>
                                <span class="font-semibold" :class="item.state === 'success' ? 'text-emerald-700' : 'text-amber-700'">{{ item.info }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('prodi.constraints.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Kelola Preferensi Dosen</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ data.schedule.statusLabel }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Status Jadwal Perkuliahan</div>
                        <p class="text-xs text-gray-500 mt-1">{{ data.schedule.decree }}</p>
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-lg font-bold text-gray-800">{{ data.schedule.classes }}</div>
                                <div class="text-[10px] text-gray-400 font-medium">Total Kelas</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-lg font-bold text-gray-800">{{ data.schedule.sks }}</div>
                                <div class="text-[10px] text-gray-400 font-medium">Total SKS</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-lg font-bold text-gray-800">{{ data.schedule.hardConflicts }}</div>
                                <div class="text-[10px] text-gray-400 font-medium">Bentrok Hard</div>
                            </div>
                        </div>
                        <p class="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{{ data.schedule.note }}</p>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('prodi.schedule-matrix.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Lihat Matriks Jadwal Prodi</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
