<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Constraint per Prodi - FSTI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[#f8fafc] font-sans antialiased text-[#0f172a]">

    <div class="flex min-h-screen">

        <!-- ================= SIDEBAR KIRI ================= -->
        <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between shrink-0">
            <div>
                <div class="p-6 border-b border-gray-50">
                    <h2 class="text-sm font-bold text-gray-800 tracking-tight leading-tight">Sistem Penjadwalan</h2>
                    <h2 class="text-sm font-bold text-gray-800 tracking-tight leading-tight mb-1">Mata Kuliah</h2>
                    <span class="text-[10px] bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">Admin Fakultas
                        FSTI</span>
                </div>
                <nav class="p-4 space-y-1">

                    <!-- Menu 1: Dashboard -->
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path
                                d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4z" />
                        </svg>
                        Dashboard
                    </a>

                    <!-- Menu 2: Kelola Constraint -->
                    <a href="{{ route('kelola.constraint') }}"
                        class="flex items-center gap-3 px-4 py-2.5 bg-[#f0f4ff] text-[#1e40af] text-xs font-bold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Kelola Constraint
                    </a>

                    <!-- Menu 3: Penjadwalan GA -->
                    <a href="{{ route('penjadwalan.ga') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Penjadwalan GA
                    </a>

                    <!-- Menu 4: Alokasi Ruangan -->
                    <a href="{{ route('alokasi.ruangan') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Alokasi Ruangan
                    </a>

                    <!-- Menu 5: Resolusi Bentrok / Relokasi -->
                    <a href="#"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Resolusi Bentrok
                    </a>

                    <!-- Menu 6: Pengaturan Akun -->
                    <a href="#"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Pengaturan Akun
                    </a>
                </nav>
            </div>
        </aside>

        <!-- ================= KONTEN UTAMA ================= -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- Topbar / Navbar Atas -->
            <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-8 shrink-0">
                <div class="relative w-80">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" placeholder="Cari prodi, ruangan, jadwal..."
                        class="w-full bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-1.5 text-xs placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                </div>
                <div class="flex items-center gap-4">
                    <button class="relative text-gray-400 hover:text-gray-600 transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span class="absolute top-1 right-1.5 w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                    </button>
                    <div class="h-5 w-[1px] bg-gray-200"></div>
                    <div class="text-right">
                        <h4 class="text-xs font-bold text-gray-800 leading-tight">Admin Fakultas FSTI</h4>
                        <p class="text-[10px] text-gray-400 font-medium">Biro Akademik</p>
                    </div>
                    <div
                        class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm">
                        AF</div>
                </div>
            </header>

            <!-- Workspace Konten -->
            <main class="flex-1 p-8 overflow-y-auto space-y-6">

                <!-- Breadcrumbs & Title Block -->
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] font-semibold text-gray-400 flex items-center gap-1.5 mb-1">
                            <span>Dashboard</span> &middot; <span>Kelola Constraint</span> &middot; <span
                                class="text-blue-600">Semester Ganjil 2024/2025</span>
                        </div>
                        <h1 class="text-xl font-extrabold text-gray-800 tracking-tight">Lihat Preview Constraint per
                            Prodi</h1>
                    </div>
                    <button
                        class="px-4 py-2 bg-[#1e40af] hover:bg-[#1a3a9c] text-white text-xs font-bold rounded-xl shadow-md transition-colors flex items-center gap-1.5">
                        Lanjut ke Konfigurasi GA
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>

                <!-- Sub Navigation Tabs -->
                <div class="flex border-b border-gray-200 text-xs font-bold">
                    <button class="px-4 py-2.5 text-gray-400 border-b-2 border-transparent hover:text-gray-600">Cek
                        Kelengkapan Constraint Semua Prodi</button>
                    <button class="px-4 py-2.5 text-[#1e40af] border-b-2 border-[#1e40af]">Preview Constraint per
                        Prodi</button>
                </div>

                <!-- Program Studi Selector Row -->
                <div
                    class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-wrap items-center justify-between gap-4 shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                    <div class="flex flex-wrap gap-2 items-center text-[10px] font-bold">
                        <span class="text-black uppercase tracking-wide mr-2 font-bold">
                            PROGRAM STUDI
                        </span>
                        <span class="text-gray-400 text-[9px] font-semibold">
                            • Pilih untuk melihat rincian constraint
                        </span>
                        <button
                            class="px-3 py-1.5 bg-[#f0f4ff] text-[#1e40af] rounded-lg border border-blue-100 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span>S1 Informatika</button>
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-100 text-gray-500 rounded-lg hover:bg-gray-50 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>S1 Sistem Informasi</button>
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-100 text-gray-500 rounded-lg hover:bg-gray-50 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>S1 Teknik Elektro</button>
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-100 text-gray-500 rounded-lg hover:bg-gray-50 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-amber-400 rounded-full"></span>S1 Sains Data</button>
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-100 text-gray-500 rounded-lg hover:bg-gray-50 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>S1 Matematika</button>
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-100 text-gray-500 rounded-lg hover:bg-gray-50 flex items-center gap-1.5"><span
                                class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>S1 Fisika</button>
                    </div>
                    <span
                        class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        Tervalidasi & Siap
                    </span>
                </div>

                <!-- Info Alert Text Logs -->
                <div class="flex flex-col sm:flex-row justify-between text-[9px] font-bold text-gray-400 gap-1 px-1">
                    <span class="flex items-center gap-1 text-slate-500">
                        <svg class="w-3 h-3 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        S1 Informatika: Disubmit oleh Dr. Hendra Wijaya, M.T. (18 Okt 2024, 14:20 WIB)
                    </span>
                    <span class="text-amber-600 flex items-center gap-1">
                        <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        S1 Sains Data masih memiliki preferensi dosen yang belum terisi lengkap
                    </span>
                </div>

                <!-- DUA KOLOM SIDE-BY-SIDE (SECTION 1 & SECTION 2) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Section 1: Hard Constraint -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 space-y-3">
                        <div class="flex justify-between items-center mb-2 pb-3 border-b border-gray-50">
                            <div>
                                <h3 class="text-xs font-extrabold text-gray-800">Section 1: Hard Constraint (Statis &
                                    Wajib)</h3>
                                <p class="text-[9px] text-gray-400 font-medium mt-0.5">Aturan baku tanpa pengecualian
                                    (Zero Tolerance).</p>
                            </div>
                            <span
                                class="text-[9px] bg-slate-50 border border-slate-100 font-bold px-2 py-1 rounded-md text-slate-500">5
                                Rules Aktif</span>
                        </div>

                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-blue-50 border border-blue-100 text-[#1e40af] font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">HC-1</span>
                                <span>Bebas bentrok jadwal mengajar dosen</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-normal">Maks. 1 kelas/waktu</span>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-blue-50 border border-blue-100 text-[#1e40af] font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">HC-2</span>
                                <span>Bebas bentrok pemakaian ruangan</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-normal">Maks. 1 kelas/ruang</span>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-blue-50 border border-blue-100 text-[#1e40af] font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">HC-3</span>
                                <span>Kapasitas ruangan memadai</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-normal">Kapasitas &ge; Peserta</span>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-blue-50 border border-blue-100 text-[#1e40af] font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">HC-4</span>
                                <span>Kesesuaian jenis lab & praktikum</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-normal">Spesifikasi Lab</span>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-blue-50 border border-blue-100 text-[#1e40af] font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">HC-5</span>
                                <span>Batas beban SKS harian dosen</span>
                            </div>
                            <span class="text-[9px] text-gray-400 font-normal">Maks. 12 SKS/hari</span>
                        </div>
                    </div>

                    <!-- Section 2: Bobot Penalti -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 space-y-3">
                        <div class="flex justify-between items-center mb-2 pb-3 border-b border-gray-50">
                            <div>
                                <h3 class="text-xs font-extrabold text-gray-800">Section 2: Bobot Penalti (Soft
                                    Constraint) S1 Informatika</h3>
                                <p class="text-[9px] text-gray-400 font-medium mt-0.5">Parameter fitness function
                                    algoritma GA yang diset prodi.</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[8px] text-gray-400 font-bold uppercase leading-none">Total Bobot:</p>
                                <p class="text-xs font-extrabold text-[#1e40af] mt-0.5">100</p>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-purple-50 border border-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">SC-1</span>
                                <div>
                                    <p class="font-bold text-gray-800">Preferensi Waktu Dosen</p>
                                    <p class="text-[9px] text-gray-400 font-normal">Penempatan di slot waktu prioritas
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">40</p>
                                <p class="text-[8px] text-red-500 font-medium">Tinggi</p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-purple-50 border border-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">SC-2</span>
                                <div>
                                    <p class="font-bold text-gray-800">Distribusi Hari Kuliah Merata</p>
                                    <p class="text-[9px] text-gray-400 font-normal">Beban kelas semester Senin - Jumat
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">25</p>
                                <p class="text-[8px] text-amber-500 font-medium">Sedang</p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-purple-50 border border-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">SC-3</span>
                                <div>
                                    <p class="font-bold text-gray-800">Gedung Fakultas Utama</p>
                                    <p class="text-[9px] text-gray-400 font-normal">Minimasi mobilisasi antar gedung
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">20</p>
                                <p class="text-[8px] text-amber-500 font-medium">Sedang</p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between p-3 border border-gray-50 rounded-xl bg-gray-50/20 text-[10px] font-semibold text-gray-700">
                            <div class="flex items-center gap-3">
                                <span
                                    class="bg-purple-50 border border-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded text-[9px] shrink-0">SC-4</span>
                                <div>
                                    <p class="font-bold text-gray-800">Sesi Berurutan Rombel</p>
                                    <p class="text-[9px] text-gray-400 font-normal">Jeda antar kelas maksimal 1 slot
                                        kosong</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">15</p>
                                <p class="text-[8px] text-emerald-500 font-medium">Rendah</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: DATA CARD FULL WIDTH -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xs font-extrabold text-gray-800">Section 3: Ringkasan Soft Constraint
                                    per Dosen (S1 Informatika)</h3>
                                <p class="text-[9px] text-gray-400 font-medium mt-0.5">Rincian preferensi slot waktu
                                    dan beban mengajar masing-masing dosen pengampu (24 Dosen).</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold">
                            <span class="text-gray-400 uppercase font-semibold text-[9px] mr-1">Filter Cepat:</span>
                            <button class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md">Semua (24)</button>
                            <button
                                class="px-2.5 py-1 bg-white border border-gray-100 text-gray-400 hover:text-gray-600 rounded-md">Ada
                                Preferensi Khusus (18)</button>
                        </div>
                    </div>

                    <!-- Profil Dosen 1 -->
                    <div class="border border-gray-100 rounded-2xl p-5 mb-4 bg-gray-50/10">
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start gap-3 border-b border-gray-50 pb-3 mb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-gray-800">Dr. Hendra Wijaya, M.T.</h4>
                                    <span class="text-[9px] font-medium text-gray-400">197804152003121002</span>
                                    <span
                                        class="text-[9px] bg-blue-50 text-blue-600 font-bold px-1.5 py-0.5 rounded">Koordinator
                                        Prodi</span>
                                </div>
                                <p class="text-[10px] text-gray-400 font-medium">Maks. 3 SKS berturut-turut &bull;
                                    Prioritas sesi pagi</p>
                            </div>
                            <div class="flex gap-4 text-[10px] font-bold shrink-0 self-end sm:self-auto">
                                <span class="text-blue-700 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span> 6 Slot Disukai</span>
                                <span class="text-gray-400 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span> 2 Slot Dihindari</span>
                                <svg class="w-4 h-4 text-emerald-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-5 gap-2 text-center text-[10px] font-bold">
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Senin</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi &
                                    Siang</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Selasa</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi &
                                    Siang</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Rabu</p>
                                <p class="text-gray-400 font-medium text-[9px] bg-gray-50 py-0.5 rounded">Rapat Prodi
                                </p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Kamis</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi &
                                    Siang</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Jumat</p>
                                <p class="text-red-500 font-extrabold text-[9px] bg-red-50/50 py-0.5 rounded">Pagi Sesi
                                    I</p>
                            </div>
                        </div>
                    </div>

                    <!-- Profil Dosen 2 -->
                    <div class="border border-gray-100 rounded-2xl p-5 mb-4 bg-gray-50/10">
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start gap-3 border-b border-gray-50 pb-3 mb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-gray-800">Ratna Sari Dewi, Ph.D.</h4>
                                    <span class="text-[9px] font-medium text-gray-400">198211092008012001</span>
                                </div>
                                <p class="text-[10px] text-gray-400 font-medium">Fokus riset lab hari Selasa & Kamis
                                    (dihindari mengajar kelas teori)</p>
                            </div>
                            <div class="flex gap-4 text-[10px] font-bold shrink-0 self-end sm:self-auto">
                                <span class="text-blue-700 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span> 6 Slot Disukai</span>
                                <span class="text-gray-400 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span> 0 Slot Dihindari</span>
                                <svg class="w-4 h-4 text-emerald-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-5 gap-2 text-center text-[10px] font-bold">
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Senin</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi &
                                    Siang</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Selasa</p>
                                <p class="text-amber-600 font-extrabold text-[9px] bg-amber-50/50 py-0.5 rounded">Riset
                                    Lab</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Rabu</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Siang &
                                    Sore</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Kamis</p>
                                <p class="text-amber-600 font-extrabold text-[9px] bg-amber-50/50 py-0.5 rounded">Riset
                                    Lab</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Jumat</p>
                                <p class="text-red-500 font-extrabold text-[9px] bg-red-50/50 py-0.5 rounded">Pagi Sesi
                                    I</p>
                            </div>
                        </div>
                    </div>

                    <!-- Profil Dosen 3 -->
                    <div class="border border-gray-100 rounded-2xl p-5 mb-4 bg-gray-50/10">
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start gap-3 border-b border-gray-50 pb-3 mb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-gray-800">Bambang Wicaksono, S.T., M.T.</h4>
                                    <span class="text-[9px] font-medium text-gray-400">197509182002121001</span>
                                </div>
                                <p class="text-[10px] text-gray-400 font-medium">Praktikum lab komputer diutamakan sesi
                                    siang (Sesi 2 & 3)</p>
                            </div>
                            <div class="flex gap-4 text-[10px] font-bold shrink-0 self-end sm:self-auto">
                                <span class="text-blue-700 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span> 10 Slot Disukai</span>
                                <span class="text-gray-400 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span> 4 Slot Dihindari</span>
                                <svg class="w-4 h-4 text-emerald-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-5 gap-2 text-center text-[10px] font-bold">
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Senin</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Siang &
                                    Sore</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Selasa</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Siang &
                                    Sore</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Rabu</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Siang &
                                    Sore</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Kamis</p>
                                <p class="text-gray-400 font-medium text-[9px] bg-gray-50 py-0.5 rounded">Off Mengajar
                                </p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Jumat</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Siang
                                    Only</p>
                            </div>
                        </div>
                    </div>

                    <!-- Profil Dosen 4 -->
                    <div class="border border-gray-100 rounded-2xl p-5 bg-gray-50/10">
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start gap-3 border-b border-gray-50 pb-3 mb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-gray-800">Prof. Dr. Ir. Gunawan, M.Eng.</h4>
                                    <span class="text-[9px] font-medium text-gray-400">196903211994031003</span>
                                    <span
                                        class="text-[9px] bg-purple-50 text-purple-600 font-bold px-1.5 py-0.5 rounded">Guru
                                        Besar</span>
                                </div>
                                <p class="text-[10px] text-gray-400 font-medium">Kuliah pagi hari maksimal 2 hari
                                    perkuliahan per pekan</p>
                            </div>
                            <div class="flex gap-4 text-[10px] font-bold shrink-0 self-end sm:self-auto">
                                <span class="text-blue-700 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-blue-600 rounded-full"></span> 4 Slot Disukai</span>
                                <span class="text-gray-400 flex items-center gap-1"><span
                                        class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span> 2 Slot Dihindari</span>
                                <svg class="w-4 h-4 text-emerald-500 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-5 gap-2 text-center text-[10px] font-bold">
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Senin</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi
                                    Only</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Selasa</p>
                                <p class="text-gray-400 font-medium text-[9px] bg-gray-50 py-0.5 rounded">Pascasarjana
                                </p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Rabu</p>
                                <p class="text-blue-600 font-extrabold text-[9px] bg-blue-50/50 py-0.5 rounded">Pagi
                                    Only</p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Kamis</p>
                                <p class="text-gray-400 font-medium text-[9px] bg-gray-50 py-0.5 rounded">Pascasarjana
                                </p>
                            </div>
                            <div class="bg-white border border-gray-100 rounded-xl p-3">
                                <p class="text-gray-400 mb-1">Jumat</p>
                                <p class="text-gray-400 font-medium text-[9px] bg-gray-50 py-0.5 rounded">Bimbingan</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM FIXED ACTION ACTION BAR -->
                <div
                    class="bg-white border border-gray-100 p-4 rounded-2xl shadow-lg flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5 text-[10px] font-medium text-gray-500">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M2.166 4.999A11.954 11.954 0 0010 1.944a11.954 11.954 0 007.834 3.056 10.03 10.03 0 01-1.353 5.485c-.947 1.637-2.4 2.965-4.202 3.842L10 15.383l-2.28-.11c-1.8-.878-3.254-2.207-4.202-3.842a10.03 10.03 0 01-1.353-5.485zM10 5a1 1 0 00-1 1v3a1 1 0 001.447.894l2-1a1 1 0 10-.894-1.789L10 7.618V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Data diambil langsung dari submission Koor Prodi yang telah tervalidasi dan siap
                            ditransfer ke Desktop Engine GA.</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto justify-end">
                        <button
                            class="px-4 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-xl transition-all shadow-sm">Unduh
                            Rekap Constraint (PDF)</button>
                        <button
                            class="px-4 py-2 bg-[#1e40af] hover:bg-[#1a3a9c] text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            Lanjut ke Konfigurasi GA
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </div>
                </div>

            </main>
        </div>
    </div>

</body>

</html>
