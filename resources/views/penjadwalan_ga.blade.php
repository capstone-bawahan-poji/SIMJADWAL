<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjadwalan GA - Sistem Penjadwalan</title>
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
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Kelola Constraint
                    </a>

                    <!-- Menu 3: Penjadwalan GA -->
                    <a href="{{ route('penjadwalan.ga') }}"
                       class="flex items-center gap-3 px-4 py-2.5 bg-[#f0f4ff] text-[#1e40af] text-xs font-bold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
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
                    <input type="text" placeholder="Cari nomor run, parameter, atau tanggal..."
                        class="w-full bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-1.5 text-xs placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                </div>
                <div class="flex items-center gap-4">
                    <button class="relative text-gray-400 hover:text-gray-600 p-1">
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

                <!-- Breadcrumbs & Header Title -->
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] font-semibold text-gray-400 flex items-center gap-1.5 mb-1">
                            <span>Dashboard</span> &middot; <span>Penjadwalan GA</span> &middot; <span
                                class="text-slate-600">Sinkronisasi Desktop App</span> &middot; <span
                                class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-500 font-bold">Semester Ganjil
                                2024/2025</span>
                        </div>
                        <h1 class="text-xl font-extrabold text-gray-800 tracking-tight">Review & Tetapkan Hasil Jadwal
                            GA</h1>
                    </div>
                    <div class="flex gap-2">
                        <button
                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 font-bold text-xs rounded-xl shadow-sm hover:bg-gray-550 transition-colors flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Tarik Data Terbaru dari Desktop
                        </button>
                        <button
                            class="px-4 py-1.5 bg-[#1e40af] hover:bg-blue-750 text-white font-bold text-xs rounded-xl shadow-md transition-colors">
                            Lanjut ke Tetapkan Jadwal Aktif &rarr;
                        </button>
                    </div>
                </div>

                <!-- ROW STATISTIC CARDS (3 KOLOM) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Card 1 -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">TOTAL RUN TERSINKRON
                            </p>
                            <h2 class="text-2xl font-black text-gray-800 mt-1">4 Hasil Run</h2>
                            <p class="text-[9px] text-blue-600 font-medium mt-1">&bull; 1 Solusi Terbaik Direkomendasi
                            </p>
                        </div>
                    </div>
                    <!-- Card 2 -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">FITNESS TERTINGGI
                            </p>
                            <h2 class="text-2xl font-black text-[#1e40af] mt-1">0.988 <span
                                    class="text-sm font-bold text-gray-300">/ 1.000</span></h2>
                            <p class="text-[9px] text-gray-400 font-medium mt-1"> &bull; 0 Bentrok Hard Constraint
                                &bull; (100% Layak)</p>
                        </div>
                    </div>
                    <!-- Card 3 -->
                    <div
                        class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">STATUS JADWAL AKTIF
                            </p>
                            <h2 class="text-2xl font-black text-amber-600 mt-1">Belum Ditetapkan</h2>
                            <p class="text-[9px] text-gray-400 font-medium mt-1">Pilih 1 dari run di bawah untuk
                                dipublikasi</p>
                        </div>
                    </div>
                </div>

                <!-- ================= TABEL DATA: RIWAYAT RUN GA ================= -->
                <div
                    class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] overflow-hidden">
                    <!-- Filter Tab Kecil di Atas Tabel -->
                    <div class="px-6 py-4 border-b border-gray-50 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex gap-1 text-[10px] font-bold">
                            <button class="px-3 py-1.5 bg-[#f0f4ff] text-[#1e40af] rounded-lg">Semua Run (4)</button>
                            <button class="px-3 py-1.5 text-gray-400 hover:text-gray-600">Baru Masuk (1)</button>
                            <button class="px-3 py-1.5 text-gray-400 hover:text-gray-600">Sedang Direview (2)</button>
                            <button class="px-3 py-1.5 text-gray-400 hover:text-gray-600">Sudah Ditetapkan (0)</button>
                        </div>
                        <span class="text-[9px] font-semibold text-gray-400">Terakhir disinkronkan Hari ini: 14:40
                            WIB</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-gray-50/70 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="p-4 w-48">ID & Tanggal Run</th>
                                    <th class="p-4 w-32">Fitness Score</th>
                                    <th class="p-4 w-36">Hard Constraint</th>
                                    <th class="p-4">Parameter GA (Pop/Gen/Pc/Pm)</th>
                                    <th class="p-4 w-32">Status</th>
                                    <th class="p-4 w-24 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-50 font-medium">
                                <!-- Baris 1: RUN-2026-004 (Sedang Dipilih) -->
                                <tr class="bg-blue-50/20 hover:bg-blue-50/30 transition-colors">
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <p class="font-extrabold text-gray-800">RUN-2026-004</p>
                                            <span
                                                class="text-[8px] bg-blue-100 text-blue-700 font-bold px-1.5 py-0.2 rounded-md">Solusi
                                                Rekomendasi</span>
                                        </div>
                                        <p class="text-[9px] text-gray-400 font-normal mt-0.5">18 Okt 2026, 14:20 WIB
                                            &bull; Desktop Core v2.4</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-extrabold text-blue-700 text-sm">0.988 <span
                                                class="text-[9px] text-gray-400 font-normal">(98.8%)</span></p>
                                        <div class="w-16 bg-gray-100 h-1 rounded-full overflow-hidden mt-1">
                                            <div class="bg-blue-600 h-full w-[98%]"></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-600 font-bold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> 0 Bentrok <span
                                            class="text-[9px] text-gray-400 font-normal">(Layak)</span>
                                    </td>
                                    <td class="p-4 text-gray-500 font-mono text-[9px] leading-relaxed">N: 150 | Gen:
                                        500 | <br> Pc: 0.85 | Pm: 0.1 | <br> TS (k=5)</td>
                                    <td class="p-4"><span
                                            class="px-2 py-0.5 bg-blue-50 border border-blue-100 text-blue-700 font-bold text-[10px] rounded-md">Baru
                                            Masuk</span></td>
                                    <td class="p-4 text-center"><span
                                            class="px-3 py-1.5 bg-blue-600 text-white font-bold text-[10px] rounded-lg shadow-sm block select-none text-center">Sedang
                                            Dipilih</span></td>
                                </tr>

                                <!-- Baris 2: RUN-2026-003 -->
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4">
                                        <p class="font-bold text-gray-800">RUN-2026-003</p>
                                        <p class="text-[9px] text-gray-400 font-normal mt-0.5">17 Okt 2026, 10:15 WIB
                                            &bull; Desktop Core v2.4</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-bold text-gray-700">0.962 <span
                                                class="text-[9px] text-gray-400 font-normal">(96.2%)</span></p>
                                        <div class="w-16 bg-gray-100 h-1 rounded-full overflow-hidden mt-1">
                                            <div class="bg-gray-400 h-full w-[96%]"></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-600 font-bold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> 0 Bentrok <span
                                            class="text-[9px] text-gray-400 font-normal">(Layak)</span>
                                    </td>
                                    <td class="p-4 text-gray-400 font-mono text-[9px] leading-relaxed">N: 100 | Gen:
                                        400 | <br> Pc: 0.80 | Pm: 0.03 | <br> Roulette</td>
                                    <td class="p-4"><span
                                            class="px-2 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold text-[10px] rounded-md">Sedang
                                            Preview</span></td>
                                    <td class="p-4 text-center"><button
                                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm w-full text-center">Lihat
                                            Detail</button></td>
                                </tr>

                                <!-- Baris 3: RUN-2026-002 -->
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4">
                                        <p class="font-bold text-gray-800">RUN-2026-002</p>
                                        <p class="text-[9px] text-gray-400 font-normal mt-0.5">15 Okt 2026, 16:45 WIB
                                            &bull; Desktop Core v2.4</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-bold text-gray-700">0.941 <span
                                                class="text-[9px] text-gray-400 font-normal">(94.1%)</span></p>
                                        <div class="w-16 bg-gray-100 h-1 rounded-full overflow-hidden mt-1">
                                            <div class="bg-gray-400 h-full w-[94%]"></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-600 font-bold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> 0 Bentrok <span
                                            class="text-[9px] text-gray-400 font-normal">(Layak)</span>
                                    </td>
                                    <td class="p-4 text-gray-400 font-mono text-[9px] leading-relaxed">N: 100 | Gen:
                                        300 | <br> Pc: 0.85 | Pm: 0.05 | <br> T (k=3) </td>
                                    <td class="p-4"><span
                                            class="px-2 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold text-[10px] rounded-md">Sedang
                                            Preview</span></td>
                                    <td class="p-4 text-center"><button
                                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm w-full text-center">Lihat
                                            Detail</button></td>
                                </tr>

                                <!-- Baris 4: RUN-2026-001 -->
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4">
                                        <p class="font-bold text-gray-800">RUN-2026-001</p>
                                        <p class="text-[9px] text-gray-400 font-normal mt-0.5">12 Okt 2026, 09:30 WIB
                                            &bull; Desktop Core v2.3</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-bold text-gray-700">0.910 <span
                                                class="text-[9px] text-gray-400 font-normal">(91.0%)</span></p>
                                        <div class="w-16 bg-gray-100 h-1 rounded-full overflow-hidden mt-1">
                                            <div class="bg-gray-300 h-full w-[91%]"></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-emerald-600 font-bold flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> 0 Bentrok <span
                                            class="text-[9px] text-gray-400 font-normal">(Layak)</span>
                                    </td>
                                    <td class="p-4 text-gray-400 font-mono text-[9px] leading-relaxed">N: 80 | Gen:
                                        200 | <br> Pc: 0.75 | Pm: 0.05 | <br> Roulette </td>
                                    <td class="p-4"><span
                                            class="px-2 py-0.5 bg-amber-50 border border-amber-100 text-amber-700 font-bold text-[10px] rounded-md">Arsip
                                            Uji Coba</span></td>
                                    <td class="p-4 text-center"><button
                                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm w-full text-center">Lihat
                                            Detail</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ================= MATRIX TABEL PREVIEW JADWAL MINGGUAN ================= -->
                <div
                    class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] overflow-hidden">
                    <!-- HEADER ATAS TABEL (LENGKAP DENGAN FILTER & CONTROLS) -->
                    <div
                        class="p-6 border-b border-gray-50 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[10px] bg-blue-600 text-white font-bold px-1.5 py-0.5 rounded">RUN-2026-004</span>
                                <h3 class="text-xs font-extrabold text-gray-800">Preview Hasil Jadwal Kuliah (Solusi
                                    Terbaik GA)</h3>
                            </div>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span
                                    class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-md flex items-center gap-1">
                                    <span class="w-1 h-1 bg-emerald-500 rounded-full"></span> 0 Hard Constraint Bentrok
                                </span>
                                <span
                                    class="text-[9px] font-semibold text-amber-700 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-md flex items-center gap-1">
                                    <span class="w-1 h-1 bg-amber-500 rounded-full"></span> 2 Minor Soft Constraint
                                    Diabaikan
                                </span>
                            </div>
                        </div>

                        <!-- Panel Kontrol & Filter Sisi Kanan Header -->
                        <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold">
                            <div class="flex items-center gap-1">
                                <span class="text-gray-400 font-semibold text-[9px]">Prodi:</span>
                                <select
                                    class="bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-[10px] font-bold text-gray-700 focus:outline-none">
                                    <option>Semua Program Studi (Gabungan)</option>
                                    <option>S1 Informatika</option>
                                    <option>S1 Sistem Informasi</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-gray-400 font-semibold text-[9px]">Hari:</span>
                                <select
                                    class="bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-[10px] font-bold text-gray-700 focus:outline-none">
                                    <option>Semua Hari</option>
                                    <option>Senin</option>
                                    <option>Selasa</option>
                                    <option>Rabu</option>
                                    <option>Kamis</option>
                                    <option>Jumat</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- GRID JADWAL MINGGUAN (LENGKAP SENIN - JUMAT) -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border-spacing-0 text-left">
                            <thead>
                                <tr
                                    class="bg-gray-50/80 text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center border-b border-gray-100">
                                    <th class="p-4 w-28 border-r border-gray-100 bg-gray-50/40">Hari / Waktu</th>
                                    <th class="p-4 border-r border-gray-100">Sesi 1<br><span
                                            class="text-[9px] text-gray-400 font-normal uppercase">07:30 - 10:00</span>
                                    </th>
                                    <th class="p-4 border-r border-gray-100">Sesi 2<br><span
                                            class="text-[9px] text-gray-400 font-normal uppercase">10:20 - 12:00</span>
                                    </th>
                                    <th class="p-4 border-r border-gray-100">Sesi 3<br><span
                                            class="text-[9px] text-gray-400 font-normal uppercase">13:00 - 15:30</span>
                                    </th>
                                    <th class="p-4 border-r border-gray-100">Sesi 4<br><span
                                            class="text-[9px] text-gray-400 font-normal uppercase">15:50 - 17:30</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="text-[10px] divide-y divide-gray-100 font-medium">

                                <!-- BARIS SENIN -->
                                <tr>
                                    <td
                                        class="p-4 text-center font-bold bg-gray-50/30 border-r border-gray-100 text-gray-700 text-xs">
                                        Senin</td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF2101 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Struktur Data</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Komputasi 1</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Dr. Hendra Wijaya | IF-3A
                                            </p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">SI1102 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Dasar Sistem Informasi</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B102</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Ratna Sari, Ph.D. | SI-1B
                                            </p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">TE3104 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Sinyal & Sistem</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung A - A204</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Ir. Budi Santoso | EL-5A</p>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center text-gray-300 font-normal italic">Kosong (Slot Bebas)
                                    </td>
                                </tr>

                                <!-- BARIS SELASA -->
                                <tr>
                                    <td
                                        class="p-4 text-center font-bold bg-gray-50/30 border-r border-gray-100 text-gray-700 text-xs">
                                        Selasa</td>
                                    <!-- Sesi 1 -->
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">BD1101 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Pengantar Bisnis Digital</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung C - C201</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Farhan S.Si., M.M. | BD-1A
                                            </p>
                                        </div>
                                    </td>
                                    <!-- Sesi 2 -->
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF3204 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Kecerdasan Buatan (A)</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Komputer AI</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Dr. Hendra Wijaya | IF-5A
                                            </p>
                                        </div>
                                    </td>
                                    <!-- Sesi 3 -->
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">MA1102 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Aljabar Linier Elementer</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B301</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Dewi Kartika, M.Sc | MA-1A
                                            </p>
                                        </div>
                                    </td>
                                    <!-- Sesi 4 -->
                                    <td class="p-3">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">FI1201 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Fisika Komputasi</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Fisika Dasar</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Agus Salim, Ph.D | FI-3A</p>
                                        </div>
                                    </td>
                                </tr>

                                <!-- BARIS RABU -->
                                <tr>
                                    <td
                                        class="p-4 text-center font-bold bg-gray-50/30 border-r border-gray-100 text-gray-700 text-xs">
                                        Rabu</td>
                                    <!-- Sesi 1 -->
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF2205 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Basis Data Terdistribusi</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Basis Data</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Siti Rahma, M.Kom | IF-3B</p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF3102 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Analisis Proses Bisnis</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B105</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Nadia Pratiwi, M.T | SI-3A
                                            </p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">TE2201 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Mikrokontroler & IoT </p>
                                            <p class="text-gray-400 font-medium">Ruang: Bengkel Elektronika</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Ir. Budi Santoso | EL-3B</p>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center text-gray-300 font-normal italic">Kosong</td>
                                </tr>

                                <!-- BARIS KAMIS -->
                                <tr>
                                    <td
                                        class="p-4 text-center font-bold bg-gray-50/30 border-r border-gray-100 text-gray-700 text-xs">
                                        Kamis</td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF3102 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Rekayasa Perangkat Lunak</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung A - A305</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Dr. Hendra Wijaya | IF-5B</p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">BD2202 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Manajemen Produk Digital</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B205</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Farhan S.Si., M.M. | BD-3A
                                            </p>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">MA2201 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Statiska Terapan</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B105</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Dewi Kartika, M.Sc | MA-3A
                                            </p>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">TE1101 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Rangkaian Listrik Dasar</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Elektro</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Ir. Budi Santoso | EL-1A</p>
                                        </div>
                                    </td>
                                </tr>

                                <!-- BARIS JUMAT -->
                                <tr>
                                    <td
                                        class="p-4 text-center font-bold bg-gray-50/30 border-r border-gray-100 text-gray-700 text-xs">
                                        Jumat</td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">IF1101 &bull; 3 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Algoritma & Pemrograman</p>
                                            <p class="text-gray-400 font-medium">Ruang: Lab Komputer 2</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Siti Rahma, M.Kom | IF-1A</p>
                                        </div>
                                    </td>
                                    <td
                                        class="p-3 border-r border-gray-100 text-center text-gray-300 font-normal italic">
                                        Jeda Sholat Jumat & Istirahat
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-amber-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-amber-800 font-bold uppercase text-[8px]">SI3301 &bull; 3
                                                SKS</p>
                                            <p class="text-gray-800 font-bold">Tata Kelola TI</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B201</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Nadia Pratiwi, M.T. | SI-5A
                                            </p>
                                            <br>
                                            <div class="flex items-center gap-2 text-amber-500 font-semibold">
                                                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path d="M12 2L1 21h22L12 2zm0 5.5L19.5 19h-15L12 7.5z" />
                                                    <path d="M11 10h2v5h-2zm0 6h2v2h-2z" />
                                                </svg>

                                                <p>
                                                    Di luar jam preferensi dosen <br> (Jumat Siang), Hard Constraint <br> aman.
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-gray-100">
                                        <div
                                            class="bg-blue-50/40 border-l-2 border-blue-600 p-2 rounded-r-lg space-y-0.5">
                                            <p class="text-blue-800 font-bold uppercase text-[8px]">FI2102 &bull; 2 SKS
                                            </p>
                                            <p class="text-gray-800 font-bold">Pendidikan Agama & Etika</p>
                                            <p class="text-gray-400 font-medium">Ruang: Gedung B - B104</p>
                                            <p class="text-gray-500 font-semibold">Dosen: Agus Salim, Ph.D. | FI-3B</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                

                <!-- ================= ACTION BAR KONFIRMASI PUBLIKASI ================= -->
                <div
                    class="bg-white border border-gray-100 p-4 rounded-2xl shadow-lg flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5 text-[10px] font-medium text-gray-500">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-bold text-gray-800">Konfirmasi Publikasi Jadwal Resmi</p>
                            <p class="text-gray-400">Menetapkan jadwal hasil run ini akan otomatis mempublikasikan <br> jadwal resmi ke portal Koor Prodi, Dosen, dan Mahasiswa serta mengunci revisi.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto justify-end">
                        <button
                            class="px-4 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor"
                                stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v10.5a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            Ekspor Draft Jadwal (Excel/PDF)
                        </button>
                        <button
                            class="px-4 py-2 bg-[#1e40af] hover:bg-[#1a3a9c] text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            Tetapkan Sebagai Jadwal Aktif FSTI &rarr;
                        </button>
                    </div>
                </div>

            </main>
        </div>
    </div>

</body>

</html>
