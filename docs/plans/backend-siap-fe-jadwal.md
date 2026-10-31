# Plan — Backend Siap Dipakai FE (Override Options, Filter, Dashboard)

Tujuan: semua halaman web yang masih menampilkan "Data contoh" punya sumber data asli, sehingga frontend bisa dikerjakan tanpa menunggu backend lagi.

Halaman yang terdampak:

| Halaman | Sumber data sekarang | Setelah plan ini |
|---|---|---|
| `Fakultas/Ga/Index.vue` | `mocks/ga.ts` | API sudah siap (tidak berubah, hanya filter `is_active`) |
| `Prodi/ScheduleMatrix/Index.vue` | `mocks/schedule.ts` | API sudah siap, ditambah `class_capacity` |
| `Fakultas/Room/Index.vue` | `/rooms` + `mocks/room.ts` | Ditambah filter `unassigned` / `over_capacity` dan endpoint options |
| `Fakultas/Dashboard.vue` | `mocks/dashboard.ts` | Props Inertia `dashboard` |
| `Prodi/Dashboard.vue` | `mocks/dashboard.ts` | Props Inertia `dashboard` |

Melanjutkan pekerjaan jadwal aktif dan override (`ScheduleQueryService`, `ScheduleOverrideService`).

---

## 1. Keputusan yang perlu dijawab

| # | Pertanyaan | Saran |
|---|---|---|
| Q1 | Bagian "Bentrok Ruang Antar Prodi" (halaman Ruangan dan dashboard fakultas) selalu kosong, karena bentrok ruang sudah ditolak saat upload, activate, dan override. Diganti apa? | Ganti dengan **"Kelas melebihi kapasitas ruang"** (scope `overCapacity` sudah ada). |
| Q2 | "SK Dekan No. ..." di dashboard prodi tidak ada datanya. | **Hapus dari UI.** Kalau nanti perlu, tambahkan kolom `decree_number` yang diisi saat publish. |
| Q3 | Endpoint options menampilkan slot yang bentrok juga (beserta alasannya), atau hanya slot yang bebas? | **Tampilkan semua slot dengan alasan.** Admin bisa melihat kenapa sebuah slot tidak bisa dipakai. |
| Q4 | Kolom "Saran" di halaman Ruangan (pindah ke ruang X). | FE memanggil options untuk baris yang diklik, lalu menampilkan ruang bebas di slot yang sama. Tidak ada endpoint saran terpisah. |

---

## 2. Endpoint `GET /schedule-details/{scheduleDetail}/options`

Untuk modal override. FE tidak bisa menghitung ketersediaan sendiri, karena jadwal fakultas lain yang belum dipublish tidak terlihat oleh admin fakultas. Padahal ruang shared dan dosen lintas fakultas ikut dihitung.

- Otorisasi: policy `override` yang sudah ada (admin_fakultas fakultasnya sendiri, superadmin).
- 409 `INVALID_STATE` kalau run tidak aktif (sama seperti PATCH).

Response:

```json
{
  "data": {
    "current": { "time_slot_id": 1, "room_id": 5 },
    "time_slots": [
      {
        "id": 6, "day": 2, "session": 2, "start_time": "10:20", "end_time": "12:00",
        "available": true,
        "reasons": [],
        "rooms": [ { "id": 9, "code": "E101", "name": null, "capacity": 60 } ]
      },
      {
        "id": 7, "day": 2, "session": 3, "start_time": "13:00", "end_time": "15:30",
        "available": false,
        "reasons": ["Dosen sudah mengajar Struktur Data kelas B pada slot ini."],
        "rooms": []
      }
    ]
  }
}
```

- `available` = slot itu lolos HC2, HC3, HC4, dan blok TPB untuk dosen/prodi. Ketersediaan ruang dihitung terpisah.
- `rooms` = ruang yang dipakai fakultas ini atau shared, muat kapasitas kelas, kosong di slot itu (di semua jadwal aktif periode), dan tidak dipakai TPB. Urut kapasitas naik, jadi yang paling pas muncul pertama.
- Ruang yang sedang dipakai baris ini sendiri tetap dihitung bebas.

### Refactor: satu pemeriksa untuk options dan PATCH

`ScheduleOverrideService::hardConstraintErrors()` dipindah ke kelas baru `ScheduleSlotChecker`:

- Saat dibuat untuk satu baris, kelas ini memuat sekali semua baris jadwal aktif di periode itu, lalu menyusun peta `slot → dosen`, `slot → ruang`, `slot → (prodi, semester, kelas)`, dan `slot → jumlah kelas prodi`. Blok TPB juga dimuat sekali.
- Method `slotErrors(slotId)` dan `roomErrors(slotId, roomId)` memeriksa di memori, tanpa query tambahan.
- PATCH dan options memakai kelas yang sama, jadi aturan dan pesannya selalu konsisten.

Biayanya satu query untuk beberapa ribu baris per periode, lalu 20 slot × ±60 ruang diperiksa di memori.

---

## 3. Tambahan kecil di API yang sudah ada

| Perubahan | Lokasi |
|---|---|
| `course.class_capacity` di setiap baris jadwal | `ScheduleDetailTransformer` (ikut ke `/schedules` dan `/scheduling-runs/{id}/schedule-details`) |
| Filter `is_active` (boolean) | `GET /scheduling-runs` (`ListSchedulingRunRequest`, `SchedulingRunQueryService::getRuns`) |
| Filter `unassigned=1` (tanpa ruang) | `GET /schedules` (`ScheduleQueryService::details`) |
| Filter `over_capacity=1` (ruang lebih kecil dari kapasitas kelas) | `GET /schedules`, memakai scope `overCapacity` |

---

## 4. Props dashboard (Inertia)

Mengikuti pola dashboard superadmin: `DashboardController` mengirim props `dashboard` sekali saat halaman dimuat. Tidak ada endpoint API baru. Halaman tetap memakai `useResourceChanges` + `router.reload({ only: ['dashboard'] })` untuk refresh.

### `Fakultas/Dashboard` — `FacultyDashboardService::get(User)`

```json
{
  "period": "2026/2027-GANJIL",
  "constraints": { "total": 6, "accepted": 5, "submitted": 1, "draft": 0 },
  "active_run": {
    "id": 1, "published_at": "…", "best_fitness": 497.0, "fitness_percent": 99.4,
    "final_generation": 637, "schedule_details_count": 166, "hc_violations": 0, "sc_unmet": 12
  },
  "rooms": {
    "unassigned_count": 3,
    "over_capacity_count": 1,
    "issues": [
      { "schedule_detail_id": 42, "type": "unassigned", "course": "Basis Data", "class_label": "A", "day": 1, "session": 1, "room": null },
      { "schedule_detail_id": 51, "type": "over_capacity", "course": "Kalkulus", "class_label": "B", "day": 2, "session": 3, "room": "B207" }
    ]
  }
}
```

- `active_run` bernilai `null` kalau belum ada jadwal aktif.
- `issues` dibatasi 5 baris. Daftar lengkap ada di halaman Ruangan (filter `unassigned` / `over_capacity`).
- Bentrok antar prodi tidak ada (Q1).

### `Prodi/Dashboard` — `StudyProgramDashboardService::get(User)`

```json
{
  "period": "2026/2027-GANJIL",
  "constraint": {
    "status": "submitted",
    "lecturers_count": 13, "lecturers_with_preferences_count": 10,
    "courses_count": 26, "incomplete_courses_count": 0,
    "completeness_percent": 92
  },
  "schedule": {
    "status": "published",
    "classes_count": 48, "sks_total": 112,
    "hc_violations": 0, "unassigned_rooms": 0,
    "published_at": "…"
  }
}
```

- Data constraint dipakai ulang dari `ConstraintSubmissionData`.
- `completeness_percent` = rata-rata dua rasio: dosen yang sudah punya preferensi, dan MK yang semua kelasnya sudah punya dosen.
- `schedule.status`: `none` (fakultas belum punya jadwal aktif), `active` (aktif, belum dipublish; angka tidak ditampilkan), atau `published` (angka dihitung dari baris prodi ini di run aktif).
- `classes_count` = jumlah (MK, kelas) yang unik. `sks_total` = jumlah SKS per (MK, kelas).
- "SK Dekan" tidak ada (Q2).

---

## 5. File

```
app/Services/Scheduling/ScheduleSlotChecker.php        baru, aturan HC di memori
app/Services/Scheduling/ScheduleOverrideService.php    pakai checker + method options()
app/Services/Scheduling/ScheduleDetailTransformer.php  + class_capacity
app/Services/Scheduling/ScheduleQueryService.php       + unassigned, over_capacity
app/Services/Scheduling/SchedulingRunQueryService.php  + is_active
app/Http/Requests/Scheduling/ListScheduleRequest.php   + 2 filter
app/Http/Requests/Scheduling/ListSchedulingRunRequest.php + is_active
app/Http/Controllers/Api/Internal/Scheduling/ScheduleController.php  + options()
routes/api.php                                         + GET schedule-details/{id}/options (internal + v1)
app/Services/Dashboard/FacultyDashboardService.php     baru
app/Services/Dashboard/StudyProgramDashboardService.php baru
app/Http/Controllers/Web/DashboardController.php       kirim props dashboard
resources/js/types/…                                   tipe TS props dashboard + ScheduleDetail + options
docs/api/openapi.yaml, docs/api/CONVENTIONS.md
docs/plans/frontend-handoff-jadwal.md                  tambah §Ruangan dan §Dashboard
```

Frontend halaman tidak disentuh. Tipe TypeScript ditambahkan supaya teman bisa langsung memakainya.

---

## 6. Test

`tests/Feature/Scheduling/ScheduleApiTest.php` (tambahan):
- Options: slot dengan dosen sibuk → `available: false` dan alasan terisi. Ruang terpakai fakultas lain tidak muncul. Ruang kecil tidak muncul. Ruang baris ini sendiri tetap muncul di slot saat ini.
- Options untuk run tidak aktif → 409. Options oleh admin fakultas lain → 403.
- PATCH tetap lulus semua test lama setelah refactor.
- Filter `unassigned`, `over_capacity`, `is_active`. `class_capacity` ada di response.

`tests/Feature/Web/DashboardTest.php` (baru):
- Admin fakultas: props `dashboard.active_run` terisi dari data demo, `rooms.issues` maksimal 5.
- Admin prodi: `schedule.status` = `published` / `active` / `none` sesuai kondisi. Angka hanya muncul saat `published`.
- Superadmin tetap menerima `stats` seperti sebelumnya.

Verifikasi akhir: `./vendor/bin/sail artisan test` penuh, `npm run build` (tipe TS), lint openapi.

---

## 7. Urutan kerja

1. `ScheduleSlotChecker` + refactor PATCH (test lama harus tetap hijau).
2. Endpoint options + test.
3. Tambahan filter dan `class_capacity` + test.
4. Dua service dashboard + controller + test.
5. Tipe TS, openapi, CONVENTIONS, handoff.
