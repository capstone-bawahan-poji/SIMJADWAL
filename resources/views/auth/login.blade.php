<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Penjadwalan Kuliah - Masuk</title>
    <!-- Hubungkan ke Vite agar Tailwind CSS aktif -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f8fafc] font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Spacer Atas -->
    <div></div>

    <!-- Container Utama -->
    <div class="flex justify-center px-4">
        <div class="w-full max-w-[460px] bg-white rounded-2xl shadow-[0_10px_35px_rgba(0,0,0,0.03)] border border-gray-100 p-8 sm:p-10 flex flex-col items-center">
            
            <!-- Logo Aplikasi Biru Box -->
            <div class="w-16 h-16 bg-[#1a43a3] rounded-2xl flex items-center justify-center shadow-md mb-5">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                </svg>
            </div>

            <!-- Judul Aplikasi -->
            <h1 class="text-xl font-bold text-gray-800 text-center mb-8 tracking-tight">
                Sistem Penjadwalan Kuliah
            </h1>

            <!-- Form -->
            <form method="POST" action="/login" class="w-full space-y-5">
                @csrf

                <!-- KOLOM KOTAK UTAMA: USERNAME / EMAIL / NIM -->
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">
                        EMAIL ATAU USERNAME / NIP / NIM
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <input type="text" name="email" required autofocus 
                            placeholder="Contoh: dosen@univ.ac.id atau 198203..." 
                            class="block w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 placeholder-gray-300 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-all" />
                    </div>
                </div>

                <!-- KOLOM KOTAK KEDUA: KATA SANDI -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                            KATA SANDI
                        </label>
                        <a href="/forgot-password" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 transition-colors">
                            Lupa Kata Sandi?
                        </a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <input type="password" name="password" required 
                            placeholder="Masukkan kata sandi Anda" 
                            class="block w-full pl-10 pr-10 py-2.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 placeholder-gray-300 focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-all" />
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center cursor-pointer">
                            <svg class="h-4 w-4 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- TOMBOL MASUK -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-[#1a43a3] hover:bg-[#12327a] text-white text-xs font-bold rounded-lg shadow-sm transition-all duration-150 text-center">
                        Masuk ke Sistem
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer Bawah -->
    <footer class="w-full text-center py-6 text-[10px] text-gray-400 font-medium">
        &copy; 2025 Biro Administrasi Akademik & Sistem Informasi. Hak Cipta Dilindungi.
    </footer>

</body>
</html>
