# Contoh integrasi ke apps/desktop

Belum dipasang ke `apps/desktop`, karena backend belum mulai dan struktur desktop masih dipegang rekan tim. File di sini sudah pernah dicoba dan jalan di Tauri + Vue (tombol "Buat kromosom acak" menampilkan 309 kelas FSTI dan jadwal hasil decode). Tampilan ini hanya untuk menguji engine, bukan UI akhir.

Kalau nanti mau dipasang:

1. Di `apps/desktop/src-tauri/Cargo.toml`, bagian `[dependencies]`, tambahkan:

   ```toml
   ga-engine = { path = "../../ga-engine" }
   ```

2. Ganti `apps/desktop/src-tauri/src/lib.rs` dengan `lib.rs` di folder ini (menambah command `buat_kromosom_acak`).
3. Ganti atau gabungkan `apps/desktop/src/App.vue` dengan `App.vue` di folder ini.
4. Jalankan `npm run tauri dev` dari `apps/desktop`.
