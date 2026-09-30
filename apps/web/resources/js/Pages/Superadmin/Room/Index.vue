<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
</script>

<template>
    <Head title="Data Ruangan" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Data Ruangan</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="ghost" class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"></path></svg>
                        Filter & Gedung
                    </Button>
                    <Button variant="ghost" class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"></path></svg>
                        Ekspor Data
                    </Button>
                </div>
            </div>

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Ruangan</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">48 <span class="text-xs font-semibold text-gray-500">Ruangan</span></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Ruang Tersedia</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">36 <span class="text-xs font-semibold text-gray-500">Ruang</span></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Sedang Dipakai</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">12 <span class="text-xs font-semibold text-gray-500">Ruang</span></h3>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Kapasitas</p>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900">2.850 <span class="text-xs font-semibold text-gray-500">Kursi</span></h3>
                </div>
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border border-gray-100 shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white rounded-t-2xl">
                    <div class="flex flex-wrap gap-3">
                        <select class="rounded-xl border-gray-200 text-xs font-semibold focus:border-primary focus:ring-primary text-gray-700 bg-gray-50 w-48">
                            <option>Semua Fakultas</option>
                            <option>Fakultas Sains Teknologi Informasi</option>
                        </select>
                        <select class="rounded-xl border-gray-200 text-xs font-semibold focus:border-primary focus:ring-primary text-gray-700 bg-gray-50 w-40">
                            <option>Semua Status</option>
                            <option>Tersedia</option>
                            <option>Sedang Dipakai</option>
                        </select>
                    </div>
                    <div class="w-full md:w-64">
                        <input
                            type="text"
                            placeholder="Cari nama ruangan..."
                            class="w-full rounded-xl border-gray-200 text-xs focus:border-primary focus:ring-primary placeholder-gray-400 bg-gray-50"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/70 text-[11px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[200px]">Kode & Nama Ruangan</th>
                                <th class="py-4 px-6">Gedung & Lantai</th>
                                <th class="py-4 px-6">Kapasitas</th>
                                <th class="py-4 px-6">Fakultas Pemilik</th>
                                <th class="py-4 px-6">Fasilitas Utama</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                            <!-- Ruang 1 -->
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">R1</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Ruang A206</p>
                                            <p class="text-[11px] text-gray-400">KODE: RUANG-201</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-800">Gedung A</p>
                                    <p class="text-[11px] text-gray-400">Lantai 2 - Sayap Barat</p>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path></svg>
                                        45 Kursi
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-medium text-gray-700">Fakultas Sains Teknologi Informasi</td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">Proyektor</span>
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">AC</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Tersedia
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-primary/10 text-gray-400 hover:text-primary transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Ruang 2 -->
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">LK</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Lab Komputer 1</p>
                                            <p class="text-[11px] text-gray-400">KODE: LAB-FTI-01</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-800">Gedung Laboratorium 1</p>
                                    <p class="text-[11px] text-gray-400">Lantai 2</p>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"></path></svg>
                                        35 PC
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-medium text-gray-700">Fakultas Sains Teknologi Informasi</td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">35 PC High-End</span>
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">LAN</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Dipakai (TI-2A)
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-primary/10 text-gray-400 hover:text-primary transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Ruang 3 -->
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-xs shrink-0">AU</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Auditorium Gedung A</p>
                                            <p class="text-[11px] text-gray-400">KODE: AUD-GD-01</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-800">Gedung A</p>
                                    <p class="text-[11px] text-gray-400">Lantai 3</p>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-gray-800 bg-gray-100 px-2.5 py-1 rounded-md">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path></svg>
                                        250 Kursi
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-medium text-gray-700">-</td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-wrap gap-1">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">Dual Proyektor</span>
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-[10px] font-medium text-gray-600">Mic</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Tersedia
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-primary/10 text-gray-400 hover:text-primary transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-100 bg-gray-50/40 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs rounded-b-2xl text-gray-500">
                    <p class="font-medium">Menampilkan <span class="font-bold text-gray-800">1</span> hingga <span class="font-bold text-gray-800">6</span> dari <span class="font-bold text-gray-800">48</span> ruangan</p>
                    <div class="flex gap-1">
                        <Button variant="ghost" disabled class="bg-white border-gray-200 text-xs px-3 py-1.5">Sebelumnya</Button>
                        <Button class="text-xs w-8 p-0 bg-primary text-white font-bold">1</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100 font-semibold text-gray-600">2</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100 font-semibold text-gray-600">3</Button>
                        <span class="px-2 text-gray-400 self-center">...</span>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100 font-semibold text-gray-600">8</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-50">Selanjutnya</Button>
                    </div>
                </div>
            </Card>
        </div>
    </SuperadminLayout>
</template>
