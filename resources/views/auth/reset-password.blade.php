<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi</title>
    <!-- Hubungkan ke Vite agar Tailwind CSS aktif -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-[#f8fafc] font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Spacer Atas untuk Menyeimbangkan Posisi Tengah -->
    <div></div>

    <!-- Container Utama -->
    <div class="flex justify-center px-4">
        <div class="w-full max-w-[500px] bg-white rounded-2xl shadow-[0_10px_35px_rgba(0,0,0,0.03)] border border-gray-100 p-8 sm:p-12 flex flex-col">
            
            <!-- Judul Halaman -->
            <h1 class="text-xl font-bold text-[#0f172a] text-center mb-8 tracking-tight">
                Atur Ulang Kata Sandi
            </h1>

            <!-- STEPPER PROGRESS NAVIGATION -->
            <div class="flex items-center justify-center w-full mb-8 px-4">
                <!-- Step 1: Verifikasi (Aktif) -->
                <div class="flex items-center gap-2 shrink-0">
                    <div class="w-6 h-6 bg-[#163a8a] rounded-full flex items-center justify-center shadow-sm">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <div class="text-left">
                        <p class="text-[10px] font-bold text-gray-800 leading-none">Verifikasi</p>
                        <p class="text-[8px] font-medium text-gray-400 mt-0.5">Kirim Kode</p>
                    </div>
                </div>

                <!-- Garis Penghubung Stepper -->
                <div class="flex-1 h-[1px] bg-gray-200 mx-4 max-w-[100px]"></div>

                <!-- Step 2: Sandi Baru (Belum Aktif) -->
                <div class="flex items-center gap-2 shrink-0">
                    <div class="w-6 h-6 bg-white border border-gray-200 rounded-full flex items-center justify-center text-[10px] font-bold text-gray-400">
                        2
                    </div>
                    <div class="text-left">
                        <p class="text-[10px] font-bold text-gray-400 leading-none">Sandi Baru</p>
                        <p class="text-[8px] font-medium text-gray-300 mt-0.5">Konfirmasi</p>
                    </div>
                </div>
            </div>

            <!-- ALERT NOTIFIKASI ERROR (Format Email Tidak Valid) -->
            <div class="bg-[#fef2f2] border border-[#fecaca] rounded-xl p-4 flex items-start justify-between gap-3 mb-6 relative">
                <div class="flex gap-2.5">
                    <!-- Ikon Peringatan Gembok/Tanda Seru Bulat -->
                    <div class="text-[#ef4444] mt-0.5 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-[11px] font-bold text-[#991b1b]">Format Email Tidak Valid</h4>
                        <p class="text-[10px] text-[#b91c1c] font-medium mt-0.5 leading-relaxed">
                            Mohon masukkan email aktif universitas yang terdaftar pada sistem akademik.
                        </p>
                    </div>
                </div>
                <!-- Tombol Close Alert (X) -->
                <button type="button" class="text-[#fca5a5] hover:text-[#ef4444] transition-colors cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- FORM INPUT EMAIL -->
            <form method="POST" action="/password/email" class="w-full space-y-6">
                @csrf
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                        EMAIL AKADEMIK TERDAFTAR
                    </label>
                    <div class="relative">
                        <!-- Ikon Surat (Email) -->
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0l-7.5-4.615a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <input type="email" name="email" value="dosen.informatika@univ.ac.id" required autofocus 
                            class="block w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 placeholder-gray-300 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-all font-medium" />
                    </div>
                    <p class="text-[9px] text-gray-400 font-medium mt-2">
                        Pastikan email memiliki domain aktif civitas academica (@univ.ac.id).
                    </p>
                </div>

                <!-- BUTTON ACTIONS ROW -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                    <!-- Tombol Kirim Utama -->
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-[#163a8a] hover:bg-[#123075] text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center justify-center gap-1.5 shrink-0">
                        Kirim Link & Kode Reset
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                    <!-- Tautan Kembali ke Login -->
                    <a href="/" class="text-xs font-bold text-gray-400 hover:text-gray-600 transition-colors py-2 block shrink-0">
                        Kembali ke Login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer Bawah Hak Cipta -->
    <footer class="w-full text-center py-6 text-[10px] text-gray-400 font-medium">
        &copy; 2025 Biro Administrasi Akademik & Sistem Informasi. Hak Cipta Dilindungi Undang-Undang.
    </footer>

</body>
</html>

