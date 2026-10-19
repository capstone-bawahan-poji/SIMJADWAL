# ga-engine

Engine penjadwalan mata kuliah untuk SIMJADWAL. **Target: FSTI.** Crate Rust ini berdiri sendiri, terpisah dari `apps/desktop` dan `apps/web`, sehingga bisa dikembangkan dan diuji dengan `cargo test` tanpa membuka aplikasi.

- Status dan hasil: [RANGKUMAN-HASIL.md](RANGKUMAN-HASIL.md)
- Spesifikasi kromosom dan kunci SKS: [SPEC-KROMOSOM.md](SPEC-KROMOSOM.md)
- Fungsi yang dipakai app desktop: [contoh-integrasi-desktop/README.md](contoh-integrasi-desktop/README.md)

## Susunan

```
data FSTI ─► dataset ─► kromosom (kunci SKS) ─► algoritma ─► penjadwalan ─► jadwal akhir + ruangan
                                                    │
                                                 evaluasi (fitness HC + SC, dipakai semua algoritma)
```

| Path | Isi |
|---|---|
| `data/fsti/` | `matkul.json`, `sesi.json`, `ruang.json` |
| `src/dataset.rs` | Memuat data, menyatukan nama dosen, membuang ruangan ganda, laporan kualitas data |
| `src/slot.rs` | 20 slot waktu dari sesi.json |
| `src/kromosom.rs` | Struktur kromosom, **kunci SKS**, validasi, decode, populasi awal |
| `src/evaluasi.rs` | Fitness: hard constraint (bentrok dosen, melebihi ruangan) + soft constraint (dosen padat, tanpa jeda) |
| `src/algoritma/` | Algoritma yang bisa diganti: `genetika.rs` (GA), `hill_climbing.rs` (pembanding) |
| `src/penjadwalan.rs` | Pintu masuk untuk desktop: pengaturan JSON, alokasi ruangan, jadwal akhir |
| `examples/demo.rs` | Laporan data + satu kromosom acak beserta penilaiannya |
| `examples/bandingkan.rs` | Menjalankan semua algoritma pada data FSTI dan membandingkan hasilnya |
| `contoh-integrasi-desktop/` | Contoh command Tauri yang memakai engine ini |

## Menjalankan

Butuh Rust 1.85 atau lebih baru.

```bash
cd apps/ga-engine
cargo test                                         # 42 test
cargo run --example demo                           # data FSTI + 1 kromosom acak
cargo run --release --example bandingkan           # GA vs Hill Climbing, seed 1-5
cargo run --release --example bandingkan -- 10     # seed 1-10
```

## Mengganti algoritma

Algoritma dipilih lewat satu nilai pengaturan, tanpa mengubah bagian lain:

```rust
use ga_engine::{jalankan_penjadwalan, siapkan, DatasetMentah, Pengaturan, PilihanAlgoritma};
use ga_engine::algoritma::{ParameterGa, ParameterHc};

let data = siapkan(&DatasetMentah::fsti_bawaan())?;

let ga = Pengaturan { algoritma: PilihanAlgoritma::Genetika(ParameterGa::default()), ..Default::default() };
let hc = Pengaturan { algoritma: PilihanAlgoritma::HillClimbing(ParameterHc::default()), ..Default::default() };

let hasil = jalankan_penjadwalan(&data, &ga, &mut |_kemajuan| true)?;  // atau &hc
println!("{:?}", hasil.hasil.penilaian);
```

Dari app desktop, pilihan yang sama dikirim sebagai JSON:

```json
{ "algoritma": { "nama": "genetika", "parameter": { "ukuran_populasi": 100 } }, "seed": 12345 }
{ "algoritma": { "nama": "hill_climbing", "parameter": {} }, "seed": 12345 }
```

Parameter yang tidak diisi memakai nilai bawaan.

### Menambah algoritma baru

1. Buat file baru di `src/algoritma/` (mis. `simulated_annealing.rs`) yang mengimplementasikan trait `Algoritma`:

   ```rust
   pub trait Algoritma {
       fn nama(&self) -> &'static str;
       fn jalankan(&self, layout: &GeneLayout, penilai: &Penilai, seed: u64,
                   pantau: &mut dyn FnMut(&Kemajuan) -> bool) -> HasilOptimasi;
   }
   ```

2. Di `src/algoritma/mod.rs`, tambahkan satu varian di `PilihanAlgoritma`, lalu daftarkan di `buat()` dan `semua_bawaan()`.

Selesai. Penilaian jadwal, kunci SKS, alokasi ruangan, dan app desktop tidak perlu diubah. Algoritma baru otomatis muncul di dropdown desktop lewat `daftar_algoritma`, dan otomatis ikut diuji oleh test yang menjalankan `semua_bawaan()`.

Syarat untuk algoritma baru: nilai gen hanya boleh diambil dari `layout.domain(i)`, supaya kunci SKS tetap terjaga.

## Fitness: hard constraint + soft constraint

| Jenis | Aturan | Arti | Bobot bawaan |
|---|---|---|---|
| HC | `dosen_bentrok` | Satu dosen mengajar dua kelas di slot yang sama | 1000 |
| HC | `melebihi_ruangan` | Kelas di satu slot lebih banyak dari jumlah ruangan (28) | 1000 |
| SC | `dosen_padat` | Dosen mengajar lebih dari 2 kelas dalam sehari (`batas_kelas_dosen_per_hari`) | 1 |
| SC | `dosen_tanpa_jeda` | Dosen mengajar di dua sesi bersambung (sesi 1 lalu 2, atau 3 lalu 4) | 1 |

Penalti = jumlah (bobot x banyak pelanggaran), fitness = 1 / (1 + penalti). Karena bobot HC 1000 kali bobot SC, satu bentrok dosen selalu lebih buruk daripada ratusan pelanggaran SC. Jadwal disebut **layak** jika HC = 0, dan **sempurna** jika HC dan SC sama-sama 0. Semua bobot dan batas bisa diubah lewat `bobot` di pengaturan.

Aturan SC di atas adalah usulan awal dari data yang tersedia dan perlu dikonfirmasi ke dosen pembimbing. Menambah aturan baru cukup di `src/evaluasi.rs`; algoritma tidak perlu diubah.

## Kunci SKS

Kelas 3 SKS hanya boleh di sesi 1 atau 3 (150 menit), kelas 2 SKS hanya di sesi 2 atau 4 (90 menit). Kunci ini berlaku di semua jalur:

- **Algoritma**: setiap gen hanya bisa bernilai slot dari domainnya, jadi GA maupun algoritma lain tidak mungkin melanggar.
- **Plot manual**: `layout.cek_kunci_sks(id_kelas, slot)` dan `kromosom.pindahkan(...)` menolak slot yang tidak sesuai. `layout.slot_terbuka(id_kelas)` memberi daftar slot yang boleh dipilih untuk ditandai di layar.

## Catatan: data fakultas lain

Target saat ini hanya FSTI. Engine sebenarnya sudah bisa memuat folder data lain (`DatasetMentah::dari_folder`) dan menggabungkan beberapa fakultas (`DatasetMentah::gabung`), tapi fitur ini belum menjadi fokus dan tidak perlu dikembangkan dulu.
