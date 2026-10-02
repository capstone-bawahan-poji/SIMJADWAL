# Rangkuman hasil ga-engine

25 September 2026 · Task 7 & 8 (sprint awal) · Abiem Akmal, AI Engineer

## Status

| Task | Status |
|---|---|
| 7. Scaffold Tauri + Vue desktop app | Scaffold sudah ada di `apps/desktop` dan jalan di macOS. Engine sudah dicoba tersambung ke desktop dan berhasil; kode sambungannya disimpan di `contoh-integrasi-desktop/`, **belum dipasang** karena backend belum mulai. |
| 8. Rancang struktur kromosom & encoding | **Selesai.** 26 test lulus (19 unit, 7 dengan data FSTI asli). |

Engine sengaja diletakkan di folder sendiri (`apps/ga-engine`), terpisah dari desktop dan web, supaya bisa dikembangkan dan diuji tanpa menunggu kedua aplikasi itu.

## Rancangan kromosom (ringkas)

- **1 kromosom** = 1 kandidat jadwal utuh.
- **1 gen** = 1 kelas (1 baris `matkul.json`), diurutkan menurut `id`.
- **Nilai gen** = id slot waktu, bilangan asli 1-20 (5 hari x 4 sesi).
- **Domain gen** dibatasi sesuai SKS: kelas 3 SKS hanya boleh di sesi 1/3, kelas 2 SKS di sesi 2/4. Jadi tiap gen hanya punya 10 pilihan, dan aturan SKS-sesi selalu terpenuhi tanpa perlu dipinalti.
- **Ruangan tidak masuk kromosom.** Data ruangan tidak punya kapasitas, jadi cukup dicek "jumlah kelas per slot tidak melebihi jumlah ruangan", lalu ruangan dibagikan setelah GA selesai. Ruang pencarian per gen turun dari 280 pilihan menjadi 10.
- **Populasi awal** memakai seed (ChaCha8), sehingga eksperimen bisa diulang dengan hasil yang sama.

Detail lengkap: [SPEC-KROMOSOM.md](SPEC-KROMOSOM.md).

## Hasil pada data FSTI

| Ukuran | Nilai |
|---|---|
| Kelas (= panjang kromosom) | 309 |
| Mata kuliah | 186 |
| Dosen | 83 (86 ejaan nama di data) |
| Ruangan | 28 (30 baris di data) |
| Slot waktu | 20 |
| Kelas 3 SKS / 2 SKS | 171 / 138 |
| Kelas per prodi | AK 25, BD 48, FI 26, IF 51, MA 25, RK 2, SI 55, ST 24, TE 53 |

Daya tampung ruangan cukup secara total: tiap tipe SKS punya 10 slot x 28 ruangan = 280 tempat, untuk 171 kelas 3 SKS dan 138 kelas 2 SKS.

Satu kromosom acak (seed 12345) menghasilkan 9-20 kelas per slot, tidak ada yang melebihi 28 ruangan. Kromosom acak ini **belum** jadwal yang layak pakai: bentrok dosen belum dinilai, karena itu tugas fungsi fitness.

## Temuan data yang perlu dicek tim

1. **Nama dosen tidak konsisten.** "Indrawan" ditulis 2 cara dan "Alvianus Kristian Sumual" 3 cara. Engine menyatukannya otomatis; tanpa ini, bentrok dosen yang sama tidak akan terdeteksi.
2. **Ruangan B102 tercantum 3 kali** di `ruang.json`. Dihitung satu.
3. **25 mata kuliah punya SKS berbeda antar kelasnya** (mis. Akuntansi Bisnis A 3 SKS, B 2 SKS, C 3 SKS). Perlu dipastikan disengaja.
4. **Tidak ada data semester**, jadi aturan "kelas satu angkatan tidak boleh bentrok" belum bisa dipakai.
5. **Tidak ada kapasitas ruangan**, jadi kecocokan ukuran kelas dan ruangan belum bisa dicek.
6. Ada label kelas X, W, Z dan beberapa kelas berlabel sama di satu MK. Aman karena engine memakai `id`.

## Kesiapan untuk fakultas kedua

Data saat ini baru satu fakultas (FSTI). Engine sudah disiapkan untuk fakultas tambahan:

- Data disimpan per fakultas di `data/<kode_fakultas>/` dengan tiga file JSON yang sama formatnya. Nama folder otomatis menjadi label fakultas.
- Bisa dijadwalkan **sendiri-sendiri** atau **bersama** (`DatasetMentah::gabung`). Jika bersama, ruangan dipakai bersama dan dosen yang mengajar di dua fakultas dikenali sebagai satu orang sehingga bentroknya ikut dicek.
- Penggabungan otomatis ditolak jika jam sesi kedua fakultas berbeda atau ada `id` kelas yang dobel.
- Sudah diuji dengan fakultas tiruan: ruangan yang sama tidak dihitung dua kali, dan dosen lintas fakultas terdeteksi.

**Perlu diputuskan tim setelah data fakultas kedua ada:**

- Apakah ruangan dipakai bersama oleh kedua fakultas?
- Apakah ada dosen yang mengajar di kedua fakultas?
- Apakah jam sesinya sama dengan FSTI?

Jika jawabannya "ya" untuk salah satu dari dua pertanyaan pertama, kedua fakultas sebaiknya dijadwalkan bersama.

Catatan: dua dosen berbeda yang namanya persis sama (sebelum gelar) akan dianggap satu orang. Hal ini perlu dicek saat data fakultas kedua masuk.

## Belum dikerjakan

| Pekerjaan | Keterangan |
|---|---|
| Fungsi fitness | Bentrok dosen, jumlah kelas per slot tidak melebihi jumlah ruangan, dan constraint lain dari tabel `hard_constraint`/`soft_constraint` |
| Seleksi, crossover, mutasi | Inisialisasi dan mutasi wajib memakai `layout.domain(i)`; crossover 1-point aman dipakai langsung |
| Alokasi ruangan | Dilakukan setelah GA selesai |
| Sambungan ke web | Menunggu API backend. Format keluaran engine (`JadwalEntry`) sudah ditetapkan di SPEC bagian 9 |
| Sambungan ke desktop | Contoh sudah ada di `contoh-integrasi-desktop/`, tinggal dipasang |

## Menjalankan

```bash
cd apps/ga-engine
cargo test                  # 26 test
cargo run --example demo    # laporan data FSTI + 1 kromosom acak
```
