<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alokasi Ruangan - Sistem Penjadwalan</title>
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
                    <span class="text-[10px] bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">Admin Fakultas FSTI</span>
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
                        class="flex items-center gap-3 px-4 py-2.5 text-gray-400 hover:bg-gray-50 hover:text-gray-600 text-xs font-semibold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Penjadwalan GA
                    </a>

                    <!-- Menu 4: Alokasi Ruangan -->
                    <a href="{{ route('alokasi.ruangan') }}"
                       class="flex items-center gap-3 px-4 py-2.5 bg-[#f0f4ff] text-[#1e40af] text-xs font-bold rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
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
                        <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </span>
                    <input type="text" placeholder="Cari nomor run, parameter, atau tanggal..." class="w-full bg-gray-50 border border-gray-100 rounded-xl pl-9 pr-4 py-1.5 text-xs placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all">
                </div>
                <div class="flex items-center gap-4">
                    <button class="relative text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                        <span class="absolute top-1 right-1.5 w-1.5 h-1.5 bg-rose-500 rounded-full"></span>
                    </button>
                    <div class="h-5 w-[1px] bg-gray-200"></div>
                    <div class="text-right">
                        <h4 class="text-xs font-bold text-gray-800 leading-tight">Admin Fakultas FSTI</h4>
                        <p class="text-[10px] text-gray-400 font-medium">Biro Akademik</p>
                    </div>
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-sm">AF</div>
                </div>
            </header>

            <!-- Workspace Konten -->
            <main class="flex-1 p-8 overflow-y-auto space-y-6">
                
                <!-- Breadcrumbs & Header Title -->
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] font-semibold text-gray-400 flex items-center gap-1.5 mb-1">
                            <span>Dashboard</span> / <span>Alokasi Ruangan</span> / <span class="text-slate-600">Deteksi Konflik & Alokasi</span> &middot; <span class="bg-gray-200 px-1.5 py-0.5 rounded text-gray-500 font-bold">Semester Ganjil 2024/2025</span>
                        </div>
                        <h1 class="text-xl font-extrabold text-gray-800 tracking-tight">Alokasi Ruangan</h1>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 font-bold text-xs rounded-xl shadow-sm hover:bg-gray-50 transition-colors">Cek Ulang</button>
                        <button class="px-3 py-1.5 bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md transition-colors flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" /></svg>
                            Alokasikan Otomatis
                        </button>
                    </div>
                </div>

                <!-- ROW STATISTIC CARDS (3 KOLOM) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Card 1 -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">BELUM TERPETAKAN</p>
                            <h2 class="text-2xl font-black text-gray-800 mt-1">8 Slot</h2>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-amber-500 mr-2"></span>
                    </div>
                    <!-- Card 2 -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">BENTROK RUANG</p>
                            <h2 class="text-2xl font-black text-gray-800 mt-1">3 Kasus</h2>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-rose-500 mr-2"></span>
                    </div>
                                        <!-- Card 3 -->
                    <div class="bg-white rounded-2xl border border-gray-100 p-5 flex items-center justify-between shadow-[0_4px_20px_rgba(0,0,0,0.01)]">
                        <div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">RUANG SIAP PAKAI</p>
                            <h2 class="text-2xl font-black text-gray-800 mt-1">36 <span class="text-sm font-bold text-gray-300">/ 48</span></h2>
                        </div>
                        <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>
                    </div>
                </div>

                <!-- ================= TABEL 1: DAFTAR BENTROK RUANG ANTAR PRODI ================= -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] overflow-hidden">
                    <div class="p-5 border-b border-gray-50 flex items-center gap-3">
                        <h3 class="text-xs font-extrabold text-gray-800">Daftar Bentrok Ruang Antar Prodi</h3>
                        <span class="text-[9px] bg-rose-50 border border-rose-100 font-bold px-2 py-0.5 rounded text-rose-600">3 Kasus</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/70 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="p-4 w-40">Slot Waktu</th>
                                    <th class="p-4 w-32">Ruangan</th>
                                    <th class="p-4">Konflik Kelas</th>
                                    <th class="p-4">Rekomendasi Solusi</th>
                                    <th class="p-4 w-44 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-50 font-medium">
                                <!-- Baris 1 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Senin, 07:30</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 1 &bull; 3 SKS</p>
                                    </td>
                                    <td class="p-4 font-bold text-gray-800">R. A206</td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">IF2101 Struktur Data <span class="text-gray-400 font-medium">(IF-A)</span></p>
                                        <p class="text-[#e11d48] font-bold mt-0.5">TE3104 Sinyal & Sistem <span class="text-rose-400 font-medium">(TE-B)</span></p>
                                    </td>
                                    <td class="p-4 text-slate-500 font-semibold">Pindah TE3104 ke R. B105</td>
                                    <td class="p-4 text-center space-x-1 flex items-center justify-center h-full pt-6">
                                        <button class="px-2.5 py-1.5 bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-[10px] rounded-lg transition-colors">Terapkan</button>
                                        <button class="px-2.5 py-1.5 bg-white border border-gray-200 text-gray-500 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors">Manual</button>
                                    </td>
                                </tr>
                                <!-- Baris 2 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Selasa, 10:20</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 2 &bull; 3 SKS</p>
                                    </td>
                                    <td class="p-4 font-bold text-gray-800">Lab Komputer 1</td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">IF3204 Kecerdasan Buatan <span class="text-gray-400 font-medium">(IF)</span></p>
                                        <p class="text-[#e11d48] font-bold mt-0.5">BD1101 Praktikum Bisnis <span class="text-rose-400 font-medium">(BD)</span></p>
                                    </td>
                                    <td class="p-4 text-slate-500 font-semibold">Pindah BD1101 ke Lab Komputer AI</td>
                                    <td class="p-4 text-center space-x-1 flex items-center justify-center h-full pt-6">
                                        <button class="px-2.5 py-1.5 bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-[10px] rounded-lg transition-colors">Terapkan</button>
                                        <button class="px-2.5 py-1.5 bg-white border border-gray-200 text-gray-500 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors">Manual</button>
                                    </td>
                                </tr>
                                <!-- Baris 3 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Kamis, 13:00</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 3 &bull; 3 SKS</p>
                                    </td>
                                    <td class="p-4 font-bold text-gray-800">R. C302</td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">SI3301 Tata Kelola IT <span class="text-gray-400 font-medium">(SI-A)</span></p>
                                        <p class="text-[#e11d48] font-bold mt-0.5">MA2201 Statistika Terapan <span class="text-rose-400 font-medium">(MA-B)</span></p>
                                    </td>
                                    <td class="p-4 text-slate-500 font-semibold">Pindah MA2201 ke R. C305</td>
                                    <td class="p-4 text-center space-x-1 flex items-center justify-center h-full pt-6">
                                        <button class="px-2.5 py-1.5 bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-[10px] rounded-lg transition-colors">Terapkan</button>
                                        <button class="px-2.5 py-1.5 bg-white border border-gray-200 text-gray-500 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors">Manual</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                                <!-- ================= TABEL 2: SLOT BELUM TERPETAKAN KE RUANGAN ================= -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.01)] overflow-hidden">
                    <div class="p-5 border-b border-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h3 class="text-xs font-extrabold text-gray-800">Slot Belum Terpetakan ke Ruangan</h3>
                            <span class="text-[9px] bg-blue-50 border border-blue-100 font-bold px-2 py-0.5 rounded text-blue-600">8 Slot</span>
                        </div>
                        <span class="text-[10px] font-semibold text-gray-400">Menampilkan 3 dari 8 slot</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/70 text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="p-4 w-40">Waktu & Sesi</th>
                                    <th class="p-4">Mata Kuliah & Dosen</th>
                                    <th class="p-4 w-52">Kebutuhan Ruangan</th>
                                    <th class="p-4 w-44">Kapasitas</th>
                                    <th class="p-4 w-28 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-50 font-medium">
                                <!-- Baris 1 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Rabu, 10:20 - 12:50</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 2</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">FI1201 Fisika Komputasi</p>
                                        <p class="text-gray-400 font-medium mt-0.5">Dr. Supriatna</p>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 bg-blue-50/60 text-blue-600 border border-blue-100 rounded-lg text-[10px] font-bold">Lab Komputasi Sains</span>
                                    </td>
                                    <td class="p-4 text-gray-500 font-semibold">28 Mahasiswa</td>
                                    <td class="p-4 text-center">
                                        <button class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm">Alokasikan</button>
                                    </td>
                                </tr>
                                <!-- Baris 2 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Kamis, 07:30 - 10:00</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 1</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">IF3102 Rekayasa Perangkat Lunak</p>
                                        <p class="text-gray-400 font-medium mt-0.5">Anita Rahmawati, M.T.</p>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 bg-slate-50 border border-gray-100 text-gray-600 rounded-lg text-[10px] font-bold">Ruang Teori Besar</span>
                                    </td>
                                    <td class="p-4 text-gray-500 font-semibold">50 Mahasiswa</td>
                                    <td class="p-4 text-center">
                                        <button class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm">Alokasikan</button>
                                    </td>
                                </tr>
                                <!-- Baris 3 -->
                                <tr class="hover:bg-gray-50/30 transition-colors">
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">Jumat, 07:30 - 09:10</p>
                                        <p class="text-[10px] text-gray-400 font-normal mt-0.5">Sesi 1</p>
                                    </td>
                                    <td class="p-4">
                                        <p class="text-gray-800 font-bold">BD2202 Manajemen Produk</p>
                                        <p class="text-gray-400 font-medium mt-0.5">Farhan Kamil, M.B.A.</p>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 bg-purple-50/60 text-purple-600 border border-purple-100 rounded-lg text-[10px] font-bold">Ruang Teori Multimedia</span>
                                    </td>
                                    <td class="p-4 text-gray-500 font-semibold">38 Mahasiswa</td>
                                    <td class="p-4 text-center">
                                        <button class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 font-bold text-[10px] rounded-lg hover:bg-gray-50 transition-colors shadow-sm">Alokasikan</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ================= BOTTOM ACTION BAR ================= -->
                <div class="bg-white border border-gray-100 p-4 rounded-2xl shadow-lg flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-[10px] font-medium text-gray-400">Penyesuaian manual dapat dilakukan sewaktu-waktu di Matriks Jadwal.</p>
                    <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto justify-end">
                        <button class="px-4 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v10.5a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            Unduh Laporan
                        </button>
                        <button class="px-4 py-2 bg-[#0f172a] hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            Lanjut ke Lihat Hasil Jadwal &rarr;
                        </button>
                    </div>
                </div>

            </main>
        </div>
    </div>

</body>
</html>
