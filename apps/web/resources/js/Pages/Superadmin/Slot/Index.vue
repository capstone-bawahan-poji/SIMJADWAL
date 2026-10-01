<script setup lang="ts">
import { ref } from 'vue';
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

const viewMode = ref('table');
</script>

<template>
    <Head title="Data Jadwal" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-7 max-w-7xl w-full mx-auto">
            <!-- Page Header -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Data Jadwal</h2>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Button variant="ghost" class="bg-white border-gray-200 text-gray-700 shadow-sm hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                        Filter Hari Aktif
                    </Button>
                    <Button variant="ghost" class="bg-white border-gray-200 text-gray-700 shadow-sm hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                        Reset Default
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1 -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Total Slot Aktif</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">20 <span class="text-xs font-semibold text-gray-400">Slot</span></h3>
                </div>
                <!-- Card 2 -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Hari Operasional</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">5 <span class="text-xs font-semibold text-gray-400">Hari</span></h3>
                </div>
                <!-- Card 3 -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Sesi per Hari</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">4 <span class="text-xs font-semibold text-gray-400">Sesi</span></h3>
                </div>
                <!-- Card 4 -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-center">
                    <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase">Slot Diedit Manual</p>
                    <h3 class="text-2xl font-extrabold text-gray-900 mt-1">3 <span class="text-xs font-semibold text-gray-900">Slot Kustom</span></h3>
                </div>
            </div>

            <!-- Matrix Grid Section -->
            <Card class="!p-0 border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 lg:p-6 pb-0 border-b border-gray-100 bg-white">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5">
                        <div>
                            <h3 class="font-bold text-base text-gray-900">Matriks Slot Waktu Mingguan</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Atur jam mulai, selesai, dan tipe sesi per kombinasi hari</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center bg-gray-50 border border-gray-200 rounded-xl p-1 text-xs">
                                <button @click="viewMode = 'grid'" :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', viewMode === 'grid' ? 'bg-white font-semibold text-primary shadow-sm' : 'text-gray-500 hover:text-gray-800']" type="button">Tampilan Grid</button>
                                <button @click="viewMode = 'table'" :class="['px-3 py-1.5 rounded-lg font-medium transition-colors', viewMode === 'table' ? 'bg-white font-semibold text-primary shadow-sm' : 'text-gray-500 hover:text-gray-800']" type="button">Tampilan Tabel</button>
                            </div>
                            <button class="p-2 border border-gray-200 rounded-xl text-gray-500 hover:bg-gray-50 transition-colors" title="Download Jadwal Matriks">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="viewMode === 'table'" class="overflow-x-auto p-5 lg:p-6 pt-0 bg-white">
                    <table class="w-full text-left border-separate border-spacing-2.5 min-w-[1100px] mt-6">
                        <thead>
                            <tr>
                                <th class="w-44 p-3 font-semibold text-xs text-gray-400 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Sesi / Jam</th>
                                <th class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Senin</th>
                                <th class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Selasa</th>
                                <th class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Rabu</th>
                                <th class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Kamis</th>
                                <th class="p-3 font-bold text-xs text-gray-700 uppercase tracking-wider bg-gray-50 rounded-xl text-center">Jumat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- ROW 1 -->
                            <tr>
                                <td class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-center align-middle">
                                    <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi 1</span>
                                    <p class="text-xs font-bold text-gray-800 mt-1.5">07.30 - 10.00 WIB</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 font-medium">2 SKS (100 Menit)</p>
                                </td>
                                <!-- Monday to Wednesday (Default) -->
                                <template v-for="day in ['SEN', 'SEL', 'RAB']" :key="day">
                                    <td>
                                        <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-gray-800">{{day}}-01</span>
                                                <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-700 mt-2">07.30 - 10.00 WIB</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">2 SKS • Reguler</p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                                <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                                <button class="hover:text-primary p-0.5" title="Edit Slot"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                                <!-- Thursday (Manual) -->
                                <td>
                                    <div class="group relative p-3.5 bg-amber-50/40 hover:bg-amber-50/70 rounded-xl border border-amber-200/80 hover:border-amber-300 hover:shadow-sm transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-amber-900">KAM-01</span>
                                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100/90 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path></svg>
                                                Diedit Manual
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-amber-950 mt-2">07.30 - 10.00 WIB</p>
                                        <p class="text-[11px] text-amber-800/80 mt-0.5">2 SKS • Jam Khusus Praktikum</p>
                                        <div class="mt-2.5 pt-2 border-t border-amber-200/50 flex items-center justify-between text-amber-700">
                                            <span class="text-[10px] font-semibold text-green-600">● Aktif</span>
                                            <button class="hover:text-amber-900 p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                        </div>
                                    </div>
                                </td>
                                <!-- Friday (Default) -->
                                <td>
                                    <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-800">JUM-01</span>
                                            <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                        </div>
                                        <p class="text-xs font-semibold text-gray-700 mt-2">07.30 - 10.00 WIB</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">2 SKS • Reguler</p>
                                        <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                            <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                            <button class="hover:text-primary p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- ROW 2 -->
                            <tr>
                                <td class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-center align-middle">
                                    <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi 2</span>
                                    <p class="text-xs font-bold text-gray-800 mt-1.5">10.20 - 12.00 WIB</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 font-medium">3 SKS (150 Menit)</p>
                                </td>
                                <!-- Monday to Thursday (Default) -->
                                <template v-for="day in ['SEN', 'SEL', 'RAB', 'KAM']" :key="day">
                                    <td>
                                        <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-gray-800">{{day}}-02</span>
                                                <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-700 mt-2">10.20 - 12.00 WIB</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">3 SKS • Reguler</p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                                <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                                <button class="hover:text-primary p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                                <!-- Friday (Manual) -->
                                <td>
                                    <div class="group relative p-3.5 bg-amber-50/40 hover:bg-amber-50/70 rounded-xl border border-amber-200/80 hover:border-amber-300 hover:shadow-sm transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-amber-900">JUM-02</span>
                                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100/90 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path></svg>
                                                Diedit Manual
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-amber-950 mt-2">10.20 - 12.00 WIB</p>
                                        <p class="text-[11px] text-amber-800/80 mt-0.5">2 SKS • Jeda Sholat Jumat</p>
                                        <div class="mt-2.5 pt-2 border-t border-amber-200/50 flex items-center justify-between text-amber-700">
                                            <span class="text-[10px] font-semibold text-green-600">● Aktif</span>
                                            <button class="hover:text-amber-900 p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- ROW 3 -->
                            <tr>
                                <td class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-center align-middle">
                                    <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi 3</span>
                                    <p class="text-xs font-bold text-gray-800 mt-1.5">13.00 - 15.30 WIB</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 font-medium">3 SKS (150 Menit)</p>
                                </td>
                                <!-- Monday to Tuesday (Default) -->
                                <template v-for="day in ['SEN', 'SEL']" :key="day">
                                    <td>
                                        <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-gray-800">{{day}}-03</span>
                                                <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-700 mt-2">13.00 - 15.30 WIB</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">3 SKS • Reguler</p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                                <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                                <button class="hover:text-primary p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                                <!-- Wednesday (Manual) -->
                                <td>
                                    <div class="group relative p-3.5 bg-amber-50/40 hover:bg-amber-50/70 rounded-xl border border-amber-200/80 hover:border-amber-300 hover:shadow-sm transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-amber-900">RAB-03</span>
                                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100/90 px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path></svg>
                                                Diedit Manual
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-amber-950 mt-2">13.00 - 15.30 WIB</p>
                                        <p class="text-[11px] text-amber-800/80 mt-0.5">3 SKS • Penyesuaian Dzuhur</p>
                                        <div class="mt-2.5 pt-2 border-t border-amber-200/50 flex items-center justify-between text-amber-700">
                                            <span class="text-[10px] font-semibold text-green-600">● Aktif</span>
                                            <button class="hover:text-amber-900 p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                        </div>
                                    </div>
                                </td>
                                <!-- Thursday & Friday (Default) -->
                                <template v-for="day in ['KAM', 'JUM']" :key="day">
                                    <td>
                                        <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-gray-800">{{day}}-03</span>
                                                <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-700 mt-2">13.00 - 15.30 WIB</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">3 SKS • Reguler</p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                                <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                                <button class="hover:text-primary p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                            </tr>

                            <!-- ROW 4 -->
                            <tr>
                                <td class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-center align-middle">
                                    <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi 4</span>
                                    <p class="text-xs font-bold text-gray-800 mt-1.5">15.50 - 17.30 WIB</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5 font-medium">2 SKS (100 Menit)</p>
                                </td>
                                <!-- All Days (Default) -->
                                <template v-for="day in ['SEN', 'SEL', 'RAB', 'KAM', 'JUM']" :key="day">
                                    <td>
                                        <div class="group relative p-3.5 bg-gray-50/50 hover:bg-white rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition-all">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-gray-800">{{day}}-04</span>
                                                <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md">Default</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-700 mt-2">15.50 - 17.30 WIB</p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">2 SKS • Reguler</p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-gray-400 group-hover:text-gray-600">
                                                <span class="text-[10px] font-medium text-green-600">● Aktif</span>
                                                <button class="hover:text-primary p-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg></button>
                                            </div>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-5 lg:p-6 pt-0 bg-white grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
                    <div v-for="i in 4" :key="i" class="border border-gray-100 rounded-xl p-5 bg-gray-50/50 flex flex-col gap-3 hover:border-primary/30 transition-colors">
                        <div class="flex items-center justify-between">
                            <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-primary bg-primary/10 rounded-lg">Sesi {{ i }}</span>
                            <span class="text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 px-2 py-0.5 rounded-md">Default 5 Hari</span>
                        </div>
                        <div class="mt-1">
                            <p class="text-sm font-bold text-gray-900">
                                {{ i === 1 ? '07.30 - 10.00 WIB' : i === 2 ? '10.20 - 12.00 WIB' : i === 3 ? '13.00 - 15.30 WIB' : '15.50 - 17.30 WIB' }}
                            </p>
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ i === 1 || i === 4 ? '2 SKS (100 Menit)' : '3 SKS (150 Menit)' }}</p>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-[10px] font-medium text-green-600">● 5 Slot Aktif</span>
                            <button class="text-primary text-[11px] font-semibold hover:underline">Edit Sesi</button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </SuperadminLayout>
</template>
