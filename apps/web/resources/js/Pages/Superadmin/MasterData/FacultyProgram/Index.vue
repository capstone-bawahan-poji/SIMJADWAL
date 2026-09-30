<script setup lang="ts">
import SuperadminLayout from '@/Layouts/SuperadminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/ui/Button.vue';
import MetricCard from '@/Components/ui/MetricCard.vue';
import Card from '@/Components/ui/Card.vue';
import Modal from '@/Components/Modal.vue';

const isModalOpen = ref(false);
</script>

<template>
    <Head title="Master Data Fakultas & Prodi" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Master Data Fakultas & Prodi</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="ghost" class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-700">
                        Ekspor Struktur
                    </Button>
                    <Button @click="isModalOpen = true" class="bg-primary hover:bg-primary-hover text-white">
                        + Tambah Entitas
                    </Button>
                </div>
            </div>

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <MetricCard title="Total Fakultas" value="3" unit="Fakultas" />
                <MetricCard title="Jurusan" value="6" unit="Jurusan" />
                <MetricCard title="Koor Prodi" value="26" unit="Koor Prodi" />
            </div>

            <!-- Tabs -->
            <div class="flex items-center border-b border-gray-200 pb-2">
                <div class="flex bg-gray-100 p-1 rounded-xl">
                    <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-white text-primary shadow-sm">
                        Fakultas (3)
                    </button>
                    <button class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-500 hover:text-gray-700">
                        Program Studi (6)
                    </button>
                </div>
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border-none shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row justify-between gap-4 bg-white">
                    <div class="flex flex-wrap gap-3">
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-48 text-gray-700">
                            <option>Semua Fakultas</option>
                            <option>Fakultas Rekayasa Teknologi Informasi (FRTI)</option>
                        </select>
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-40 text-gray-700">
                            <option>Semua Jenjang</option>
                            <option>Strata 1 (S1)</option>
                        </select>
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-40 text-gray-700">
                            <option>Status: Semua Aktif</option>
                            <option>Aktif</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            placeholder="Cari nama prodi, kode..."
                            class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-full lg:w-64 placeholder-gray-400"
                        />
                        <Button @click="isModalOpen = true" variant="ghost" class="bg-gray-100 hover:bg-gray-200 text-primary font-semibold">
                            + Prodi Baru
                        </Button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[250px]">Program Studi & Kode</th>
                                <th class="py-4 px-6">Fakultas Induk</th>
                                <th class="py-4 px-6">Jenjang & Akreditasi</th>
                                <th class="py-4 px-6">Koor Prodi & Kontak</th>
                                <th class="py-4 px-6">Mahasiswa & Kelas</th>
                                <th class="py-4 px-6 text-center">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">TIF</div>
                                        <div>
                                            <p class="font-bold text-gray-900">S1 Teknik Informatika</p>
                                            <p class="text-xs text-gray-500">Kode: TIF-55201 • Dep. RPL</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FRTI</p>
                                    <p class="text-xs text-gray-500">Gedung Dekanat FRTI Lt. 2</p>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex gap-2">
                                        <span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-xs font-bold">S1</span>
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-semibold">Unggul (BAN-PT)</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">Dr. Irma Suryani, M.T.</p>
                                    <p class="text-xs text-gray-500">NIP. 197805122003121002</p>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-bold text-gray-900">520 Mhs</p>
                                    <p class="text-xs text-gray-500">14 Kelas Paralel GA</p>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>Aktif
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">SIF</div>
                                        <div>
                                            <p class="font-bold text-gray-900">S1 Sistem Informasi</p>
                                            <p class="text-xs text-gray-500">Kode: SIF-57201 • Dep. Sistem Informasi</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FRTI</p>
                                    <p class="text-xs text-gray-500">Gedung FRTI Lt. 3</p>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex gap-2">
                                        <span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-xs font-bold">S1</span>
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-semibold">Unggul (LAM-INFOKOM)</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">Rina Fitriani, M.Kom.</p>
                                    <p class="text-xs text-gray-500">NIP. 198503142008122001</p>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-bold text-gray-900">430 Mhs</p>
                                    <p class="text-xs text-gray-500">11 Kelas Paralel GA</p>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>Aktif
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm rounded-b-xl">
                    <p class="text-gray-500 font-medium">Menampilkan <span class="font-bold text-gray-900">1</span> hingga <span class="font-bold text-gray-900">6</span> dari <span class="font-bold text-gray-900">6</span> program studi</p>
                    <div class="flex gap-1">
                        <Button variant="ghost" disabled class="bg-white border-gray-200 text-xs">Sebelumnya</Button>
                        <Button class="text-xs w-8 p-0">1</Button>
                        <Button variant="ghost" disabled class="bg-white border-gray-200 text-xs">Selanjutnya</Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Add Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Tambah Entitas Fakultas / Prodi</h2>
                        <p class="text-sm text-gray-500">Konfigurasi master data fakultas atau program studi baru</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">
                    Tutup
                </button>
            </div>
            <form @submit.prevent="isModalOpen = false" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Tipe Entitas</label>
                        <select class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary">
                            <option>Program Studi (Prodi)</option>
                            <option>Fakultas / Dekanat</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas Induk</label>
                        <select class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary">
                            <option>Fakultas Rekayasa Teknologi Informasi (FRTI)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Nama Entitas</label>
                        <input type="text" placeholder="Contoh: S1 Rekayasa Perangkat Lunak" class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Kode Singkat</label>
                        <input type="text" placeholder="Contoh: RPL-55202" class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required />
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit">Simpan Entitas</Button>
                </div>
            </form>
        </Modal>
    </SuperadminLayout>
</template>
