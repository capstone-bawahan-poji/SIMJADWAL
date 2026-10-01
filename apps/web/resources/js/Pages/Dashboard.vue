<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard Superadmin" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-7">
            <!-- Title & Action Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard Superadmin</h1>
                </div>
                

            </div>

            <!-- Dashboard Summary Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                <!-- Data Fakultas -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">3</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Fakultas Terdaftar</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Seluruh fakultas terverifikasi dalam master data akademik universitas.</p>
                        </div>
                        <div class="mt-4 p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between text-xs">
                            <span class="text-gray-500">Cakupan Dekanat</span>
                            <span class="font-semibold text-gray-700">8 Unit Resmi</span>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.faculty-program.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Fakultas</span>
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </Link>
                    </div>
                </div>

                <!-- Program Studi -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">32</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Program Studi Terdaftar</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Terdistribusi pada jenjang Sarjana (S1) dan Magister (S2).</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 text-center">
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">26</div>
                                <div class="text-[10px] text-gray-400 font-medium">S1</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">6</div>
                                <div class="text-[10px] text-gray-400 font-medium">S2</div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.faculty-program.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Prodi</span>
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </Link>
                    </div>
                </div>

                <!-- Kelola Akun -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">3,842</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Kelola Akun & Pengguna</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Kredensial pengguna aktif dengan pembagian otorisasi terstruktur.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 text-xs">
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-primary"></span><span class="text-gray-500 text-[11px]">Adm. Fak:</span></div>
                                <span class="font-bold text-gray-800">16</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span><span class="text-gray-500 text-[11px]">Adm. Prodi:</span></div>
                                <span class="font-bold text-gray-800">68</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-gray-500 text-[11px]">Dosen:</span></div>
                                <span class="font-bold text-gray-800">412</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-gray-400"></span><span class="text-gray-500 text-[11px]">Mhs:</span></div>
                                <span class="font-bold text-gray-800">3,346</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.accounts.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Semua Akun</span>
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </Link>
                    </div>
                </div>

                <!-- Slot / Waktu -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">20</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Data Waktu / Slot Penjadwalan</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Konfigurasi slot jam perkuliahan reguler dan pembagian sesi mingguan.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">5 Hari</div>
                                <div class="text-[10px] text-gray-400 font-medium">Operasional</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">4 Sesi</div>
                                <div class="text-[10px] text-gray-400 font-medium">Per Hari</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">3 Slot</div>
                                <div class="text-[10px] text-gray-400 font-medium">Manual Lock</div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <a href="#" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Waktu / Slot</span>
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </SuperadminLayout>
</template>
