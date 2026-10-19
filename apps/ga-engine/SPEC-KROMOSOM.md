# Spesifikasi struktur kromosom & encoding (Task 8)

Versi 0.4. Target: FSTI (`data/fsti`).

## 1. Ringkasan

| Konsep | Arti |
|---|---|
| Kromosom | Satu kandidat jadwal utuh untuk seluruh kelas FSTI |
| Gen | Satu kelas = satu baris `matkul.json` (identitas: `id`, mis. `IF2514101-1`) |
| Nilai gen | Id slot waktu, bilangan asli 1 sampai 20 |
| Domain gen (kunci SKS) | Hanya slot yang cocok dengan SKS kelas itu (10 dari 20 slot) |
| Panjang kromosom | 309 gen (jumlah baris `matkul.json`) |
| Ruangan | Tidak masuk kromosom, dialokasikan setelah algoritma selesai |

Contoh: `[13, 1, 10, 2, 9, ...]` berarti kelas pertama (urut `id`) di slot 13 = Kamis sesi 1, kelas kedua di slot 1 = Senin sesi 1, dan seterusnya.

## 2. Slot waktu (dari sesi.json)

|        | Sesi 1 (3 SKS) 08:00-10:30 | Sesi 2 (2 SKS) 10:30-12:00 | Sesi 3 (3 SKS) 13:00-15:30 | Sesi 4 (2 SKS) 15:30-17:00 |
|--------|:-:|:-:|:-:|:-:|
| Senin  | 1 | 2 | 3 | 4 |
| Selasa | 5 | 6 | 7 | 8 |
| Rabu   | 9 | 10 | 11 | 12 |
| Kamis  | 13 | 14 | 15 | 16 |
| Jumat  | 17 | 18 | 19 | 20 |

Id slot dibuat dengan mengurutkan hari lalu sesi, jadi urutan baris di sesi.json tidak berpengaruh. Id ini sama dengan `index + 1` pada notebook referensi.

## 3. Domain tiap gen (kunci SKS)

Slot yang boleh diisi sebuah kelas: `sesi` ada di `allowed_sessions` kelas itu, dan `type` slot sama dengan `sks` kelas itu.

- Kelas 3 SKS (171 kelas): slot 1, 3, 5, 7, 9, 11, 13, 15, 17, 19.
- Kelas 2 SKS (138 kelas): slot 2, 4, 6, 8, 10, 12, 14, 16, 18, 20.

Inisialisasi dan mutasi wajib memilih nilai dari domain ini (`GeneLayout::domain(i)`). Crossover 1-point tetap aman, karena gen di posisi yang sama selalu berbagi domain yang sama. Akibatnya aturan SKS-sesi terpenuhi sejak awal dan tidak perlu dipinalti di fitness. Notebook referensi masih memberi pinalti +5 untuk pelanggaran ini, padahal inisialisasinya sudah tidak pernah menghasilkannya.

Kunci yang sama juga berlaku untuk plot manual di app desktop: `GeneLayout::slot_terbuka(id_kelas)` memberi slot yang boleh dipilih, sedangkan `GeneLayout::cek_kunci_sks` dan `Kromosom::pindahkan` menolak slot yang tidak sesuai. Jadi kunci SKS berlaku untuk algoritma apa pun maupun perubahan manual.

## 4. Urutan gen

Gen diurutkan menurut `id`. Pasangan (`kode_mk`, `kelas`) tidak bisa dipakai sebagai identitas karena di data ada yang dobel (mis. dua kelas "A" untuk MA2514002).

Data pendamping tiap gen (tidak berevolusi): `id`, `kode_mk`, `nama_mk`, `kelas`, `sks`, `prodi` (prefix kode), `fakultas`, `dosen`, `dosen_idx`, `slot_diizinkan`.

## 5. Pengolahan data sebelum dipakai

- **Nama dosen disatukan.** Nama dibandingkan tanpa gelar (bagian sebelum koma pertama, huruf kecil, tanpa Dr./Ir./Prof.). Di data ada 86 ejaan untuk 83 orang, misalnya "Indrawan, S.Pd., M.Si." dan "Indrawan, S.Pd. M.Si.". Tanpa langkah ini, bentrok dosen yang sama tidak terdeteksi karena namanya dianggap berbeda. Notebook referensi masih membandingkan nama mentah.
- **Ruangan ganda dibuang.** B102 tercantum 3 kali, jadi ada 28 ruangan unik dari 30 baris.
- **Laporan kualitas data.** `siapkan()` mengembalikan `LaporanData` berisi ringkasan dan peringatan (lihat `cargo run --example demo`).

## 6. Validitas kromosom

Kromosom valid jika panjangnya sama dengan jumlah kelas dan setiap gen ada di domainnya. Kromosom valid belum tentu jadwal yang baik; pelanggaran hard constraint (bentrok dosen, kelebihan kelas per slot) dan soft constraint (dosen padat, tanpa jeda) dinilai oleh `Penilai` di `src/evaluasi.rs`, yang dipakai bersama oleh semua algoritma.

## 7. Kenapa ruangan tidak di-encode

Notebook referensi menyimpan (slot, ruangan) di tiap gen. Engine ini hanya menyimpan slot, karena:

1. Data ruangan tidak punya kapasitas, jadi ruangan mana pun sama cocoknya untuk kelas mana pun.
2. Dengan begitu, jadwal selalu bisa diberi ruangan selama jumlah kelas di satu slot tidak lebih dari 28. Syarat ini cukup dicek sebagai satu constraint di fitness.
3. Ruang pencarian jauh lebih kecil: tiap gen punya 10 pilihan, bukan 10 x 28 = 280.

Alokasi ruangan dilakukan setelah algoritma selesai (`alokasi_ruangan` di `src/penjadwalan.rs`): untuk tiap slot, kelas-kelasnya dibagikan ke ruangan yang masih kosong. Keputusan ini sesuai dengan kolom `jadwal_detail.ruangan_id` di ERD yang boleh kosong (unassigned).

## 8. Populasi awal

Setiap gen diisi slot acak dari domainnya. Generator acak memakai ChaCha8 dengan `base_seed`, sehingga seed yang sama selalu menghasilkan populasi yang sama di perangkat mana pun.

## 9. Kontrak data dengan aplikasi web

Masuk ke engine: tiga file JSON dengan format `data/fsti`, atau data yang sama bentuknya dari API web nanti.

Keluar dari engine: daftar `JadwalEntry` berisi `id_kelas`, `kode_mk`, `nama_mk`, `kelas`, `sks`, `prodi`, `fakultas`, `dosen`, `waktu` (`id_slot`, `nama_hari`, `sesi_ke`, `mulai`, `selesai`, `tipe_sks`), dan `ruangan`. Web mencari `waktu_slot.id` di basis data lewat pasangan (hari, sesi), bukan lewat `id_slot`.

## 10. Struktur ini tidak terikat pada satu algoritma

Kromosom, domain gen (kunci SKS), dan penilaian jadwal tidak bergantung pada GA. Algoritma lain (Hill Climbing sudah tersedia sebagai pembanding) memakai struktur yang sama, sehingga algoritma bisa diganti tanpa mengubah spesifikasi ini. Lihat README bagian "Mengganti algoritma".

Catatan: engine juga bisa memuat data fakultas lain (`DatasetMentah::dari_folder`, `DatasetMentah::gabung`), tapi target saat ini hanya FSTI.

## 11. Catatan data untuk tim

1. Data tidak memuat semester, jadi aturan "kelas satu angkatan tidak boleh bentrok" belum bisa dicek. Perlu kolom semester (atau daftar MK per angkatan) kalau aturan ini mau dipakai.
2. Data ruangan tidak memuat kapasitas.
3. 25 mata kuliah punya SKS berbeda antar kelasnya (mis. Akuntansi Bisnis A 3 SKS, B 2 SKS, C 3 SKS). Perlu dipastikan ini disengaja.
4. Ada label kelas X, W, Z, dan dua kelas berlabel sama pada beberapa MK. Engine aman karena memakai `id`.
