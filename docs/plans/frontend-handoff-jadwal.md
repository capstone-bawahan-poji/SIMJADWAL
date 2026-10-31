# Handoff Frontend — Hasil Run, Jadwal Aktif, Override, Import, Ruangan, Dashboard

Dokumen ini untuk pengembang frontend yang mengganti halaman dummy dengan data asli. Backend untuk semua tugas di bawah **sudah selesai dan sudah diuji**. Tugasmu hanya di sisi frontend (web Vue + Inertia, dan sebagian desktop Tauri).

Kalau ada endpoint yang kurang atau perilaku API yang terasa salah, jangan ubah kontrak API sendiri. Catat, lalu diskusikan dengan pemilik backend.

---

## 0. Persiapan

### Jalankan aplikasi

```bash
cd apps/web
./vendor/bin/sail up -d                          # termasuk service gotenberg untuk PDF
./vendor/bin/sail artisan migrate:fresh --seed   # menghapus isi DB lokal, lalu mengisi data demo
./vendor/bin/sail npm run dev
```

### Akun demo

Password semua akun demo = nilai `SEED_DEFAULT_PASSWORD` di `.env`.

| Email | Role | Untuk mengetes |
|---|---|---|
| `admin.fsti@example.test` | admin_fakultas FSTI | Review, aktifkan, publish, override |
| `admin.fpb@example.test` | admin_fakultas FPB | Run aktif yang belum dipublish, akses ditolak ke data FSTI |
| `admin.if@example.test` | admin_prodi Informatika | Lihat jadwal, import mata kuliah |
| `mahasiswa.if@example.test` | mahasiswa Informatika | Lihat jadwal (hanya yang sudah dipublish) |
| `dosen.if01@example.test` | dosen | Lihat jadwal lintas fakultas |
| `admin.tpb@example.test` | admin_tpb | Lihat jadwal semua fakultas |

### Data demo jadwal

- **FSTI**: satu batch berisi 3 run. Run pertama aktif dan sudah dipublish, dan punya satu baris hasil override manual.
- **FPB**: satu run aktif yang **belum** dipublish. Hanya admin FPB (dan superadmin) yang bisa melihatnya.

### Sumber acuan

- Bentuk request/response lengkap: `docs/api/openapi.yaml`. Untuk melihatnya di browser: `npx -y @redocly/cli@1.25.0 build-docs docs/api/openapi.yaml -o api-docs.html`.
- Aturan status code, error code, dan event realtime: `docs/api/CONVENTIONS.md`.

### Pola yang dipakai di web

- Web memanggil **`/api/internal/*`** lewat `resources/js/lib/api.ts` (session + CSRF, tanpa token). Path dan bentuk datanya sama dengan `/api/v1/*` di openapi.
- Response sukses: `{ "data": ... }`. Response error: `{ "message", "code", "errors" }`. Pakai `toApiError()` dan `fieldErrors()` dari `lib/api.ts`.
- Untuk list dan form, pakai composable yang sudah ada: `useApiList`, `useApiForm`.
- Untuk refresh otomatis saat data berubah di tempat lain, pakai `useResourceChanges` (event `ResourcesChanged` dari Reverb). Tabel yang relevan: `scheduling_runs`, `schedule_details`, `violation_logs`.
- Pesan error dari server sudah dalam bahasa Indonesia. Tampilkan apa adanya, jangan diterjemahkan ulang.
- Setelah halaman memakai data asli, hapus `<SampleDataBadge />` dan import dari `resources/js/mocks/*`. Hapus file mock yang tidak dipakai lagi.
- Tipe TypeScript untuk semua response di dokumen ini sudah ada di `resources/js/types/models.ts` (bagian *Scheduling* dan *Dashboards*): `SchedulingRunSummary`, `SchedulingRunDetail`, `RunScheduleDetail`, `Violation`, `Schedule`, `ScheduleFilters`, `ScheduleOptions`, `ScheduleOverridePayload`, `FacultyDashboard`, `StudyProgramDashboard`.

### Urutan pengerjaan yang disarankan

1. Enhance Import Data Mata Kuliah (§5). Paling lepas dari yang lain, cocok untuk mengenal pola API.
2. Sinkronisasi hasil run (§3).
3. Review Hasil Run & Tetapkan Jadwal Aktif (§1).
4. Multi-run comparison + progress realtime (§4).
5. Lihat/Filter Jadwal + Override Manual (§2). Buat modal override sebagai komponen sendiri, karena dipakai lagi di §6.
6. Alokasi Ruangan (§6).
7. Dashboard Fakultas dan Prodi (§7).
8. Export jadwal + halaman Periode Akademik (§8). Tombol export dipasang di halaman jadwal §2.

---

## 1. Review Hasil Run & Tetapkan Jadwal Aktif (Admin Fakultas)

**Halaman**: `resources/js/Pages/Fakultas/Ga/Index.vue` (route `fakultas.ga.index`). Sekarang memakai `mocks/ga.ts`.

### Konsep

Satu run punya tiga kemungkinan status:

| Status | Kondisi data | Siapa yang melihat di halaman jadwal |
|---|---|---|
| Draft | `is_active: false`, `published_at: null` | Tidak muncul di jadwal |
| Aktif, belum publish | `is_active: true`, `published_at: null` | Hanya admin fakultas itu dan superadmin |
| Aktif, sudah publish | `is_active: true`, `published_at` terisi | Semua role sesuai scope |
| Arsip | `is_active: false`, `published_at` terisi | Tidak muncul (pernah dipublish, lalu digantikan) |

Mengaktifkan dan memublikasikan adalah **dua langkah terpisah**. Admin bisa mengaktifkan run, merapikannya dengan override, lalu memublikasikannya.

### Endpoint

| Method | Path | Kegunaan |
|---|---|---|
| GET | `/scheduling-runs?period=&batch_id=&is_active=&page=&per_page=` | Daftar run fakultas, terbaru dulu. `is_active=1` = hanya run aktif |
| GET | `/scheduling-runs/{id}` | Detail run, termasuk `parameter_snapshot` dan `fitness_history` |
| GET | `/scheduling-runs/{id}/schedule-details?study_program_id=&day=` | Isi jadwal run (untuk matriks hari × sesi) |
| GET | `/scheduling-runs/{id}/violations?category=HC\|SC` | Daftar pelanggaran |
| POST | `/scheduling-runs/{id}/activate` | Tetapkan sebagai jadwal aktif |
| POST | `/scheduling-runs/{id}/publish` | Publikasikan jadwal aktif |

Field ringkasan yang berguna di daftar run: `best_fitness`, `fitness_percent`, `hc_violations`, `sc_unmet`, `unassigned_rooms`, `schedule_details_count`, `selection_operator`, `crossover_rate`, `mutation_rate`, `is_active`, `published_at`.

### Yang harus dibuat

1. **Daftar run** periode berjalan. Tampilkan badge status (Draft / Aktif / Dipublikasikan / Arsip) dan angka ringkasan di atas.
2. **Detail run**:
   - Matriks hari × sesi dari `schedule-details`, dengan filter prodi dan hari.
   - Tandai sel dengan `room: null` ("belum dapat ruang") dan sel dengan `violations_count > 0`.
   - Tab atau panel pelanggaran dari `violations`, bisa difilter HC/SC.
   - Grafik `fitness_history` (opsional; desktop punya contoh di `views/run/FitnessChart.vue`).
3. **Tombol "Tetapkan sebagai Jadwal Aktif"**:
   - Tampilkan dialog konfirmasi. Jelaskan bahwa jadwal aktif yang lama akan digantikan.
   - Kalau run punya `hc_violations > 0`, tampilkan peringatan di dialog. Server tetap mengizinkan.
   - Error 409 `INVALID_STATE` muncul kalau run belum selesai, atau kalau run bentrok dengan jadwal aktif fakultas lain. Tampilkan `message` dari server.
4. **Tombol "Publikasikan"**: hanya muncul untuk run yang aktif dan belum dipublish. Pakai dialog konfirmasi. Teksnya: setelah dipublish, dosen dan mahasiswa bisa melihat jadwal ini.
5. Setelah activate atau publish berhasil, muat ulang daftar run. Response berisi run terbaru, tapi status run lain juga berubah.

### Kriteria selesai

- [ ] Login `admin.fsti`: tiga run FSTI terlihat, satu berstatus Dipublikasikan.
- [ ] Mengaktifkan run lain mengubah run lama menjadi Arsip, dan run baru menjadi Aktif (belum publish).
- [ ] Publish run baru berhasil. Login `mahasiswa.if` lalu buka halaman jadwal: jadwal FSTI muncul lagi.
- [ ] Error 409 tampil sebagai pesan yang terbaca, bukan error generik.
- [ ] Tidak ada import dari `mocks/ga.ts` lagi.

---

## 2. Lihat/Filter Jadwal (semua role) + Override Manual

**Halaman**: `resources/js/Pages/Prodi/ScheduleMatrix/Index.vue` sekarang memakai `mocks/schedule.ts`. Halaman ini perlu bisa diakses semua role.

- Tambahkan route dan menu baru untuk dosen, mahasiswa, admin_tpb, dan admin_fakultas di `routes/web.php` dan `resources/js/lib/navigation.ts`. Saat ini dosen, mahasiswa, dan admin_tpb belum punya menu.
- Satu komponen halaman sebaiknya dipakai bersama semua role. Yang berbeda hanya filter default dan tombol override.

### Endpoint

**`GET /schedules`**

Parameter (semua opsional): `period`, `faculty_id`, `study_program_id`, `semester`, `class_number` (1 = A), `course_id`, `lecturer_id`, `room_id`, `day` (1 = Senin), `unassigned=1` (hanya kelas tanpa ruang), `over_capacity=1` (hanya kelas yang ruangnya lebih kecil dari kapasitas kelas).

Response:

```json
{
  "data": {
    "period": "2026/2027-GANJIL",
    "runs": [
      { "id": 1, "faculty": { "id": 1, "code": "FSTI", "name": "..." }, "published_at": "2026-10-30T09:00:00+08:00" }
    ],
    "schedule_details": [
      {
        "scheduling_run_id": 1,
        "id": 42,
        "course": { "id": 7, "code": "IF2514201", "name": "Basis Data", "sks": 3, "semester": 3, "class_capacity": 45, "study_program": { "id": 1, "code": "IF", "name": "Informatika" } },
        "class_number": 1,
        "meeting_number": 1,
        "lecturer": { "id": 15, "name": "Dosen Informatika 01", "title": "S.Kom., M.Kom." },
        "time_slot": { "id": 1, "day": 1, "session": 1, "start_time": "07:30", "end_time": "10:00" },
        "room": { "id": 5, "code": "B207", "name": "Gedung Perkuliahan", "capacity": 50 },
        "is_overridden": false,
        "overridden_at": null
      }
    ]
  }
}
```

Hasilnya tidak dipaginasi, karena matriks butuh semua baris.

Server sudah mengatur scope per role. Frontend tidak perlu menyaring ulang:

- superadmin, admin_tpb, dan dosen melihat semua fakultas. Role lain hanya fakultasnya sendiri.
- Run aktif yang belum dipublish hanya terlihat oleh superadmin dan admin fakultasnya.

**`GET /schedule-details/{id}/options`** (isi modal override)

Mengembalikan semua slot. Untuk tiap slot: apakah baris ini boleh pindah ke sana (`available`), alasannya kalau tidak boleh (`reasons`), dan ruang yang kosong dan muat (`rooms`, urut dari kapasitas paling pas).

```json
{
  "data": {
    "current": { "time_slot_id": 1, "room_id": 5 },
    "time_slots": [
      { "id": 6, "day": 2, "session": 2, "start_time": "10:20", "end_time": "12:00",
        "available": true, "reasons": [],
        "rooms": [ { "id": 9, "code": "E101", "name": null, "capacity": 60 } ] },
      { "id": 7, "day": 2, "session": 3, "start_time": "13:00", "end_time": "15:30",
        "available": false, "reasons": ["Dosen sudah mengajar Struktur Data kelas B pada slot ini."],
        "rooms": [] }
    ]
  }
}
```

Frontend **tidak bisa** menghitung ketersediaan ini sendiri dari `/schedules`, karena jadwal fakultas lain yang belum dipublish tidak terlihat. Selalu pakai endpoint ini untuk mengisi pilihan di modal. Hak akses dan 409 sama dengan PATCH.

**`PATCH /schedule-details/{id}`** (override manual)

```json
{ "time_slot_id": 6, "room_id": 9 }
```

- Kedua field **wajib dikirim**. `room_id` boleh `null`, artinya kelas dilepas dari ruangan.
- Hanya berlaku untuk baris dari run aktif. Hanya admin_fakultas (fakultasnya sendiri) dan superadmin yang boleh.
- `200`: response berisi baris yang sudah diperbarui, dengan `is_overridden: true`.
- `422`: perubahan melanggar aturan wajib. Pesan ada di `errors.time_slot_id` dan/atau `errors.room_id`, misalnya "Dosen sudah mengajar Struktur Data kelas B pada slot ini." atau "Ruangan sudah dipakai Kalkulus II kelas C pada slot ini."
- `409 INVALID_STATE`: run tidak aktif.
- `403 FORBIDDEN`: bukan haknya.

Daftar lengkap pesan validasi ada di deskripsi endpoint di `openapi.yaml`.

### Yang harus dibuat

1. **Filter**: periode, fakultas (hanya untuk role yang melihat banyak fakultas), prodi, semester, kelas, dosen, ruang, hari. Simpan filter di query string URL supaya bisa dibagikan.
   - Data dropdown diambil dari endpoint master data yang sudah ada (`/study-programs`, `/lecturers`, `/rooms`, `/time-slots`).
2. **Filter default per role** (diatur di frontend):
   - mahasiswa dan admin_prodi: `study_program_id` milik sendiri.
   - dosen: `lecturer_id` milik sendiri.
   - admin_fakultas: tanpa filter tambahan.
3. **Tampilan matriks** hari × sesi. Sumbu sesi diambil dari `/time-slots`, jangan di-hardcode. Kalau perlu, tambahkan tampilan tabel/list.
4. **Banner** "Jadwal belum dipublikasikan" kalau ada `runs[].published_at === null`. Hanya admin yang akan melihat kondisi ini.
5. **Badge "Diubah manual"** untuk baris dengan `is_overridden: true`.
6. **Override** (hanya untuk admin_fakultas dan superadmin):
   - Klik sel, lalu modal terbuka dan memanggil `GET /schedule-details/{id}/options`.
   - Tampilkan slot sebagai grid hari × sesi. Slot `available: false` dibuat nonaktif dengan tooltip berisi `reasons`. Slot saat ini (`current.time_slot_id`) ditandai.
   - Setelah slot dipilih, dropdown ruang diisi dari `rooms` slot itu, ditambah opsi "Tanpa ruang".
   - Kalau 422, tampilkan pesan di bawah field yang sesuai. Modal tetap terbuka.
   - Kalau 200, ganti baris di state dengan `data` dari response. Tidak perlu memuat ulang seluruh jadwal.
   - Tombol override disembunyikan untuk role lain. Server tetap menolak dengan 403 kalau dipaksa.
7. **Kondisi kosong**: tampilkan "Belum ada jadwal yang dipublikasikan untuk periode ini" kalau `schedule_details` kosong.

### Kriteria selesai

- [ ] `mahasiswa.if` melihat jadwal FSTI dengan filter prodi IF terisi otomatis, dan tidak melihat FPB.
- [ ] `admin.fpb` melihat banner "belum dipublikasikan" untuk FPB.
- [ ] `dosen.if01` melihat jadwalnya sendiri, dan bisa membuka fakultas lain lewat filter.
- [ ] `admin.fsti` berhasil memindahkan satu kelas ke slot kosong. Badge "Diubah manual" muncul.
- [ ] Memindahkan kelas ke slot saat dosennya sedang mengajar menampilkan pesan bentrok dari server.
- [ ] Tidak ada import dari `mocks/schedule.ts` lagi.

---

## 3. Sinkronisasi hasil run ke server dan UI

**Lokasi**: desktop `apps/desktop/src/views/SyncView.vue` dan `composables/useRunSync.ts`. Web tidak perlu halaman khusus.

### Kondisi sekarang

- Desktop sudah bisa mengunggah run lewat `POST /api/v1/scheduling-runs`. Pemanggilannya lewat command Rust (`lib/api/schedulingRuns.ts` → `uploadResult`).
- Upload bersifat idempotent berdasarkan `client_run_id`. Mengunggah run yang sama dua kali aman dan menghasilkan 200.
- Server menolak dengan `409 DATASET_CHANGED` kalau data master berubah sejak dataset dimuat.
- Setelah upload, server mengirim event `ResourcesChanged` (`scheduling_runs`).

### Yang harus dibuat

1. Desktop: status per run harus jelas (Belum dikirim / Terkirim / Gagal) beserta pesan error dari server.
   - Untuk `DATASET_CHANGED`, tampilkan pesan bahwa data sudah berubah dan run perlu dijalankan ulang. Run ini tidak bisa diunggah ulang.
2. Desktop: tombol "Kirim semua yang belum terkirim".
3. Web: halaman Review Hasil Run (§1) harus memuat ulang daftar run otomatis saat ada event `ResourcesChanged` untuk `scheduling_runs`. Pakai `useResourceChanges`.
4. Web: halaman jadwal (§2) juga memuat ulang saat `scheduling_runs` atau `schedule_details` berubah. Contohnya saat admin lain mengaktifkan run atau melakukan override.

### Kriteria selesai

- [ ] Run yang diunggah dari desktop muncul di halaman Review web tanpa refresh manual.
- [ ] Override di satu browser langsung terlihat di browser lain yang membuka halaman jadwal.
- [ ] Error `DATASET_CHANGED` tampil dengan pesan yang bisa dipahami.

---

## 4. Multi-run comparison + progress realtime

### Progress realtime (desktop saja)

GA berjalan lokal di desktop. Progress dikirim dari engine Rust ke UI lewat Tauri event (lihat `composables/useGaRunner.ts` dan `views/run/RunProgressList.vue`). **Tidak ada endpoint server untuk progress, dan memang tidak perlu.**

Yang bisa dirapikan:

- Progress per run: generasi saat ini / maksimum, fitness terbaik, dan estimasi sisa waktu.
- Tombol batal per batch.
- Status akhir per run: selesai, dibatalkan, atau gagal.

### Perbandingan multi-run

- **Desktop**: `RunView.vue` sudah punya tabel "Perbandingan run" per batch. Rapikan tampilannya: urutkan berdasarkan fitness, tandai run tanpa pelanggaran HC, dan tandai run terbaik.
- **Web** (halaman Review, §1): tambahkan mode perbandingan. Run dalam satu batch diambil dengan `GET /scheduling-runs?batch_id=<uuid>`.
  - Tabel berdampingan: operator seleksi, Cr, Mr, seed, `best_fitness`, `fitness_percent`, `best_generation`, `final_generation`, `hc_violations`, `sc_unmet`, `unassigned_rooms`.
  - Sorot nilai terbaik per kolom.
  - Opsional: grafik `fitness_history` beberapa run sekaligus. Data diambil dari `GET /scheduling-runs/{id}`.
  - Dari tabel ini, admin bisa langsung memilih run lalu menekan "Tetapkan sebagai Jadwal Aktif".

### Kriteria selesai

- [ ] Web: membuka batch FSTI menampilkan 3 run berdampingan, dan run terbaik ditandai.
- [ ] Desktop: progress tiap run bergerak saat GA berjalan, dan tombol batal berfungsi.

---

## 5. Enhance halaman Import Data Mata Kuliah

**Halaman**: `resources/js/Pages/Prodi/Course/Import.vue` (route `prodi.courses.import`). MVP-nya sudah berfungsi dengan data asli.

Acuan lengkap: `docs/plans/web-import-mata-kuliah.md`, terutama §10.

### Endpoint (sudah ada)

| Method | Path | Kegunaan |
|---|---|---|
| GET | `/courses/import/template` | Unduh template (kosong / terisi data sekarang) |
| POST | `/courses/import` | `dry_run=true` mengembalikan preview + `plan_hash`. Commit dikirim dengan `plan_hash` itu. Data yang berubah di antaranya menghasilkan 409 `IMPORT_PLAN_CHANGED` |

### Yang harus dibuat (daftar enhancement dari plan §10)

1. Kartu filter per status (baru / berubah / sama / error) di atas tabel preview.
2. Sel error ditandai dengan garis merah, disertai tooltip berisi pesan.
3. Bagian "Tidak ada di file": mata kuliah yang ada di sistem tapi tidak ada di file.
4. Unduh laporan error dalam bentuk xlsx. Kalau butuh endpoint baru, diskusikan dengan backend dulu.
5. Upload file dengan drag & drop.
6. Pencarian di dropdown pencocokan dosen.

### Kriteria selesai

- [ ] Keenam enhancement berfungsi untuk akun `admin.if`.
- [ ] Alur MVP (upload → preview → import) tetap berjalan seperti sebelumnya.

---

## 6. Alokasi Ruangan (Admin Fakultas)

**Halaman**: `resources/js/Pages/Fakultas/Room/Index.vue` (route `fakultas.rooms.index`). Tabel ruangan di atas sudah memakai data asli. Dua tabel di bawahnya masih memakai `mocks/room.ts`.

### Perubahan isi halaman (sudah disepakati)

| Bagian di mock | Diganti dengan |
|---|---|
| "Bentrok Ruang Antar Prodi" | **"Kelas Melebihi Kapasitas Ruang"**. Bentrok ruang di jadwal aktif tidak mungkin terjadi, karena sudah ditolak saat upload, activate, dan override |
| "Slot Belum Terpetakan" | **"Kelas Tanpa Ruang"** |
| Kolom "Saran" berisi teks | Tombol **"Pilih ruang"** yang membuka modal override dari §2 |

### Endpoint

| Tabel | Sumber |
|---|---|
| Kelas Melebihi Kapasitas Ruang | `GET /schedules?faculty_id=<milik sendiri>&over_capacity=1`. Tampilkan `room.capacity` vs `course.class_capacity` |
| Kelas Tanpa Ruang | `GET /schedules?faculty_id=<milik sendiri>&unassigned=1` |
| Isi modal "Pilih ruang" | `GET /schedule-details/{id}/options`, lalu `PATCH /schedule-details/{id}` |

Untuk "Pilih ruang", buka modal dengan slot saat ini sudah terpilih. Ruang yang muncul diambil dari `rooms` milik slot `current.time_slot_id`. Kalau daftarnya kosong, tampilkan "Tidak ada ruang kosong di jam ini, pilih slot lain" dan biarkan admin memilih slot lain di modal yang sama.

### Kriteria selesai

- [ ] Kedua tabel memakai data asli, dan menampilkan "Tidak ada" kalau kosong.
- [ ] Memberi ruang ke kelas tanpa ruang lewat modal berhasil, dan baris itu hilang dari tabel setelah dimuat ulang.
- [ ] Tidak ada import dari `mocks/room.ts` lagi.

---

## 7. Dashboard Fakultas dan Prodi

**Halaman**: `resources/js/Pages/Fakultas/Dashboard.vue` dan `resources/js/Pages/Prodi/Dashboard.vue`. Keduanya sekarang memakai `mocks/dashboard.ts`.

Data dashboard **tidak diambil lewat API**. Data dikirim sebagai props Inertia bernama `dashboard` saat halaman dibuka (sama seperti dashboard superadmin):

```ts
import type { FacultyDashboard } from '@/types/models';
defineProps<{ dashboard: FacultyDashboard }>();
```

Untuk refresh saat data berubah: `useResourceChanges` lalu `router.reload({ only: ['dashboard'] })`.

### Dashboard Fakultas (`FacultyDashboard`)

| Kartu di mock | Field |
|---|---|
| Periode | `period` (format `2026/2027-GANJIL`, ubah ke "Semester Ganjil 2026/2027" di FE) |
| Kelengkapan constraint prodi | `constraints.accepted` / `constraints.total` (ada juga `submitted` dan `draft`) |
| Status run GA | `active_run`: `fitness_percent`, `schedule_details_count`, `final_generation`, `hc_violations`, `published_at`. Bernilai `null` kalau belum ada jadwal aktif, jadi tampilkan "Belum ada jadwal aktif" |
| Ruangan bermasalah | `rooms.unassigned_count`, `rooms.over_capacity_count`, dan maksimal 5 contoh di `rooms.issues` (`type` = `unassigned` / `over_capacity`). Tombol "Kelola" mengarah ke halaman Ruangan (§6) |
| Bentrok antar prodi | **Hapus kartu ini** (alasan sama dengan §6) |

### Dashboard Prodi (`StudyProgramDashboard`)

| Kartu di mock | Field |
|---|---|
| Kelengkapan constraint (%) | `constraint.completeness_percent` |
| Checklist | Buat dari `constraint`: "Preferensi dosen: `lecturers_with_preferences_count`/`lecturers_count`", "MK lengkap dosen: `courses_count - incomplete_courses_count`/`courses_count`", dan status (`draft` / `submitted` / `accepted`) |
| Status jadwal | `schedule.status`: `none` = "Belum ada jadwal", `active` = "Sedang difinalisasi fakultas" (angka `null`, jangan ditampilkan), `published` = "Sudah dipublikasikan" + `published_at` |
| Total kelas, SKS, bentrok, tanpa ruang | `schedule.classes_count`, `sks_total`, `hc_violations`, `unassigned_rooms` (hanya saat `published`) |
| "SK Dekan No. ..." | **Hapus** (datanya tidak ada) |

### Kriteria selesai

- [ ] `admin.fsti`: kartu run GA terisi dari run aktif FSTI. Ruangan bermasalah sesuai halaman Ruangan.
- [ ] `admin.fpb`: kartu run GA terisi dan bertanda "belum dipublikasikan".
- [ ] `admin.if`: status jadwal "Sudah dipublikasikan" dengan angka terisi.
- [ ] Tidak ada import dari `mocks/dashboard.ts` lagi. Folder `resources/js/mocks/` boleh dihapus kalau sudah kosong.

---

## 8. Export Jadwal (Excel, PDF, .ics) + Halaman Periode Akademik

Backend sudah selesai. Rincian ada di `docs/plans/api-export-jadwal.md` dan `openapi.yaml` (`/schedules/export`, `/academic-periods`).

### Export di halaman jadwal (§2)

Endpoint: `GET /api/internal/schedules/export?format=xlsx|pdf|ics&<filter yang sama dengan /schedules>`. Tipe: `ScheduleExportParams`.

- Isi file selalu sama dengan yang tampil di layar untuk filter yang sama, termasuk scope role dan status publish.
- `classes[]` untuk mahasiswa yang memilih MK yang diambil: `"12"` = semua kelas MK 12, `"12:1"` = kelas A MK 12. axios mengirim array sebagai `classes[]=…`. Filter ini juga berlaku di `GET /schedules`, jadi pratinjau dan file sama.
- Nama file dari header `Content-Disposition`, misalnya `jadwal-if-smt3-a-2026-2027-ganjil.pdf`.

Error (body JSON, tetapi karena request memakai `responseType: 'blob'`, blob perlu di-parse dulu: `JSON.parse(await error.response.data.text())`):
- `409 INVALID_STATE`: tidak ada jadwal yang cocok, atau periode belum punya tanggal kuliah (khusus .ics). Tampilkan `message`.
- `503 PDF_UNAVAILABLE`: service PDF mati. Tampilkan `message`, sarankan coba Excel.
- `422`: format atau `classes[]` salah.

Yang harus dibuat:
1. Tombol **Unduh** di toolbar halaman jadwal, berupa dropdown dengan tiga pilihan: Excel, PDF, Kalender (.ics). Kirim filter yang sedang aktif.
2. Unduh lewat `api.get('/schedules/export', { params, responseType: 'blob' })`, lalu buat `URL.createObjectURL` dan klik `<a download>`. Jangan pakai `window.location`, karena error akan tampil sebagai JSON mentah.
3. Status loading di tombol (PDF butuh 1–3 detik).
4. **Mahasiswa**: mode "Jadwal pribadi". Checklist MK + kelas dari jadwal prodi/semester yang sedang tampil. Pilihan disimpan di `localStorage` dan dikirim sebagai `classes[]`, baik ke `/schedules` maupun ke export.
5. **Dosen**: filter default `lecturer_id` milik sendiri (sudah ada di §2), jadi export otomatis berisi jadwal mengajarnya.
6. Teks bantuan di samping pilihan .ics: "Impor ke Google Calendar atau Outlook. Mengimpor ulang memperbarui jadwal, tidak menduplikasi."

### Halaman Periode Akademik (superadmin)

Tanggal kuliah per periode dipakai untuk export .ics. Endpoint CRUD `/academic-periods`, tipe `AcademicPeriod`. Polanya sama dengan halaman Slot Waktu (`pages/Superadmin/Slot/Index.vue`).

1. Route Inertia baru, misalnya `/superadmin/academic-periods` → `Superadmin/AcademicPeriod/Index`, plus menu di sidebar superadmin.
2. Tabel: label (`Ganjil 2026/2027`), tanggal mulai, tanggal selesai, badge "Periode berjalan" kalau `is_current`.
3. Form tambah/ubah: kode periode (`YYYY/YYYY-GANJIL|GENAP`, bisa berupa dua input tahun + pilihan Ganjil/Genap), tanggal mulai, tanggal selesai.
4. Tombol ubah/hapus mengikuti `can_update` / `can_delete`. Kalau dihapus atau kodenya diubah saat sudah dipakai run, server mengembalikan `409 RESOURCE_IN_USE`. Tampilkan `message`.
5. Role lain hanya bisa membaca. Tidak perlu halaman untuk mereka.

### Kriteria selesai

- [ ] `admin.if` mengunduh Excel prodi IF: ada sheet "Matriks" dan "Daftar".
- [ ] `admin.fpb` mengunduh PDF FPB: ada watermark "DRAF".
- [ ] `dosen.if01` mengunduh .ics. File bisa diimpor ke Google Calendar dan berulang tiap minggu sampai akhir semester.
- [ ] `mahasiswa.if` memilih 3 MK, lalu file hanya berisi 3 MK itu.
- [ ] Gotenberg dimatikan (`docker compose stop gotenberg`), lalu PDF menampilkan pesan "Layanan PDF sedang tidak tersedia".
- [ ] Superadmin mengubah tanggal selesai periode, lalu .ics berikutnya memakai tanggal baru.

---

## 9. Verifikasi sebelum menyerahkan

```bash
cd apps/web
./vendor/bin/sail npm run build      # sudah termasuk vue-tsc
./vendor/bin/sail artisan test        # memastikan backend tidak ikut rusak

cd ../desktop
npm run build                        # sudah termasuk vue-tsc --noEmit
```

Cek manual dengan akun-akun demo di §0, mengikuti kriteria selesai di tiap bagian.
