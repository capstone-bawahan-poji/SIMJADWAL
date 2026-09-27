# SIMJADWAL — Web

Aplikasi web SIMJADWAL (sistem penjadwalan kuliah). Folder ini berisi backend Laravel dan frontend Vue yang dirender lewat Inertia. Backend ini juga menyediakan REST API (`/api/v1/*`) untuk aplikasi desktop di [`apps/desktop`](../desktop).

Dokumen ini menjelaskan cara menjalankan aplikasi di laptop sendiri, dari nol sampai bisa login.

## Daftar isi

1. [Stack yang dipakai](#stack-yang-dipakai)
2. [Konsep dulu: Docker dan Sail](#konsep-dulu-docker-dan-sail)
3. [Yang perlu diinstal](#yang-perlu-diinstal)
4. [Setup pertama kali](#setup-pertama-kali)
5. [Menjalankan sehari-hari](#menjalankan-sehari-hari)
6. [Akun login](#akun-login)
7. [Perintah yang sering dipakai](#perintah-yang-sering-dipakai)
8. [Menjalankan test](#menjalankan-test)
9. [Masalah yang sering muncul](#masalah-yang-sering-muncul)
10. [Struktur folder](#struktur-folder)

## Stack yang dipakai

| Bagian | Teknologi |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Vue 3 + TypeScript, Inertia.js, Tailwind CSS, Vite |
| Database | PostgreSQL 17 |
| Cache | Valkey (pengganti Redis, kompatibel) |
| Email (lokal) | Mailpit |
| Auth API | Laravel Sanctum (Bearer token untuk desktop, session untuk web) |
| Hak akses | spatie/laravel-permission |
| Lingkungan dev | Docker + Laravel Sail |

## Konsep dulu: Docker dan Sail

Baca bagian ini kalau belum pernah pakai Docker atau Sail. Kalau sudah paham, lompat ke [Yang perlu diinstal](#yang-perlu-diinstal).

### Kenapa pakai Docker?

Aplikasi ini butuh PHP 8.5, PostgreSQL 17, Valkey, Node.js, dan beberapa ekstensi PHP. Menginstal semuanya satu per satu di laptop itu repot, dan versinya sering beda antar anggota tim.

Docker menjalankan semua itu di dalam **container**. Container itu seperti komputer Linux kecil yang terisolasi dan berjalan di laptop kamu. Semua anggota tim memakai container yang sama, jadi versi PHP, Postgres, dan lainnya pasti sama.

Kamu **tidak perlu** menginstal PHP, Composer, PostgreSQL, atau Node.js di laptop. Cukup Docker.

### Container apa saja yang jalan?

Semuanya didefinisikan di [`compose.yaml`](compose.yaml). Ada 4 container:

| Container | Isinya | Port di laptop |
|---|---|---|
| `laravel.test` | PHP 8.5, Composer, Node.js, npm. Tempat aplikasi Laravel berjalan. | `80` (web), `5173` (Vite) |
| `pgsql` | Database PostgreSQL 17 | `5432` |
| `valkey` | Cache (Valkey) | `6379` |
| `mailpit` | Penangkap email palsu untuk dev | `1025` (SMTP), `8025` (dashboard) |

Folder `apps/web` di laptop kamu di-*mount* ke dalam container `laravel.test` di `/var/www/html`. Artinya, file yang kamu edit di VS Code langsung terlihat di dalam container. Kamu tetap coding seperti biasa di laptop.

Data database disimpan di Docker volume `sail-pgsql`. Data ini tetap ada walaupun container dimatikan.

### Lalu Sail itu apa?

[Laravel Sail](https://laravel.com/docs/sail) adalah script pembungkus untuk `docker compose`. Lokasinya di `./vendor/bin/sail`.

Tanpa Sail, untuk menjalankan perintah artisan di dalam container kamu harus mengetik:

```bash
docker compose exec laravel.test php artisan migrate
```

Dengan Sail cukup:

```bash
./vendor/bin/sail artisan migrate
```

Aturan pentingnya: **semua perintah `php`, `artisan`, `composer`, dan `npm` dijalankan lewat Sail**, bukan langsung di terminal laptop.

| Jangan | Pakai ini |
|---|---|
| `php artisan migrate` | `sail artisan migrate` |
| `composer install` | `sail composer install` |
| `npm install` | `sail npm install` |
| `npm run dev` | `sail npm run dev` |

Alasannya: `.env` memakai `DB_HOST=pgsql`. Nama host `pgsql` hanya dikenal di dalam jaringan Docker. Kalau kamu menjalankan `php artisan migrate` langsung di laptop, koneksi ke database akan gagal.

### Biar tidak capek mengetik `./vendor/bin/sail`

Buat alias di shell. Tambahkan baris ini ke `~/.zshrc` (macOS) atau `~/.bashrc` (Linux/WSL):

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

Lalu buka ulang terminal. Setelah itu cukup ketik `sail ...`. Contoh di dokumen ini selanjutnya memakai alias `sail`.

## Yang perlu diinstal

Hanya dua:

1. **Git**
2. **Docker**
   - **macOS:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) atau [OrbStack](https://orbstack.dev/) (lebih ringan).
   - **Windows:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) dengan backend **WSL 2**, plus distro Ubuntu dari Microsoft Store. Lihat catatan Windows di bawah.
   - **Linux:** [Docker Engine](https://docs.docker.com/engine/install/) + plugin Docker Compose. Tambahkan user ke grup `docker` supaya tidak perlu `sudo`.

Cek Docker sudah jalan:

```bash
docker --version
docker compose version
docker ps
```

Kalau `docker ps` error seperti `Cannot connect to the Docker daemon`, berarti Docker Desktop/OrbStack belum dibuka. Buka aplikasinya dulu dan tunggu sampai statusnya running.

### Catatan untuk pengguna Windows

Sail hanya berjalan di lingkungan Linux, jadi di Windows semuanya dikerjakan di dalam **WSL 2**:

1. Instal WSL: buka PowerShell sebagai Administrator, jalankan `wsl --install`, lalu restart.
2. Di Docker Desktop, buka **Settings → Resources → WSL Integration**, aktifkan untuk distro Ubuntu.
3. Buka terminal **Ubuntu** (bukan PowerShell atau CMD). Semua perintah di README ini dijalankan di sana.
4. Clone repo di dalam filesystem Linux, misalnya `~/code/SIMJADWAL`. **Jangan** clone di `/mnt/c/...`, karena aksesnya sangat lambat.
5. Buka project di VS Code dari terminal Ubuntu dengan `code .` (butuh ekstensi **WSL** di VS Code).

## Setup pertama kali

Langkah ini cukup dilakukan sekali. Jalankan berurutan.

### 1. Clone repo dan masuk ke folder web

```bash
git clone https://github.com/capstone-bawahan-poji/SIMJADWAL.git
cd SIMJADWAL/apps/web
```

Semua perintah selanjutnya dijalankan dari folder `apps/web`.

### 2. Salin file environment

```bash
cp .env.example .env
```

Buka `.env`, lalu isi password untuk akun superadmin:

```dotenv
SUPERADMIN_PASSWORD=isi-password-bebas
```

Nilai ini wajib diisi. Kalau kosong, proses seeding di langkah 6 akan gagal dengan pesan `SUPERADMIN_PASSWORD is empty`.

Nilai lain di `.env.example` sudah disesuaikan untuk Sail. Tidak perlu diubah.

### 3. Instal dependency PHP (tanpa PHP di laptop)

Script Sail ada di folder `vendor`, dan folder itu belum ada setelah clone. Jadi instal dulu dependency Composer memakai container sementara:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

Perintah ini mengunduh image kecil berisi PHP + Composer, menjalankan `composer install`, lalu menghapus container-nya. Setelah selesai, folder `vendor/` terisi dan `./vendor/bin/sail` sudah bisa dipakai.

Kalau di laptop kamu sudah ada PHP dan Composer, `composer install` biasa juga boleh.

### 4. Nyalakan container

```bash
sail up -d
```

- `-d` artinya jalan di background, jadi terminal tetap bisa dipakai.
- Pertama kali akan lama (bisa 5–15 menit), karena Docker membangun image PHP dan mengunduh image Postgres, Valkey, dan Mailpit. Berikutnya hanya beberapa detik.

Cek semua container sudah jalan:

```bash
sail ps
```

Keempat service (`laravel.test`, `pgsql`, `valkey`, `mailpit`) harus berstatus `running` atau `healthy`.

### 5. Generate application key

```bash
sail artisan key:generate
```

Perintah ini mengisi `APP_KEY` di `.env`. Tanpa key ini Laravel menolak berjalan.

### 6. Buat tabel dan isi data awal

```bash
sail artisan migrate --seed
```

- `migrate` membuat semua tabel di database `simjadwal`.
- `--seed` mengisi data referensi: permission, role, fakultas dan prodi, slot waktu, ruangan, jenis constraint, dan akun superadmin. Di luar production, seeder juga membuat akun demo untuk setiap role.

Data master demo (dosen, mata kuliah, pengampu) dibaca dari file CSV di `database/seeders/data/<slug-prodi>/`. Folder ini kosong di repo. Kalau tidak ada CSV, seeder hanya menampilkan peringatan dan melewati bagian itu. Aplikasi tetap bisa jalan. Minta file CSV ke anggota tim kalau butuh data contoh.

### 7. Instal dependency frontend

```bash
sail npm install
```

Jalankan lewat `sail`, **bukan** `npm install` di laptop. Beberapa paket (Vite, esbuild, Rollup) punya binary yang berbeda untuk macOS/Windows dan Linux. Container berjalan di Linux, jadi `node_modules` harus diinstal dari dalam container.

### 8. Jalankan Vite

```bash
sail npm run dev
```

Biarkan terminal ini tetap terbuka. Vite meng-compile file Vue/TypeScript dan me-reload browser otomatis setiap kali kamu menyimpan file.

### 9. Buka aplikasinya

| Alamat | Isinya |
|---|---|
| http://localhost | Aplikasi web |
| http://localhost:8025 | Mailpit, untuk melihat email yang dikirim aplikasi |
| http://localhost/up | Health check, harus menampilkan halaman "Application up" |

Login memakai salah satu akun di bagian [Akun login](#akun-login). Setup selesai.

## Menjalankan sehari-hari

Setelah setup pertama selesai, alur hariannya cukup:

```bash
cd SIMJADWAL/apps/web

# 1. Nyalakan container
sail up -d

# 2. Jalankan Vite (biarkan terminal ini terbuka)
sail npm run dev
```

Buka http://localhost.

Selesai kerja, matikan container supaya tidak memakan RAM:

```bash
sail stop
```

Setelah `git pull`, jalankan ini kalau ada perubahan dependency atau migration:

```bash
sail composer install
sail npm install
sail artisan migrate
```

## Akun login

Akun ini dibuat oleh seeder.

| Role | Email | Password |
|---|---|---|
| Super Admin | `superadmin@penjadwalan.test` | nilai `SUPERADMIN_PASSWORD` di `.env` |
| Admin Fakultas (FSTI) | `admin.fsti@penjadwalan.test` | `password` |
| Admin Prodi (Informatika) | `admin.if@penjadwalan.test` | `password` |
| Mahasiswa | `mahasiswa.if@penjadwalan.test` | `password` |
| Dosen | `dosen.ccu@penjadwalan.test` | `password` |

Catatan:

- Password akun demo diambil dari `SEED_DEFAULT_PASSWORD` di `.env` (default `password`).
- Akun dosen hanya dibuat kalau data dosen berkode `CCU` ada di CSV `database/seeders/data/informatika/lecturers.csv`.
- Tidak ada halaman registrasi. Akun baru dibuat oleh admin dari dalam aplikasi.

## Perintah yang sering dipakai

### Container

```bash
sail up -d          # nyalakan semua container di background
sail stop           # matikan container, data tetap aman
sail ps             # lihat status container
sail logs -f        # lihat log semua container (Ctrl+C untuk keluar)
sail shell          # masuk ke terminal bash di dalam container laravel.test
sail build --no-cache   # bangun ulang image (misalnya setelah compose.yaml berubah)
```

### Laravel

```bash
sail artisan migrate                 # jalankan migration baru
sail artisan migrate:fresh --seed    # hapus semua tabel, buat ulang, isi data awal
sail artisan db:seed                 # jalankan seeder saja
sail artisan route:list              # lihat semua route
sail artisan tinker                  # REPL PHP, untuk coba-coba query
sail artisan optimize:clear          # hapus semua cache (config, route, view)
sail artisan pail                    # lihat log aplikasi secara real-time
```

### Frontend

```bash
sail npm run dev     # Vite dev server dengan hot reload
sail npm run build   # type-check (vue-tsc) lalu build untuk production
```

### Format kode PHP

```bash
sail pint            # rapikan format kode PHP sesuai standar Laravel
```

### Akses database dari aplikasi GUI

Kamu bisa membuka database memakai TablePlus, DBeaver, pgAdmin, atau DataGrip dengan setting ini:

| Setting | Nilai |
|---|---|
| Host | `127.0.0.1` |
| Port | `5432` |
| Database | `simjadwal` |
| User | `sail` |
| Password | `password` |

Atau langsung dari terminal:

```bash
sail psql
```

## Menjalankan test

```bash
sail artisan test
```

Test memakai database terpisah bernama `testing`. Database ini dibuat otomatis oleh container Postgres saat pertama kali dinyalakan, jadi data di database `simjadwal` tidak ikut terhapus.

Menjalankan satu file atau satu test saja:

```bash
sail artisan test tests/Feature/Auth/AuthenticationTest.php
sail artisan test --filter=test_users_can_authenticate_using_the_login_screen
```

## Masalah yang sering muncul

### `Cannot connect to the Docker daemon`

Docker belum jalan. Buka Docker Desktop atau OrbStack, tunggu sampai statusnya running, lalu ulangi perintahnya.

### `./vendor/bin/sail: No such file or directory`

Folder `vendor` belum ada. Ulangi [langkah 3](#3-instal-dependency-php-tanpa-php-di-laptop).

### `Bind for 0.0.0.0:80 failed: port is already allocated`

Port sudah dipakai aplikasi lain di laptop (misalnya Apache, Nginx, XAMPP, Laragon, atau PostgreSQL lokal). Ada dua pilihan:

- Matikan aplikasi yang memakai port itu.
- Atau ganti port di `.env`, lalu jalankan `sail up -d` lagi:

```dotenv
APP_PORT=8000              # web jadi http://localhost:8000
APP_URL=http://localhost:8000
FORWARD_DB_PORT=54320      # kalau port 5432 bentrok dengan Postgres lokal
VITE_PORT=5174             # kalau port 5173 bentrok
```

### `Vite manifest not found at: .../public/build/manifest.json`

Vite belum jalan. Jalankan `sail npm run dev` di terminal terpisah dan biarkan terbuka. Kalau tidak mau menjalankan Vite terus-menerus, jalankan `sail npm run build` sekali. Hasilnya tidak auto-reload saat file diubah.

### Halaman tidak berubah setelah edit file Vue

Pastikan `sail npm run dev` masih berjalan dan tidak ada error di terminalnya. Coba hard refresh browser (`Cmd+Shift+R` atau `Ctrl+Shift+R`).

### `Cannot find module @rollup/rollup-linux-...` atau error esbuild saat `sail npm run dev`

`node_modules` diinstal dari laptop (macOS/Windows), bukan dari container Linux. Hapus lalu instal ulang lewat Sail:

```bash
rm -rf node_modules
sail npm install
```

### `SQLSTATE[08006] could not translate host name "pgsql"`

Kamu menjalankan `php artisan ...` langsung di laptop. Pakai `sail artisan ...`. Lihat penjelasan di [Lalu Sail itu apa?](#lalu-sail-itu-apa).

### `SUPERADMIN_PASSWORD is empty. Set it in .env before seeding.`

Isi `SUPERADMIN_PASSWORD` di `.env`, lalu jalankan `sail artisan db:seed`.

### `No application encryption key has been specified`

Jalankan `sail artisan key:generate`.

### Error permission di `storage/` atau `bootstrap/cache/` (Linux/WSL)

```bash
sail shell
chmod -R ug+rw storage bootstrap/cache
exit
```

### Mau reset database dari nol

Hapus semua tabel lalu buat ulang dengan data awal:

```bash
sail artisan migrate:fresh --seed
```

**Peringatan:** perintah di bawah ini menghapus volume Docker, termasuk **seluruh isi database** (`simjadwal` dan `testing`). Data tidak bisa dikembalikan. Pakai hanya kalau database benar-benar rusak.

```bash
sail down -v
sail up -d
sail artisan migrate --seed
```

## Struktur folder

```
apps/web/
├── app/
│   ├── Http/Controllers/     # controller web (Inertia) dan Api/
│   ├── Models/               # model Eloquent: MasterData/, Scheduling/, Constraint/
│   ├── Services/             # logika bisnis
│   ├── Policies/             # otorisasi per model
│   ├── Data/                 # DTO (spatie/laravel-data)
│   └── Enums/                # enum role, status, dan lainnya
├── database/
│   ├── migrations/           # skema tabel
│   ├── factories/            # data palsu untuk test
│   └── seeders/              # data awal (Reference/, Demo/, data/*.csv)
├── resources/js/
│   ├── Pages/                # halaman Vue, dipanggil dari controller via Inertia::render()
│   ├── Components/           # komponen Vue yang dipakai ulang
│   ├── Layouts/              # layout halaman
│   └── types/                # tipe TypeScript
├── routes/
│   ├── web.php               # route halaman web
│   ├── api.php               # REST API: /api/v1 (token) dan /api/internal (session)
│   └── auth.php              # login, logout, reset password
├── tests/                    # PHPUnit: Feature/ dan Unit/
├── compose.yaml              # definisi container Docker untuk Sail
└── .env.example              # template environment
```

Dokumentasi lain ada di folder [`docs/`](../../docs) di root repo: arsitektur, ERD, spesifikasi API (OpenAPI), use case, sequence diagram, dan activity diagram.
