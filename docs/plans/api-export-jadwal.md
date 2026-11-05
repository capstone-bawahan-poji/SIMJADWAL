# Plan — API Export Jadwal (Excel, PDF, .ics)

Status: **selesai** (backend). FE: `frontend-handoff-jadwal.md` §8.

Tujuan: pengguna mengunduh jadwal resmi (run aktif yang terlihat olehnya) dalam tiga format. Isi file selalu sama dengan yang tampil di halaman jadwal, karena dibaca lewat `ScheduleQueryService` yang sama.

Acuan:
- Use case `docs/use-case/Modul-7.png`: Koor Prodi, Dosen, dan Mahasiswa bisa export ke Excel, PDF, dan .ics jadwal pribadi.
- Sequence diagram `docs/sequence/modul7/7.1–7.3`:
  - 7.1 Mahasiswa: pilih mata kuliah yang diambil → tampilkan jadwal pribadi → pilih format → unduh.
  - 7.2 Dosen: pilih format → sistem mengambil jadwal berdasarkan data pengguna → unduh.
  - 7.3 Koor Prodi: pilih Excel atau PDF → jadwal aktif prodi → unduh.
  - Semua: kalau generate gagal, tampilkan pesan error.
- DATABASE.md §2 aturan 12: mahasiswa tidak punya KRS, jadwal pribadi = filter prodi + semester + kelas dari UI.

---

## 1. Kondisi sekarang

- `ScheduleQueryService::visibleRuns()` + `details()` sudah menangani scope per role, status publish, dan semua filter. Export cukup memakai ulang.
- `maatwebsite/excel` (PhpSpreadsheet) sudah terpasang. Import mata kuliah memakai PhpSpreadsheet langsung, tanpa class Export maatwebsite.
- Belum ada library PDF dan library iCalendar. Diputuskan: Gotenberg (PDF) dan `spatie/icalendar-generator` (.ics).
- `time_slots` punya `day`, `start_time`, `end_time`. Tanggal mulai dan akhir semester **tidak ada** di DB, padahal .ics butuh tanggal untuk event berulang.

---

## 2. Keputusan yang perlu dijawab

| # | Pertanyaan | Saran |
|---|---|---|
| Q1 | Tanggal semester untuk .ics (event mingguan butuh tanggal mulai dan akhir). | **CRUD tabel `academic_periods`** (diputuskan): `code`, `start_date`, `end_date`, endpoint `/academic-periods`, tulis hanya superadmin. Periode tanpa baris: .ics ditolak 409. Seeder mengisi tanggal contoh 2026/2027 Ganjil dan Genap. |
| Q2 | Library PDF. | **Gotenberg** (diputuskan). Service Docker `gotenberg/gotenberg:8` di compose. Laravel mengirim HTML Blade ke `POST /forms/chromium/convert/html` lewat `Http` client bawaan, jadi tidak perlu paket PHP. URL lewat `GOTENBERG_URL`. Kalau Gotenberg mati: 503 `PDF_UNAVAILABLE`. |
| Q3 | Library .ics. | **`spatie/icalendar-generator`** (diputuskan). Menangani escape, lipat baris, `VTIMEZONE`, dan `RRULE`. |
| Q4 | Cara mahasiswa memilih "mata kuliah yang diambil". | **Paling fleksibel** (diputuskan): `classes[]=12` (semua kelas MK 12) atau `classes[]=12:1` (kelas A), bisa digabung dengan semua filter biasa. Berlaku juga di `GET /schedules`, jadi pratinjau = file. |
| Q5 | Sinkron atau lewat queue? | **Sinkron** (diputuskan) (langsung unduh). Data satu prodi/dosen hanya ratusan baris, cukup cepat. Queue dan notifikasi Reverb baru dipakai kalau nanti ada export satu institut. |
| Q6 | Layout PDF dan Excel. | **Matriks hari × sesi** (seperti halaman jadwal), ditambah sheet/halaman kedua berisi **daftar** (MK, kelas, dosen, hari, jam, ruang). Excel: sheet "Matriks" + sheet "Daftar". PDF: A4 landscape, matriks dulu, lalu daftar. |
| Q7 | Siapa yang boleh export dan format apa? | (diputuskan) Semua role dengan `scheduling:schedule:view`, semua format (sesuai use case). Scope tetap mengikuti `visibleRuns()`, jadi mahasiswa tidak bisa mengunduh jadwal yang belum dipublish. |

---

## 3. Endpoint

```
GET /schedules/export?format=xlsx|pdf|ics&<filter /schedules>&classes[]=12:1&classes[]=15:2
```

- Ada di internal (web, session) dan v1 (token).
- Filter sama dengan `GET /schedules`: `period`, `faculty_id`, `study_program_id`, `semester`, `class_number`, `course_id`, `lecturer_id`, `room_id`, `day`, `unassigned`, `over_capacity`. Ditambah `classes[]`.
- Response `200` berupa file dengan `Content-Disposition: attachment`:

| format | Content-Type | Nama file contoh |
|---|---|---|
| `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `jadwal-IF-2026-2027-GANJIL.xlsx` |
| `pdf` | `application/pdf` | `jadwal-IF-2026-2027-GANJIL.pdf` |
| `ics` | `text/calendar; charset=utf-8` | `jadwal-dosen-informatika-01-2026-2027-GANJIL.ics` |

- Nama file disusun dari filter: prodi, nama dosen, atau "pribadi" untuk `classes[]`, lalu periode.
- Error (dalam JSON, sesuai konvensi):
  - `422 VALIDATION_FAILED`: format atau filter salah.
  - `409 INVALID_STATE`: tidak ada jadwal yang cocok ("Tidak ada jadwal untuk diekspor"), atau periode .ics tanpa baris di `academic_periods`.
  - `403 FORBIDDEN`: role tanpa `scheduling:schedule:view`.

Penggunaan per aktor (diatur FE, server hanya menerima filter):

| Aktor | Parameter khas |
|---|---|
| Koor Prodi | `study_program_id=<prodi>&format=xlsx\|pdf` |
| Dosen | `lecturer_id=<diri sendiri>&format=…` |
| Mahasiswa | `classes[]=…` (MK yang diambil) atau `study_program_id&semester&class_number`, `format=…` |
| Admin fakultas | Filter apa pun di fakultasnya |

---

## 4. Isi file

### Excel (PhpSpreadsheet langsung, seperti import)
- **Sheet "Matriks"**: baris = sesi (jam), kolom = hari Senin–Jumat (sumbu diambil dari `time_slots`). Sel berisi `KODE · Nama MK (A)`, dosen, dan ruang. Kalau ada lebih dari satu kelas di satu sel, dipisah baris baru.
- **Sheet "Daftar"**: Hari, Jam, Kode, Mata Kuliah, SKS, Kelas, Pertemuan, Dosen, Ruang, Prodi, Semester, Diubah manual. Header dibekukan dan ada autofilter.
- Judul di baris atas: "Jadwal Kuliah · <prodi/dosen> · <periode>", plus tanggal cetak. Kalau ada run yang belum dipublish (hanya admin yang bisa mengunduhnya), tambahkan tanda "DRAF — belum dipublikasikan".

### PDF (Gotenberg, Blade `resources/views/pdf/schedule.blade.php`)
- Blade dirender jadi HTML lengkap (CSS inline), lalu dikirim multipart ke Gotenberg (Chromium). Opsi: `paperWidth=11.7`, `paperHeight=8.27` (A4 landscape), margin kecil, `printBackground=true`.
- Halaman 1: matriks. Halaman berikutnya (`page-break-before`): daftar. CSS modern (grid, flex) jalan karena Chromium.
- Watermark "DRAF" untuk run yang belum dipublish.
- Timeout `Http` 30 detik. Gagal koneksi atau status bukan 200: `503 PDF_UNAVAILABLE` ("Layanan PDF sedang tidak tersedia").
- Test memakai `Http::fake()`, jadi tidak butuh container Gotenberg saat test.

### .ics (`spatie/icalendar-generator`)
- `Calendar::create('Jadwal …')->withoutAutoTimezoneComponents()` tidak dipakai; biarkan paket membuat `VTIMEZONE` Asia/Makassar (WITA, ITK Balikpapan) otomatis.
- Satu `Event` per baris jadwal (per pertemuan): `startsAt` = tanggal hari kuliah pertama pada/sesudah `start` periode + `start_time`, `endsAt` = tanggal yang sama + `end_time`.
- `->rrule(RRule::frequency(RecurrenceFrequency::weekly())->until(<end 23:59:59>))`.
- `name` = `KODE Nama MK (A)`. `address` = kode ruang (atau "Belum ada ruang"). `description` = dosen dan prodi.
- `uniqueIdentifier` = `schedule-detail-<id>-<period>@simjadwal`. UID ini stabil, sehingga impor ulang memperbarui event, tidak menduplikasi.

---

## 5. File

```
composer require spatie/icalendar-generator
compose.yaml                                            + service gotenberg (gotenberg/gotenberg:8)
config/services.php, .env.example                       + gotenberg.url (GOTENBERG_URL=http://gotenberg:3000)
academic_periods: migration, model, CRUD /academic-periods, AcademicPeriodSeeder — Q1
app/Http/Requests/Scheduling/ExportScheduleRequest.php  extends ListScheduleRequest + format, classes[]
app/Services/Scheduling/ScheduleQueryService.php        + filter classes[] (pasangan course_id:class_number)
app/Services/Scheduling/Export/ScheduleExportService.php    pilih exporter, nama file, error kosong
app/Services/Scheduling/Export/ScheduleMatrix.php           susun baris jadwal → grid sesi × hari (dipakai xlsx dan pdf)
app/Services/Scheduling/Export/XlsxScheduleExporter.php
app/Services/Scheduling/Export/PdfScheduleExporter.php
app/Services/Scheduling/Export/IcsScheduleExporter.php
resources/views/pdf/schedule.blade.php
app/Http/Controllers/Api/Internal/Scheduling/ScheduleController.php   + export()
routes/api.php                                          + GET schedules/export (sebelum route lain yang bentrok)
lang/id.json, docs/api/openapi.yaml, docs/api/CONVENTIONS.md
docs/plans/frontend-handoff-jadwal.md                   + tombol export di halaman jadwal
resources/js/types/models.ts                            + ExportFormat, ScheduleExportParams
```

Ketiga exporter memakai interface yang sama: `export(Collection $details, ExportContext $ctx): Response`. `ExportContext` berisi judul, periode, status draf, dan time slots.

---

## 6. Test (`tests/Feature/Scheduling/ScheduleExportTest.php`)

- xlsx: status 200, content-type benar, file bisa dibaca PhpSpreadsheet, sheet "Matriks" dan "Daftar" ada, jumlah baris daftar = jumlah baris jadwal, filter `lecturer_id` mengurangi isi.
- pdf: `Http::fake` Gotenberg, status 200, `application/pdf`, request ke Gotenberg berisi HTML dengan nama MK. Gotenberg gagal → 503 `PDF_UNAVAILABLE`.
- ics: berisi `BEGIN:VCALENDAR`, jumlah `BEGIN:VEVENT` = jumlah baris, ada `RRULE:FREQ=WEEKLY;UNTIL=`, `DTSTART` jatuh di hari yang benar, karakter koma di-escape.
- `classes[]` hanya mengembalikan kelas yang dipilih. Format `classes[]` yang salah → 422.
- Mahasiswa tidak bisa mengunduh jadwal yang belum dipublish (409 kosong, bukan file berisi data draf).
- Periode tanpa baris `academic_periods` → .ics 409. xlsx/pdf tetap bisa.
- Format tidak dikenal → 422.

Verifikasi akhir: test penuh, lint openapi, buka file hasil secara manual (xlsx di Excel/LibreOffice, ics di Google Calendar).

---

## 7. Urutan kerja

1. CRUD periode akademik, `ExportScheduleRequest`, filter `classes[]`, dan `ScheduleMatrix`.
2. Exporter xlsx + test.
3. Pasang `spatie/icalendar-generator`, exporter ics + test.
4. Service Gotenberg di compose, config, Blade, exporter pdf + test (`Http::fake`).
5. Controller, route, openapi, CONVENTIONS, tipe TS, handoff FE.
