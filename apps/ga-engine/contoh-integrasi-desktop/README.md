# Fungsi engine untuk app desktop

Daftar yang akan dipakai `apps/desktop` dari engine. Contoh lengkapnya ada di `lib.rs` (command Tauri) dan `App.vue` (tampilan uji kromosom). Keduanya belum dipasang ke `apps/desktop`; `lib.rs` sudah dicoba dikompilasi dengan Tauri 2.

## Command Tauri

| Command | Masukan | Keluaran | Dipakai untuk |
|---|---|---|---|
| `laporan_data` | - | `LaporanData` | Ringkasan data FSTI dan peringatan kualitas data |
| `daftar_algoritma` | - | daftar `PilihanAlgoritma` | Isi dropdown algoritma + parameter bawaannya |
| `jalankan` | `pengaturan` | `HasilPenjadwalan` | Menjalankan algoritma pilihan; kemajuan dikirim lewat event `kemajuan-penjadwalan` |
| `hentikan` | - | - | Tombol batal; algoritma mengembalikan hasil terbaik sejauh ini |
| `slot_terbuka` | `idKelas` | daftar id slot | Kunci SKS: menandai slot yang boleh dipilih saat plot manual |
| `nilai_jadwal` | `genes`, `bobot` (opsional) | penilaian + daftar pelanggaran | Menilai jadwal setelah diubah manual; ditolak jika melanggar kunci SKS |
| `buat_kromosom_acak` | `seed` | kromosom acak + decode | Tampilan uji kromosom (App.vue contoh) |

## Bentuk data penting

`pengaturan` (semua field boleh dikosongkan):

```json
{
  "algoritma": { "nama": "genetika", "parameter": { "ukuran_populasi": 100, "generasi_maks": 500 } },
  "bobot": { "dosen_bentrok": 1000, "melebihi_ruangan": 1000, "dosen_padat": 1, "dosen_tanpa_jeda": 1, "batas_kelas_dosen_per_hari": 2 },
  "seed": 12345
}
```

`HasilPenjadwalan`:

- `hasil`: nama algoritma, kromosom terbaik, `penilaian` (penalti, fitness, dosen_bentrok, melebihi_ruangan, dosen_padat, dosen_tanpa_jeda), jumlah iterasi dan evaluasi, `riwayat_penalti` untuk grafik konvergensi, `dihentikan`.
- `jadwal`: satu baris per kelas berisi mata kuliah, kelas, SKS, prodi, dosen, `waktu` (hari, sesi, jam), dan `ruangan`.
- `pelanggaran`: daftar pelanggaran beserta kelas yang terlibat, mis. `{"aturan":"dosen_bentrok","dosen":"...","id_slot":5,"id_kelas":["IF..-1","IF..-2"]}`. Nilai `aturan`: `dosen_bentrok` dan `melebihi_ruangan` (HC), `dosen_padat` dan `dosen_tanpa_jeda` (SC). Pelanggaran HC bisa dicatat ke tabel `override_pelanggaran_hc` saat koordinator tetap menyimpan jadwal manual yang bentrok.
- `durasi_ms`: lama algoritma berjalan.

Event `kemajuan-penjadwalan`: `{ iterasi, iterasi_maks, terbaik: { penalti, ... } }`.

## Cara memasang nanti

1. Di `apps/desktop/src-tauri/Cargo.toml`, bagian `[dependencies]`:

   ```toml
   ga-engine = { path = "../../ga-engine" }
   ```

2. Ganti `apps/desktop/src-tauri/src/lib.rs` dengan `lib.rs` di folder ini.
3. Buat tampilan Vue yang memanggil command di atas (`App.vue` di folder ini hanya contoh untuk uji kromosom).
4. Jalankan `npm run tauri dev` dari `apps/desktop`.
