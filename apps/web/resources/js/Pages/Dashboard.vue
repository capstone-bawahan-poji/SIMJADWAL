<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, Clock, GraduationCap, Users } from 'lucide-vue-next';
import type { DashboardStats } from '@/types/models';

// Counts come from the server (DashboardController); null for roles without a dashboard yet.
defineProps<{ stats: DashboardStats | null }>();

const format = (value: number) => value.toLocaleString('id-ID');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div v-if="!stats" class="p-6 md:p-8 max-w-7xl w-full mx-auto">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Dashboard</h1>
            <p class="mt-2 text-sm text-gray-500">Halaman untuk role Anda sedang disiapkan.</p>
        </div>

        <div v-else class="p-6 md:p-8 max-w-7xl w-full mx-auto space-y-7">
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
                                <Building2 class="w-6 h-6" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ format(stats.faculties) }}</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Fakultas Terdaftar</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Seluruh fakultas terverifikasi dalam master data akademik universitas.</p>
                        </div>
                        <div class="mt-4 p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between text-xs">
                            <span class="text-gray-500">Jurusan</span>
                            <span class="font-semibold text-gray-700">{{ format(stats.departments) }} Jurusan</span>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.faculty-program.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Fakultas</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <!-- Program Studi -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <GraduationCap class="w-6 h-6" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ format(stats.study_programs) }}</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Program Studi Terdaftar</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Program studi jenjang Sarjana (S1) di seluruh fakultas.</p>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.faculty-program.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Prodi</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <!-- Kelola Akun -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <Users class="w-6 h-6" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ format(stats.users.total) }}</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Kelola Akun & Pengguna</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Kredensial pengguna aktif dengan pembagian otorisasi terstruktur.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 text-xs">
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-primary"></span><span class="text-gray-500 text-[11px]">Adm. Fak:</span></div>
                                <span class="font-bold text-gray-800">{{ format(stats.users.by_role.admin_fakultas) }}</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span><span class="text-gray-500 text-[11px]">Adm. Prodi:</span></div>
                                <span class="font-bold text-gray-800">{{ format(stats.users.by_role.admin_prodi) }}</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-gray-500 text-[11px]">Dosen:</span></div>
                                <span class="font-bold text-gray-800">{{ format(stats.users.by_role.dosen) }}</span>
                            </div>
                            <div class="flex items-center justify-between bg-gray-50 border border-gray-100 px-3 py-2 rounded-xl">
                                <div class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-gray-400"></span><span class="text-gray-500 text-[11px]">Mhs:</span></div>
                                <span class="font-bold text-gray-800">{{ format(stats.users.by_role.mahasiswa) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.accounts.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Semua Akun</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <!-- Slot / Waktu -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                                <Clock class="w-6 h-6" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="text-4xl font-bold text-gray-900 tracking-tight">{{ format(stats.time_slots.total) }}</div>
                            <div class="text-sm font-semibold text-gray-700 mt-1">Data Waktu / Slot Penjadwalan</div>
                            <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Konfigurasi slot jam perkuliahan reguler dan pembagian sesi mingguan.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 text-center">
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">{{ stats.time_slots.days }} Hari</div>
                                <div class="text-[10px] text-gray-400 font-medium">Operasional</div>
                            </div>
                            <div class="bg-gray-50 border border-gray-100 py-2 rounded-xl">
                                <div class="text-sm font-bold text-gray-800">{{ stats.time_slots.sessions_per_day }} Sesi</div>
                                <div class="text-[10px] text-gray-400 font-medium">Per Hari</div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-5 mt-5 border-t border-gray-100">
                        <Link :href="route('superadmin.slots.index')" class="inline-flex items-center justify-between w-full text-xs font-semibold text-primary hover:text-primary-hover transition-colors group">
                            <span>Kelola Data Waktu / Slot</span>
                            <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
