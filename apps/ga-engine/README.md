# ga-engine

Engine algoritma genetika untuk SIMJADWAL. Crate Rust ini berdiri sendiri, terpisah dari `apps/desktop` dan `apps/web`, sehingga logika GA bisa dikembangkan dan diuji dengan `cargo test` tanpa membuka aplikasi apa pun.

Status dan hasil sejauh ini ada di [RANGKUMAN-HASIL.md](RANGKUMAN-HASIL.md). Spesifikasi encoding ada di [SPEC-KROMOSOM.md](SPEC-KROMOSOM.md).

## Isi

| Path | Isi |
|---|---|
| `data/<fakultas>/` | Data per fakultas: `matkul.json`, `sesi.json`, `ruang.json`. Saat ini baru `data/fsti/` |
| `src/dataset.rs` | Memuat data per fakultas, menggabungkan beberapa fakultas, menyatukan nama dosen, membuang ruangan ganda, laporan kualitas data |
| `src/slot.rs` | Slot waktu dari sesi.json |
| `src/kromosom.rs` | `GeneLayout` (domain slot per gen), `Kromosom`, validasi, decode, `populasi_awal` berbasis seed |
| `tests/dataset_fsti.rs` | Uji dengan data FSTI asli |
| `examples/demo.rs` | Laporan data + satu kromosom acak dan hasil decode-nya |
| `contoh-integrasi-desktop/` | Cara menyambungkan engine ke `apps/desktop` nanti (belum dipasang) |

## Menjalankan

Butuh Rust 1.85 atau lebih baru.

```bash
cd apps/ga-engine
cargo test                                          # 26 test
cargo run --example demo                            # data FSTI, seed 12345
cargo run --example demo -- 7                       # seed lain
cargo run --example demo -- 7 data/fakultas_baru    # satu fakultas lain
cargo run --example demo -- 7 data/fsti data/fakultas_baru   # dua fakultas dijadwalkan bersama
```

## Menambah fakultas

1. Buat folder `data/<kode_fakultas>/` (huruf kecil, mis. `data/ftik/`) berisi `matkul.json`, `sesi.json`, `ruang.json` dengan format yang sama seperti `data/fsti/`.
2. Nama folder otomatis menjadi label fakultas (`ftik` -> `FTIK`).
3. Coba dengan `cargo run --example demo -- 7 data/ftik`, lalu cek bagian "Peringatan data".

Dijadwalkan sendiri atau bersama FSTI, dua-duanya didukung:

```rust
// Sendiri
let data = siapkan(&DatasetMentah::dari_folder("data/ftik")?)?;

// Bersama (ruangan dipakai bersama, dosen lintas fakultas dicek bentroknya)
let data = siapkan(&DatasetMentah::gabung([
    DatasetMentah::dari_folder("data/fsti")?,
    DatasetMentah::dari_folder("data/ftik")?,
])?)?;
```

Penggabungan ditolak jika jam sesi kedua fakultas berbeda, atau ada `id` kelas yang sama di dua fakultas.

## Contoh pemakaian untuk operator GA

```rust
use ga_engine::{populasi_awal, siapkan, DatasetMentah};

let data = siapkan(&DatasetMentah::fsti_bawaan())?;
let populasi = populasi_awal(&data.layout, 100, 12345);
let jadwal = populasi[0].decode(&data.layout, &data.slot)?;

// Nilai yang boleh diisi gen ke-i (untuk inisialisasi dan mutasi)
let pilihan = data.layout.domain(i);
```
