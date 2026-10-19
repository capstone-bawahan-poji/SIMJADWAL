# Rangkuman hasil ga-engine

16 Oktober 2026 · Bagian ML/AI · Abiem Akmal

## Sprint 2 · Task 12: GA engine core (crossover, mutasi, fitness HC+SC)

**Status: selesai.** Output: engine GA lengkap (seleksi, crossover, mutasi, fitness HC+SC), teruji pada data FSTI.

| Bagian | Isi | File |
|---|---|---|
| Fitness: hard constraint | Bentrok dosen, kelas melebihi jumlah ruangan per slot | `src/evaluasi.rs` |
| Fitness: soft constraint | Dosen mengajar lebih dari 2 kelas sehari, dosen mengajar dua sesi bersambung tanpa jeda | `src/evaluasi.rs` |
| Seleksi | Turnamen (3 kromosom acak, ambil yang penaltinya terkecil) + elitisme (2 terbaik dibawa langsung) | `src/algoritma/genetika.rs` |
| Crossover | 1 titik, peluang 0,9 | `src/algoritma/genetika.rs` |
| Mutasi | Tiap gen berpeluang 0,01 diganti slot acak dari domainnya (kunci SKS tetap terjaga) | `src/algoritma/genetika.rs` |
| Pengujian | 42 test otomatis lulus, termasuk uji dengan data FSTI asli | `tests/`, `src/**` |

Penalti = jumlah (bobot x pelanggaran), dengan bobot HC = 1000 dan SC = 1. Fitness = 1 / (1 + penalti). Jadwal **layak** jika HC = 0, dan **sempurna** jika HC dan SC = 0.

## Revisi dari dosen pembimbing (bagian ML/AI)

| Masukan | Status |
|---|---|
| Algoritma harus mudah diganti | Selesai. Fitness dipisah dari algoritma; semua algoritma memakai satu antarmuka dan dipilih lewat satu pengaturan. Tersedia GA dan Hill Climbing (pembanding). |
| Target fokus ke FSTI | Selesai. |
| Fitur pengunci sesuai SKS | Selesai. Berlaku untuk semua algoritma dan untuk plot manual. |
| Siapkan yang akan dipakai app desktop | Selesai di sisi engine (lihat `contoh-integrasi-desktop/`). |
| Link download app desktop | Bukan bagian ML; dikerjakan di sisi aplikasi desktop. |

## Hasil pada data FSTI

Data: 309 kelas, 83 dosen, 28 ruangan, 20 slot. Tiap algoritma dijalankan dengan 10 seed.

| Algoritma | Penalti awal | Penalti akhir | HC akhir | SC akhir | Iterasi | Waktu |
|---|---|---|---|---|---|---|
| Algoritma Genetika | 14.024-19.024 | 0 | 0 | 0 | 50-69 generasi | ~30 ms |
| Hill Climbing | 20.031-35.033 | 0 | 0 | 0 | 648-1.838 langkah | < 5 ms |

Penalti awal yang besar berasal dari bentrok dosen di jadwal acak (sekitar 14-35 bentrok x bobot 1000). Kedua algoritma selalu menghasilkan jadwal sempurna: tanpa bentrok, tanpa slot penuh, semua sesuai kunci SKS, semua kelas mendapat ruangan, dan setiap dosen paling banyak 2 kelas sehari dengan jeda di antaranya.

**Uji tambahan (aturan diperketat):** dengan batas 1 kelas per dosen per hari, pelanggaran SC tidak mungkin nol. Dosen dengan lebih dari 5 kelas pasti melanggar, sehingga minimum teoretisnya 12. GA (rata-rata 12,2) dan Hill Climbing (12,0) sama-sama mencapai atau mendekati angka minimum itu. Ini menunjukkan fitness dan algoritma bekerja benar.

### Temuan penting

Dengan aturan yang bisa dihitung dari data sekarang, penjadwalan FSTI **tergolong mudah**. Algoritma sederhana (Hill Climbing) sudah cukup dan bahkan lebih cepat, sehingga keunggulan GA belum terlihat. GA baru akan berbeda nyata jika ada aturan yang saling bertentangan, misalnya bentrok satu angkatan (butuh data semester) atau kapasitas ruangan (butuh data kapasitas). Karena algoritma bisa diganti, keduanya tetap tersedia dan bisa dibandingkan secara adil.

## Perlu dikonfirmasi

1. Data **semester/angkatan** tiap mata kuliah, untuk aturan bentrok satu angkatan.
2. Data **kapasitas ruangan** (dan jumlah mahasiswa per kelas jika ada).
3. Apakah dua soft constraint di atas sudah sesuai, atau ada aturan lain yang diinginkan (beserta bobotnya).
4. 25 mata kuliah yang SKS-nya berbeda antar kelas: disengaja atau salah data.

## Tahap berikutnya

- Menambah aturan fitness sesuai jawaban di atas.
- Tuning parameter GA (ukuran populasi, peluang crossover/mutasi) setelah aturannya lengkap.
- Memasang command engine ke `apps/desktop` bersama tim desktop.

## Menjalankan

```bash
cd apps/ga-engine
cargo test                                   # 42 test
cargo run --example demo                     # data FSTI + 1 jadwal acak dan penilaiannya
cargo run --release --example bandingkan     # GA vs Hill Climbing pada data FSTI
```
