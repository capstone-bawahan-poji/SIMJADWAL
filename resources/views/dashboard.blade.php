<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Fakultas FSTI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[#f8fafc] font-sans antialiased text-[#0f172a]">

    <div class="flex min-h-screen">

        <!-- ================= SIDEBAR KIRI ================= -->
        <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between shrink-0">
            <div>
                <!-- Header Sidebar / Logo -->
                <div class="p-6 flex items-center justify-between border-b border-gray-50">
                    <div>
                        <h2 class="text-sm font-bold text-gray-800 tracking-tight leading-tight">Sistem Penjadwalan</h2>
                        <h2 class="text-sm font-bold text-gray-800 tracking-tight leading-tight mb-1">Mata Kuliah</h2>
                        <span class="text-[10px] bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">Admin
                            Fakultas FSTI</span>
                    </div>
                </div>

                <!-- Menu Navigasi Sidebar -->
                <nav class="p-4 space-y-1">
                    <!-- Menu 1: Dashboard -->
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center gap-3 px-4 py-2.5 bg-[#f0f4ff] text-[#1e40af] text-xs font-bold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4z" />
                        </svg>
                        Dashboard
                    </a>

                    <!-- Menu 2: Kelola Constraint -->
                    <a href="{{ route('kelola.constraint') }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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

        <!-- ================= WRAPPER KONTEN UTAMA & TOPBAR ================= -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- Topbar / Navbar Atas -->
            <header class="h-16 bg-white border-b border-gray-100 flex items-center justify-between px-8 shrink-0">
                <!-- Kolom Search -->
                <div class="relative w-80">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" placeholder="Cari prodi, ruangan, jadwal, atau dosen..."
                        class="w-full bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-1.5 text-xs placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                </div>

                <!-- Profil & Notifikasi (Kanan) -->
                <div class="flex items-center gap-4">
                    <!-- ICON LONCENG / NOTIFIKASI -->
                    <button class="relative text-gray-400 hover:text-gray-600 transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span class="absolute top-1 right-1.5 w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                    </button>

                    <!-- GARIS PEMISAH VERTIKAL -->
                    <div class="h-5 w-[1px] bg-gray-200"></div>

                    <!-- TULISAN ADMIN -->
                    <div class="text-right">
                        <h4 class="text-xs font-bold text-gray-800 leading-tight">Admin Fakultas FSTI</h4>
                        <p class="text-[10px] text-gray-400 font-medium">Biro Akademik</p>
                    </div>

                    <!-- AVATAR AF -->
                    <div
                        class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm">
                        AF
                    </div>
                </div>
            </header>

            <!-- ================= ISI CONTENT WORKSPACE ================= -->
            <main class="flex-1 p-8 overflow-y-auto">

                <!-- Main Header Judul Page -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h1 class="text-xl font-bold text-gray-800 tracking-tight">Dashboard Admin Fakultas</h1>
                            <span class="text-[10px] bg-blue-50 text-blue-600 font-bold px-2 py-0.5 rounded-md">Semester
                                Ganjil 2026/2027</span>
                        </div>
                        <p class="text-xs text-gray-400 font-medium">Pusat operasional penjadwalan terpadu Genetic
                            Algorithm dan koordinasi ruangan bersama prodi.</p>
                    </div>
                </div>

                <!-- GRID KARTU METRIK UTAMA (4 CARD - 2 KOLOM) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Card 1: Kelengkapan Constraint Prodi -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 flex flex-col justify-between min-h-[220px]">
                        <div>
                            <div
                                class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center mb-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.26 10.174L10.74 14.15a1.2 1.2 0 001.28 0l6.48-3.976m-14 0A48.536 48.536 0 0112 3c4.639 0 8.784 2.373 11.26 6.174m-14 0v5.826a3 3 0 001.528 2.617l4.474 2.556a3 3 0 002.996 0l4.474-2.556a3 3 0 001.528-2.617V10.174m-14 0a48.536 48.536 0 00-2.52 6.174m0 0A48.57 48.57 0 0012 21c4.639 0 8.784-2.373 11.26-6.174m-11.26-3.826L4.26 10.174" />
                                </svg>
                            </div>
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-3xl font-extrabold text-gray-800">5</span>
                                <span class="text-xl font-bold text-gray-400">/ 6</span>
                                <span class="text-xs font-semibold text-gray-400 ml-1">Prodi</span>
                            </div>
                            <h3 class="text-xs font-bold text-gray-700 mb-4">Kelengkapan Constraint Prodi</h3>
                            <div class="flex items-center gap-2 text-[11px] text-gray-500 font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                5 Program Studi Terverifikasi
                            </div>
                        </div>
                        <a href="#"
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-6">
                            Kelola Constraint Prodi &rarr;
                        </a>
                    </div>

                    <!-- Card 2: Status Run Penjadwalan GA -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 flex flex-col justify-between min-h-[220px]">
                        <div>
                            <div
                                class="w-8 h-8 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="text-3xl font-extrabold text-gray-800">0.98</span>
                                <span
                                    class="text-[10px] text-gray-400 font-bold bg-gray-50 border border-gray-100 px-2 py-0.5 rounded">Fitness
                                    Score</span>
                            </div>
                            <h3 class="text-xs font-bold text-gray-700 mb-4">Status Run Penjadwalan GA</h3>

                            <div
                                class="grid grid-cols-3 border border-gray-100 rounded-xl p-3 bg-gray-50/50 text-center">
                                <div>
                                    <p class="text-sm font-bold text-gray-800">166</p>
                                    <p class="text-[9px] text-gray-400 font-semibold">Total Kelas</p>
                                </div>
                                <div class="border-x border-gray-100">
                                    <p class="text-sm font-bold text-gray-800">42</p>
                                    <p class="text-[9px] text-gray-400 font-semibold">Generasi</p>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-emerald-600">0</p>
                                    <p class="text-[9px] text-gray-400 font-semibold">Bentrok Hard</p>
                                </div>
                            </div>
                        </div>
                        <a href="#"
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-4">
                            Lihat Hasil Matriks &rarr;
                        </a>
                    </div>

                    <!-- Card 3: Ruangan & Kapasitas -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 flex flex-col justify-between min-h-[240px]">
                        <div>
                            <div
                                class="w-8 h-8 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mb-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-3xl font-extrabold text-gray-800">4</span>
                                <span
                                    class="text-[10px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md ml-1">Ruangan
                                    Bermasalah</span>
                            </div>
                            <h3 class="text-xs font-bold text-gray-700 mb-4">Ruangan & Kapasitas</h3>

                            <div class="space-y-2.5 text-[11px] font-medium">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full bg-amber-400"></span><span
                                            class="text-gray-600">IF-204 (Pemrograman Lanjut)</span></div>
                                    <span class="text-gray-400 text-[10px]">Over 15 Mhs</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full bg-blue-400"></span><span
                                            class="text-gray-600">SI-301 (Analisis Proses)</span></div>
                                    <span class="text-gray-400 text-[10px]">Slot Penuh</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full bg-rose-400"></span><span
                                            class="text-gray-600">SD-102 (Aljabar Linier)</span></div>
                                    <span class="text-gray-400 text-[10px]">Tanpa Ruang</span>
                                </div>
                            </div>
                        </div>
                        <a href="#"
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-6">
                            Kelola Alokasi Ruangan &rarr;
                        </a>
                    </div>

                    <!-- Card 4: Bentrok Antar Prodi -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] p-6 flex flex-col justify-between min-h-[240px]">
                        <div>
                            <div
                                class="w-8 h-8 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center mb-4">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-3xl font-extrabold text-gray-800">2</span>
                                <span
                                    class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md ml-1">Jadwal
                                    Bentrok</span>
                            </div>
                            <h3 class="text-xs font-bold text-gray-700 mb-4">Bentrok Antar Prodi</h3>

                            <div class="space-y-3 text-[10px]">
                                <div class="flex justify-between items-start border-b border-gray-50 pb-2">
                                    <div>
                                        <p class="font-bold text-gray-700">Lab Komputasi 1</p>
                                        <p class="text-gray-400 font-medium">TI-A (Pemrograman Web) vs SI-B (Basis
                                            Data)</p>
                                    </div>
                                    <span class="text-gray-500 font-semibold shrink-0">Kamis, 10:00</span>
                                </div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-700">Ruangan A206</p>
                                        <p class="text-gray-400 font-medium">SD-A (Machine Learning) vs RPL-C (Rekayasa
                                            Lunak)</p>
                                    </div>
                                    <span class="text-gray-500 font-semibold shrink-0">Selasa, 13:00</span>
                                </div>
                            </div>
                        </div>
                        <a href="#"
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 mt-4">
                            Selesaikan Resolusi Bentrok &rarr;
                        </a>
                    </div>
