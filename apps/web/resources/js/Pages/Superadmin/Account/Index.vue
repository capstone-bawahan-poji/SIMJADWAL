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
    <Head title="Kelola Akun & Role" />

    <SuperadminLayout>
        <div class="p-6 md:p-8 space-y-8">
            <!-- Header section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Kelola Akun & Role</h2>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="ghost" class="border border-gray-200 bg-white hover:bg-gray-50 text-gray-700">
                        Ekspor Data
                    </Button>
                    <Button @click="isModalOpen = true" class="bg-primary hover:bg-primary-hover text-white">
                        + Tambah Akun
                    </Button>
                </div>
            </div>

            <!-- Metrics grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <MetricCard title="Total Pengguna" value="3.842" unit="Akun" />
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Admin Institusi</p>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">18 <span class="text-xs font-medium text-gray-500">Admin</span></h3>
                    <p class="text-xs font-medium text-gray-400 mt-1">3 Fakultas • 6 Program Studi</p>
                </div>
                <MetricCard title="Dosen Pengampu" value="412" unit="Dosen" />
                <MetricCard title="Mahasiswa Aktif" value="3.346" unit="Mahasiswa" />
            </div>

            <!-- Table & Filters Section -->
            <Card class="!p-0 border-none shadow-sm">
                <!-- Toolbar -->
                <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row justify-between gap-4 bg-white">
                    <div class="flex flex-wrap gap-3">
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-40 text-gray-700">
                            <option>Semua Role</option>
                            <option>Superadmin</option>
                            <option>Admin Fakultas</option>
                            <option>Koor Prodi</option>
                            <option>Dosen</option>
                            <option>Mahasiswa</option>
                        </select>
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-56 text-gray-700">
                            <option>Semua Fakultas & Unit</option>
                            <option>FSTI - Fak. Sains & Teknologi Informasi</option>
                        </select>
                        <select class="rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary w-40 text-gray-700">
                            <option>Semua Status</option>
                            <option>Aktif</option>
                            <option>Nonaktif</option>
                        </select>
                    </div>
                    <div class="w-full md:w-64">
                        <input
                            type="text"
                            placeholder="Cari dalam tabel..."
                            class="w-full rounded-xl border-gray-200 text-sm focus:border-primary focus:ring-primary placeholder-gray-400"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">
                                <th class="py-4 px-6 min-w-[200px]">Pengguna</th>
                                <th class="py-4 px-6">Email Institusi</th>
                                <th class="py-4 px-6">Role & Hak Akses</th>
                                <th class="py-4 px-6">Fakultas / Unit Terkait</th>
                                <th class="py-4 px-6 text-center">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <!-- Superadmin -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-xs shrink-0">SA</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Superadmin IT</p>
                                            <p class="text-xs text-gray-500">NIP. 198901012015011001</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">superadmin@univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 text-xs font-semibold">Superadmin</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">Biro Administrasi Sistem & TI</p>
                                    <p class="text-xs text-gray-500">Pusat Komputer & Jaringan</p>
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
                            
                            <!-- Admin Fakultas -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">HW</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Hendra Wijaya, S.Kom., M.T.</p>
                                            <p class="text-xs text-gray-500">NIP. 198207192006041003</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">h.wijaya@univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 text-xs font-semibold">Admin Fakultas</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">Dekanat FSTI</p>
                                    <p class="text-xs text-gray-500">Subbagian Akademik FSTI</p>
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
                            
                            <!-- Koor Prodi -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">RF</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Rina Fitriani, M.Kom.</p>
                                            <p class="text-xs text-gray-500">NIP. 198503142008122001</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">rina.fitriani@univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 text-xs font-semibold">Koor Prodi</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FSTI - S1 Sistem Informasi</p>
                                    <p class="text-xs text-gray-500">Koordinator Kurikulum & Jadwal</p>
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

                            <!-- Dosen -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center font-bold text-xs shrink-0">SY</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Dr. Eng. Ir. Suryadi, M.T.</p>
                                            <p class="text-xs text-gray-500">NIP. 197805122003121002</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">suryadi@univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-teal-50 text-teal-700 text-xs font-semibold">Dosen</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FSTI - S1 Teknik Informatika</p>
                                    <p class="text-xs text-gray-500">Laboratorium Kecerdasan Buatan</p>
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
                            
                            <!-- Nonaktif (Siti Rahmawati) -->
                            <tr class="hover:bg-gray-50/50 transition-colors opacity-80">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center font-bold text-xs shrink-0">SR</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Siti Rahmawati, S.E., M.M.</p>
                                            <p class="text-xs text-gray-500">NIP. 197904182002122001</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">siti.rahma@univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 text-xs font-semibold">Admin Fakultas</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FPB - Fak. Pertanian & Bisnis</p>
                                    <p class="text-xs text-gray-500">Bagian Administrasi Pengajaran</p>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>Nonaktif
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-900 transition-colors">Edit</button>
                                        <button class="p-1.5 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors">Hapus</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Mahasiswa -->
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">DA</div>
                                        <div>
                                            <p class="font-bold text-gray-900">Dimas Aditya Pratama</p>
                                            <p class="text-xs text-gray-500">NIM. 11231058</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-gray-700 font-medium">dimas.aditya@mhs.univ.ac.id</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 text-xs font-semibold">Mahasiswa</span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-semibold text-gray-900">FSTI - S1 Teknik Informatika</p>
                                    <p class="text-xs text-gray-500">Semester 5 (Kelas Reguler A)</p>
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

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm rounded-b-xl">
                    <p class="text-gray-500 font-medium">Menampilkan <span class="font-bold text-gray-900">1</span> hingga <span class="font-bold text-gray-900">7</span> dari <span class="font-bold text-gray-900">3.842</span> akun</p>
                    <div class="flex gap-1">
                        <Button variant="ghost" disabled class="bg-white border-gray-200 text-xs">Sebelumnya</Button>
                        <Button class="text-xs w-8 p-0">1</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100">2</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100">3</Button>
                        <span class="px-2 text-gray-400 self-center">...</span>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs w-8 p-0 hover:bg-gray-100">549</Button>
                        <Button variant="ghost" class="bg-white border-gray-200 text-xs hover:bg-gray-100">Selanjutnya</Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Add Modal -->
        <Modal :show="isModalOpen" @close="isModalOpen = false" maxWidth="2xl">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Tambah Akun Pengguna Baru</h2>
                        <p class="text-sm text-gray-500">Konfigurasi data akun institusi dan otorisasi role</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-gray-400 hover:text-gray-900 transition">
                    Tutup
                </button>
            </div>
            <form @submit.prevent="isModalOpen = false" class="p-6 space-y-6">
                <!-- User fixes for the form fields that had weird dummy HTML in the prompt -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Nama Lengkap</label>
                        <input type="text" placeholder="Nama lengkap beserta gelar..." class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Nomor Identitas (NIP/NIM/ID)</label>
                        <input type="text" placeholder="Contoh: 198502152010121001" class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Email Institusi</label>
                        <input type="email" placeholder="Contoh: dosen@univ.ac.id" class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Role & Otoritas Akses</label>
                        <select class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" required>
                            <option value="" disabled selected>Pilih hak akses role...</option>
                            <option>Superadmin (Akses Sistem Penuh)</option>
                            <option>Admin Fakultas (Verifikasi & Kuota)</option>
                            <option>Koor Prodi (Setup Kurikulum & Jadwal)</option>
                            <option>Dosen (Preferensi Waktu & Jadwal)</option>
                            <option>Mahasiswa (Lihat Jadwal & KRS)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Fakultas / Unit Kerja</label>
                        <select class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary">
                            <option>Semua Fakultas & Unit</option>
                            <option>FSTI - Fak. Sains & Teknologi Informasi</option>
                            <option>Biro Administrasi Sistem & TI</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Program Studi (Spesifik)</label>
                        <select class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary">
                            <option>- Berlaku untuk Seluruh Unit / Non-Prodi -</option>
                            <option selected>S1 Teknik Informatika</option>
                            <option>S1 Sistem Informasi</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Password Awal</label>
                        <input type="password" placeholder="Biarkan kosong untuk otomatis (kirim email)" class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-900 text-sm focus:border-primary focus:ring-primary" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">Status Awal</label>
                        <div class="flex items-center gap-4 pt-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 font-medium">
                                <input type="radio" name="status" value="active" checked class="text-primary focus:ring-primary w-4 h-4" />
                                Aktif Langsung
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 font-medium">
                                <input type="radio" name="status" value="pending" class="text-primary focus:ring-primary w-4 h-4" />
                                Menunggu Verifikasi
                            </label>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm text-blue-800">Setiap perubahan role pengguna akan langsung dicatat ke dalam <strong>Log Keamanan Sistem</strong>. Kredensial akun akan otomatis dikirimkan ke email institusi terkait.</p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" @click="isModalOpen = false" variant="ghost" class="bg-gray-100 hover:bg-gray-200">Batal</Button>
                    <Button type="submit">Simpan Akun</Button>
                </div>
            </form>
        </Modal>
    </SuperadminLayout>
</template>
