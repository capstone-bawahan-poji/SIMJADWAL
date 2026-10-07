<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SampleDataBadge from '@/Components/shared/SampleDataBadge.vue';
import { fakultasDashboard as data } from '@/mocks/dashboard';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';

const toneClass = { amber: 'text-amber-600', gray: 'text-gray-500', red: 'text-red-600' } as const;
</script>

<template>
    <Head title="Dashboard Admin Fakultas" />

    <AppLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-7">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard Admin Fakultas</h1>
                    <SampleDataBadge />
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ data.period }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ data.constraints.ready }}/{{ data.constraints.total }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Kelengkapan Constraint Prodi</div>
                        <p class="text-xs text-gray-500 mt-1.5">{{ data.constraints.ready }} program studi terverifikasi.</p>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('fakultas.constraints.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Kelola Constraint Prodi</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ data.ga.fitness.toFixed(2) }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Status Run Penjadwalan GA</div>
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl"><div class="text-lg font-bold text-gray-800">{{ data.ga.classes }}</div><div class="text-[10px] text-gray-400 font-medium">Total Kelas</div></div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl"><div class="text-lg font-bold text-gray-800">{{ data.ga.generations }}</div><div class="text-[10px] text-gray-400 font-medium">Generasi</div></div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl"><div class="text-lg font-bold text-gray-800">{{ data.ga.hardConflicts }}</div><div class="text-[10px] text-gray-400 font-medium">Bentrok Hard</div></div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('fakultas.ga.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Lihat Hasil Matriks</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ data.problemRooms.length }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Ruangan Bermasalah</div>
                        <ul class="mt-4 space-y-2">
                            <li v-for="room in data.problemRooms" :key="room.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-xs">
                                <span class="text-gray-700"><span class="font-bold">{{ room.code }}</span> ({{ room.course }})</span>
                                <span class="font-semibold" :class="toneClass[room.tone]">{{ room.status }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('fakultas.rooms.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Kelola Alokasi Ruangan</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm">
                    <div>
                        <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ data.conflicts.length }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-1">Bentrok Antar Prodi</div>
                        <ul class="mt-4 space-y-2">
                            <li v-for="conflict in data.conflicts" :key="conflict.id" class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-xs">
                                <div class="flex justify-between font-semibold text-gray-800"><span>{{ conflict.room }}</span><span class="text-gray-500">{{ conflict.time }}</span></div>
                                <p class="mt-0.5 text-gray-500">{{ conflict.detail }}</p>
                            </li>
                        </ul>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('fakultas.rooms.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover group">
                            <span>Selesaikan Bentrok Ruangan</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
