# Arsitektur Sistem — Blueprint untuk Kloning

Dokumen ini merangkum desain arsitektur aplikasi ini agar dapat dibangun ulang dari nol di repository baru. Isinya adalah pola, aturan penamaan, dan alur data — bukan logika bisnis IPP/KPI. Setiap bagian ditulis sebagai aturan yang bisa langsung diterapkan.

---

## 1. Ringkasan Tumpukan Teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Vue 3 (`<script setup>` + TypeScript), Inertia.js 3 |
| Build | Vite 7, Tailwind CSS 4, `laravel-vite-plugin` |
| DTO | `spatie/laravel-data` |
| Type sharing PHP → TS | `spatie/laravel-typescript-transformer` |
| Route sharing PHP → TS | `laravel/wayfinder` |
| Otorisasi | `spatie/laravel-permission` + Laravel Policy |
| Query API | `spatie/laravel-query-builder` |
| State machine | `spatie/laravel-model-states` |
| UI kit | shadcn-vue / reka-ui + lucide + iconify |
| HTTP client (FE) | `ky` (bukan axios untuk panggilan internal) |
| i18n | `laravel-vue-i18n` + folder `lang/` |
| Breadcrumbs | `diglactic/laravel-breadcrumbs` + `robertboes/inertia-breadcrumbs` |
| Export | `maatwebsite/excel` |
| Dev environment | Laravel Sail (Docker Compose) |

Prinsip dasar: **satu sumber kebenaran di PHP**, lalu di-generate ke TypeScript. Frontend tidak pernah mendefinisikan ulang bentuk data, daftar enum, atau URL route.

---

## 2. Dua Pintu Masuk: Web (Inertia) dan API (JSON)

Ini adalah keputusan arsitektur paling menentukan dalam sistem ini.

```
routes/web.php  → App\Http\Controllers\Web\...   → Inertia::render(...)  → halaman Vue + props awal
routes/api.php  → App\Http\Controllers\Api\...   → JsonResponse          → data dinamis (tabel, filter, CRUD)
```

**Pembagian tanggung jawab:**

- **Web controller** menyajikan halaman. Tugasnya menyiapkan *props awal* halaman (data referensi yang jarang berubah, misalnya daftar fakultas, periode aktif, flag peran), membagikan flag tombol lewat `shareActions()`, lalu `Inertia::render('Folder/Halaman', [...])`. Web controller tidak menangani `store`/`update`/`destroy` untuk data utama. Pengecualiannya adalah alur yang memang butuh redirect atau session (lihat §2.2).
- **API controller** menangani semua interaksi setelah halaman dimuat: paginasi, filter, sort, create, update, delete, aksi workflow. Dipanggil dari Vue lewat `ky`.

**Konsekuensi yang harus dipertahankan saat kloning:**

1. Halaman tidak reload untuk operasi CRUD; hanya request JSON.
2. Validasi berat, otorisasi granular, dan transaksi ada di jalur API.
3. Route API internal memakai middleware `['web', 'auth']` (bukan token) dengan prefix `/api/internal`, sehingga otentikasi memakai session + cookie CSRF yang sama dengan halaman.
4. Token Sanctum hanya dipakai di `/api/v1/*`, yaitu API eksternal untuk aplikasi mobile dan desktop. Kontraknya ada di `docs/api/openapi.yaml`.

### 2.1 Skema folder controller

```
app/Http/Controllers/
├── Controller.php                     # base kosong milik Laravel
├── Api/
│   ├── ApiController.php              # base JSON: response(), noContent(), AuthorizesRequests
│   ├── Internal/<Domain>/             # /api/internal/*  → session + CSRF, konsumen: Vue milik aplikasi ini
│   │   └── Account/UserController.php
│   └── V1/<Domain>/                   # /api/v1/*        → token Sanctum, konsumen: mobile & desktop
│       ├── Account/AuthController.php # login token, logout, me, forgot/reset password
│       └── Account/UserController.php # extends Internal\Account\UserController
└── Web/
    ├── Controller.php                 # base Web: AuthorizesRequests
    ├── InertiaController.php          # base halaman: shareActions()
    ├── WelcomeController.php          # invokable, satu halaman
    ├── DashboardController.php
    ├── Account/ProfileController.php
    └── Auth/                          # alur session: login, logout, forgot/reset/confirm/update password
```

Aturan penempatan:

| Kebutuhan | Tempat |
|---|---|
| User membuka URL atau menu, butuh halaman | `Web/<Domain>` + `Inertia::render` |
| Data tabel, filter, pagination, chart yang di-refresh tanpa reload | `Api/Internal/<Domain>` |
| Create, update, delete dari modal, lalu tabel di-refresh | `Api/Internal/<Domain>` |
| Login, logout, reset password, update profil sendiri (butuh redirect atau session) | `Web/Auth`, `Web/Account` |
| Download file, link dari email | `Web` (dengan middleware `NonInertiaRoutes` atau `signed`) |
| Mobile app, desktop app, sistem luar | `Api/V1/<Domain>` |

Semua route di `routes/web.php` diarahkan ke controller, tidak ada closure. Halaman tunggal memakai controller invokable (`__invoke`).

### 2.2 Web controller

- Semua Web controller extend `Web\Controller`. Controller yang me-render halaman extend `Web\InertiaController`.
- `shareActions(['create' => Gate::allows('create', User::class), ...])` membagikan prop `actions` agar frontend bisa menampilkan atau menyembunyikan tombol.
- Alur form klasik (login, reset password, update profil) boleh menerima `POST`/`PATCH`/`PUT` di Web, karena hasilnya redirect + flash session, bukan JSON. Validasinya tetap lewat FormRequest dan logikanya tetap di service.

```php
class ProfileController extends InertiaController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', ['status' => session('status')]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $this->userService->updateProfile($request->user(), name: $v['name'], email: $v['email']);

        return to_route('profile.edit');
    }
}
```

### 2.3 `Api/Internal` vs `Api/V1`

Keduanya memanggil service yang sama. Controller hanya adapter yang mengubah hasil service menjadi format response yang dibutuhkan.

| | `Api/Internal` | `Api/V1` |
|---|---|---|
| Prefix & nama route | `/api/internal`, `api.internal.*` | `/api/v1`, `api.v1.*` |
| Middleware | `['web', 'auth']` | `['auth:sanctum', 'active']` |
| Konsumen | Frontend Vue aplikasi ini | Mobile, desktop (Tauri), sistem luar |
| Boleh berubah bebas? | Ya, mengikuti kebutuhan halaman | Tidak, terikat kontrak `openapi.yaml` |

Selama kontrak v1 sama dengan kebutuhan internal, controller V1 cukup berupa subclass kosong dari controller Internal. Saat v1 harus berbeda, override method-nya di kelas V1. Dengan begitu frontend bisa berubah tanpa merusak klien eksternal, dan tidak ada kode duplikat selama keduanya masih sama.

```php
namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;

class UserController extends InternalUserController {}
```

Controller khusus token (misalnya `V1\Account\AuthController` yang menerbitkan token Sanctum) hanya ada di V1. Logika yang dipakai kedua jalur, seperti reset password, berada di service bersama (`PasswordService`), bukan di service khusus token. Contohnya, `Web\Auth\NewPasswordController` dan `V1\Account\AuthController::resetPassword()` sama-sama memanggil `PasswordService::resetPassword()`, sehingga reset lewat web juga mencabut token API.

```php
// routes/api.php
Route::middleware(['web', 'auth'])->prefix('internal')->name('api.internal.')->group(function () {
    Route::apiResource('users', InternalUserController::class)->except(['destroy']);
    Route::patch('users/{user}/status', [InternalUserController::class, 'updateStatus'])->name('users.status');
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [V1AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        // forgot-password, reset-password, lalu logout & me di balik auth:sanctum
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::apiResource('users', V1UserController::class)->except(['destroy']);
        Route::patch('users/{user}/status', [V1UserController::class, 'updateStatus'])->name('users.status');
    });
});
```

Kelompok route Internal dan V1 ditulis terpisah, tidak berbagi satu closure. Tujuannya supaya penambahan endpoint di salah satu jalur tidak otomatis ikut muncul di jalur lain.

---

## 3. Struktur Folder `app/`

Pengelompokan **berdasarkan domain di dalam tipe kelas**, bukan berdasarkan domain di level atas. Artinya: `app/Services/Kpi/...`, bukan `app/Kpi/Services/...`.

```
app/
├── Console/Commands/
├── Contracts/<Domain>/            # interface untuk strategi yang bisa ditukar
├── Data/<Domain>/<Entity>/        # DTO (spatie/laravel-data)
├── Enums/<Domain>/                # enum domain, non-permission
├── Exceptions/Shared/
├── Exports/<Domain>/              # sheet Excel
├── Extensions/                    # perluasan framework (lihat §9)
│   ├── Data/{Casts,Traits,Transformers}
│   ├── Models/{Casts,Contracts,Traits}
│   ├── Helpers/
│   ├── Notifications/Channels/
│   ├── Permissions/
│   └── TypescriptTransformer/Writers/
├── Http/
│   ├── Controllers/Api/{ApiController.php, Internal/<Domain>/, V1/<Domain>/}   # lihat §2.1
│   ├── Controllers/Web/{Controller.php, InertiaController.php, Auth/, <Domain>/}
│   ├── Middleware/
│   ├── Queries/<Domain>/          # subclass spatie QueryBuilder
│   └── Requests/<Domain>/<Entity>/
├── Mail/<Domain>/ + Mail/Concerns/
├── Models/<Domain>/
├── Notifications/<Domain>/
├── Permissions/<Domain>/          # enum permission
├── Policies/<Domain>/
├── Providers/ + Providers/Shared/
├── Rules/<Domain>/
├── Services/<Domain>/ + Services/Concerns/
├── States/                        # state machine
├── Support/<Domain>/
└── View/Components/Shared/...     # komponen Blade (hanya untuk halaman non-Inertia & email)
```

Nama domain dipakai konsisten di semua folder. Bila domain bernama `Kpi`, maka ada `Data/Kpi`, `Models/Kpi`, `Services/Kpi`, `Policies/Kpi`, `Permissions/Kpi`, `Http/Requests/Kpi`, `Http/Controllers/Api/Internal/Kpi`. Konsistensi inilah yang mencegah kode berantakan: lokasi file bisa ditebak dari nama kelas.

---

## 4. Alur Request Lengkap

### 4.1 Jalur API (paling sering dipakai)

```
Route (api.php)
  → FormRequest              : validasi input mentah (snake_case)
  → Controller               : otorisasi permission → bangun FormData DTO → panggil Service
  → Service (readonly class) : query/transaksi DB → kembalikan Data DTO
  → Controller               : $this->response($dto, $status)
  → JSON ke Vue
```

Controller **tidak** berisi logika bisnis, **tidak** memanggil Eloquent selain lewat route-model binding, dan **tidak** membangun array respons manual.

Contoh bentuk baku controller API:

```php
class KpiDictionaryController extends ApiController
{
    public function __construct(
        private readonly Request $request,
        private readonly KpiDictionaryService $kpiDictionaryService,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorizeAny([KpiDictionaryPermissions::VIEW, KpiDictionaryPermissions::VIEW_AS_EMPLOYEE]);

        return $this->response($this->kpiDictionaryService->getKpiDictionaries($this->request));
    }

    public function store(StoreKpiDictionaryRequest $request): JsonResponse
    {
        $this->authorize(KpiDictionaryPermissions::CREATE);

        $v = $request->validated();
        $formData = new KpiDictionaryFormData(/* mapping eksplisit dari $v */);

        return $this->response($this->kpiDictionaryService->createKpiDictionary($formData), 201);
    }
}
```

Perhatikan: pemetaan `validated()` → `FormData` ditulis eksplisit, tidak `Data::from($request)`. Ini disengaja agar konversi tipe (`(int)`), nilai kondisional, dan default terlihat jelas di satu tempat.

### 4.2 Jalur Web (Inertia)

```
Route (web.php)
  → Web Controller : ambil data referensi → petakan ke SummaryData
  → Inertia::render('Ipp/ManagePlan/Index', [...props...])
  → Vue page menerima props lewat defineProps
```

Middleware `HandleInertiaRequests` menyuntikkan props global untuk setiap halaman: `auth.user`, `route.current`, `preferences.locale`, `layout.menu`. Semuanya berupa closure agar lazy-evaluated.

---

## 5. Lapisan DTO (`app/Data`)

Dua jenis DTO dengan peran berbeda:

| Jenis | Akhiran | Arah | Basis |
|---|---|---|---|
| Input | `...FormData` | Controller → Service | `Spatie\LaravelData\Data` |
| Output | `...SummaryData`, `...DetailData`, `...ItemData` | Service → JSON/Inertia | `App\Data\BaseData` |

Aturan:

- Semua DTO memakai `#[MapName(SnakeCaseMapper::class)]` → properti PHP camelCase, JSON keluar snake_case. Frontend selalu membaca snake_case.
- DTO output diberi atribut `#[TypeScript]` agar ikut di-generate ke `resources/js/types/generated.d.ts`.
- `SummaryData` untuk baris tabel/daftar; `DetailData` untuk satu entitas lengkap. Jangan pakai satu DTO gemuk untuk keduanya.

### 5.1 `BaseData` — deklarasi kebutuhan query

`BaseData` adalah inti pencegah N+1 query dan duplikasi eager-loading. Setiap DTO output mendeklarasikan relasi yang dibutuhkannya, lalu query menyesuaikan diri:

```php
class BaseData extends Data
{
    public function defaultWrap(): string { return 'data'; }

    public static function relations(): array { return []; }        // ->with()
    public static function countRelations(): array { return []; }   // ->withCount()
    public static function existRelations(): array { return []; }   // ->withExists()
    public static function sumRelations(): array { return []; }     // ->withSum()
    public static function avgRelations(): array { return []; }     // ->withAvg()
    public static function additionalSelects(): array { return []; }

    public static function prepareQuery(Builder|Relation $query): Builder|Relation { /* terapkan semua di atas */ }
    public static function loadRelations(Model $model): Model { /* versi untuk model tunggal */ }
    public static function relationsFromNested(string $name, array $relations): array { /* helper prefix */ }
}
```

Pemakaian di service:

```php
$query->with(KpiDictionarySummaryData::relations());     // daftar
KpiDictionaryDetailData::from(KpiDictionaryDetailData::loadRelations($model)); // detail
```

Karena kebutuhan relasi melekat pada DTO, penambahan kolom relasi di DTO tidak pernah lupa disertai eager-load di service.

### 5.2 `#[FromPolicy]` — izin ikut di dalam payload

Atribut kustom yang mengisi properti boolean dari hasil `Gate::allows()`:

```php
#[FromPolicy('update')] public bool $canUpdate;
#[FromPolicy('delete')] public bool $canDelete;
```

Implementasinya (`app/Extensions/Data/FromPolicy.php`) mengimplementasikan `InjectsPropertyValue` dari spatie/laravel-data dan memanggil `Gate::allows($ability, $payload)`. Dengan pola ini frontend tidak perlu menduga hak akses: setiap baris data membawa `can_update`, `can_delete`, dan seterusnya, sehingga tombol bisa disembunyikan berdasarkan data, bukan berdasarkan peran yang di-hardcode di Vue.

### 5.3 Trait & cast DTO

- `WithTimestamps`, `WithSoftDelete` — menambah `createdAt`/`updatedAt`/`deleted_at` tanpa pengulangan.
- `Extensions/Data/Casts/` — cast khusus (`EnumOrNullCast`, `StateCast`, `OptionCast`, `DateOrZeroCast`, dll.) untuk menormalkan nilai yang sering muncul.

---

## 6. Lapisan Service (`app/Services`)

Semua logika bisnis ada di sini. Karakteristik yang harus ditiru:

```php
readonly class KpiDictionaryService
{
    public function __construct(
        private KpiDictionary $kpiDictionary,   // instance model, bukan facade statis
        private DatabaseManager $db,            // untuk transaksi
        private AuthManager $auth,              // untuk user aktif
    ) {}
}
```

Aturan yang konsisten di seluruh repo:

1. Kelas `readonly`, dependensi lewat constructor property promotion.
2. **Tidak memakai facade statis** (`DB::`, `Auth::`) di dalam service — gunakan `DatabaseManager` dan `AuthManager` yang di-inject. Ini membuat service mudah di-test.
3. Query dimulai dari `$this->model->newQuery()`, bukan `Model::query()`.
4. Setiap operasi tulis dibungkus `$this->db->transaction(...)`.
5. Method mengembalikan DTO, bukan model. Bahkan `delete` mengembalikan `DetailData` (di-snapshot sebelum dihapus) agar respons tetap informatif.
6. Filter opsional memakai `->when(...)` berantai, bukan `if` bertumpuk.
7. Paginasi: `->paginate(15)` lalu `$results->transform(fn ($m) => SummaryData::from($m))` — bentuk paginator Laravel tetap dipertahankan sehingga frontend membaca `data`, `last_page`, `total`, `from`.

Service khusus yang layak ditiru sebagai kategori:
- **Calculator/Resolver** (`KpiScoreCalculator`, `KpiGroupResolver`) — perhitungan murni, tanpa I/O, mudah diuji unit.
- **Scope service** (`DepartmentApprovalScope`) — lihat §7.
- **Shared service** (`MenuService`, `ActivityLogService`, `FileStorage`, `Pdf`, `Notification`) — lintas domain, diletakkan di `Services/Shared`.

---

## 7. Contracts + Binding Strategi

Aturan organisasi yang berpotensi berubah (siapa boleh menyetujui siapa) tidak ditulis langsung di service konsumen, melainkan di balik interface:

```php
// app/Contracts/Approval/ResolvesApprovalScope.php
interface ResolvesApprovalScope
{
    public function subordinatePlans(User $approver): Builder;
    public function canActOn(User $approver, IppPlan $plan): bool;
}
```

Implementasi (`DepartmentApprovalScope`) di-bind di `AppServiceProvider::register()`:

```php
$this->app->bind(ResolvesApprovalScope::class, DepartmentApprovalScope::class);
$this->app->bind(ResolvesOrgScope::class, HierarchyOrgScope::class);
```

Manfaat untuk kloning: saat aturan hierarki berbeda, cukup menulis implementasi baru dan mengganti satu baris binding. Tidak ada `if ($user->role === ...)` yang tersebar.

---

## 8. Otorisasi Dua Lapis

### 8.1 Permission sebagai enum (bukan string bebas)

```php
enum KpiDictionaryPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW   = 'master:kpi:dictionary:view';
    case CREATE = 'master:kpi:dictionary:create';
    // ...

    public static function getGroupName(): string { return __('Master') . ' - ' . __('KPI Dictionary'); }
    public function getLabel(): string { return match ($this) { /* label ter-translate */ }; }
}
```

- Format nama: `area:domain:entity:action`, huruf kecil, dipisah titik dua.
- Interface `Permissionable` mewajibkan nama grup dan label, sehingga halaman pengelolaan role bisa merender daftar permission yang terkelompok dan berbahasa manusia secara otomatis.
- `PermissionService::getAllPermissionEnums()` mendaftar semua enum permission. `PermissionSeeder` mengiterasinya dan `updateOrCreate` ke tabel permission — menambah permission baru cukup dengan menambah `case` lalu menjalankan seeder.

### 8.2 Titik pemeriksaan

| Konteks | Cara |
|---|---|
| Aksi di controller | Middleware `can:` lewat `HasMiddleware` (contoh: `new Middleware('can:update,user', only: ['update'])`) atau `$this->authorize(...)` dari trait `AuthorizesRequests` di base `ApiController` / `Web\Controller` |
| Aturan per-objek | Policy class di `app/Policies/<Domain>`, diikat ke model lewat atribut `#[UsePolicy(KpiDictionaryPolicy::class)]` |
| Ekspos ke frontend | `#[FromPolicy('update')]` di DTO |
| Bypass super admin | `Gate::before(fn ($user) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null)` di `AppServiceProvider::boot()` |
| Menu sidebar | setiap `MenuItem` membawa `permission`; `MenuService` menyaring sebelum dikirim ke Inertia |

Policy umumnya tipis dan hanya mendelegasikan ke permission (`return $user->can(Permissions::UPDATE);`), kecuali untuk aturan kepemilikan/hierarki yang memakai scope service dari §7.

---

## 9. `app/Extensions` — Perluasan Framework

Folder ini menampung semua kode yang "menambal" atau memperluas Laravel/paket, terpisah dari kode domain:

- `Extensions/Data/FromPolicy.php`, `Casts/`, `Traits/`, `Transformers/` — perluasan spatie/laravel-data.
- `Extensions/Models/Traits/` — `CommonScopes` (scope tanggal generik, sadar driver MySQL/PostgreSQL), `WithTimestampScopes`, `HasActivityLogs`, `HasFile`, `BelongsToAuthor`.
- `Extensions/Permissions/` — interface `Permissionable` dan trait `PermissionGroup`.
- `Extensions/Notifications/Channels/DatabaseChannel` — channel notifikasi kustom, di-bind menggantikan bawaan Laravel.
- `Extensions/TypescriptTransformer/Writers/` — writer khusus untuk output TypeScript.
- `Extensions/Helpers/` — helper murni (`Text`, `Url`).

Aturan: kode di sini tidak boleh mengandung istilah domain. Bila membutuhkan konteks bisnis, tempatnya di `Services`.

---

## 10. Macro untuk Respons Web

`RedirectorServiceProvider` mendaftarkan macro pada `RedirectResponse`, `View`, dan `Redirector`:

- `->withToast(NotificationData)` — menyalakan toast; mengirim lewat `Inertia::flash('toast', ...)` **dan** session flash sekaligus, sehingga halaman Inertia maupun Blade sama-sama tertangani.
- `->withMessage(...)` — pesan inline pada halaman.
- `->setIntendedUrl(...)` — mengatur tujuan setelah login.

Di sisi Vue, `App.vue` memantau `page.props.flash?.toast` dan meneruskannya ke `vue-sonner`. Satu mekanisme notifikasi untuk seluruh aplikasi.

---

## 11. Query API Terstandar (`app/Http/Queries`)

Untuk endpoint daftar yang butuh filter/sort dinamis, dipakai subclass dari `spatie/laravel-query-builder`:

```php
abstract class Query extends QueryBuilder
{
    public ?int $perPage = 10;
    public ?int $page = 1;

    public function __construct(Builder|Relation $query, ?Request $request = null)
    {
        parent::__construct($query, $request);
        $this->perPage = $this->request->integer('per_page', 10);
        $this->page    = $this->request->integer('page', 1);
    }
}

class RoleQuery extends Query
{
    public function __construct(Builder|Relation $query, ?Request $request = null)
    {
        parent::__construct($query, $request);
        $this->defaultSort('type')
             ->allowedSorts(['type', 'label', 'users_count', 'created_at'])
             ->allowedFilters(['label', AllowedFilter::scope('type', 'whereType')]);
    }
}
```

Frontend membentuk parameter dengan helper `buildQueryBuilderParams()` (`filter[x]=`, `sort=`, `per_page=`, `page=`) sehingga kontrak query sama di semua tabel. Endpoint yang filternya sederhana boleh memakai `->when()` langsung di service (seperti `KpiDictionaryService`); gunakan `Query` bila daftar filter/sort mulai panjang.

---

## 12. Model

Konvensi model:

```php
#[UsePolicy(KpiDictionaryPolicy::class)]
class KpiDictionary extends Model
{
    use CommonScopes, HasFactory, SoftDeletes, WithTimestampScopes;

    protected $fillable = [...];
    protected function casts(): array { return [...]; }

    /** @return BelongsTo<KpiBscCategory, $this> */
    public function kpiBscCategory(): BelongsTo { return $this->belongsTo(KpiBscCategory::class); }
}
```

- Policy diikat lewat atribut PHP 8, bukan array di provider.
- Setiap relasi diberi anotasi generic docblock (`@return BelongsTo<Target, $this>`) untuk static analysis.
- Model hanya berisi relasi, scope, cast, accessor. Tanpa logika bisnis.
- Status entitas yang berperilaku (punya label, severity, transisi) memakai `spatie/laravel-model-states` dengan basis:

```php
abstract class BaseState extends State
{
    abstract public function value(): string;
    abstract public function label(): string;
    abstract public function severity(): Severity;
}
```

`severity()` mengembalikan enum tampilan bersama, sehingga warna badge di frontend berasal dari backend — bukan dari `switch` di Vue.

---

## 13. Frontend (`resources/js`)

```
resources/js/
├── app.ts               # entry non-Inertia (asset glob)
├── inertia-app.ts       # entry Inertia: createInertiaApp + i18n
├── App.vue              # shell global: toast, viewer PDF
├── layouts/             # DefaultLayout.vue, EmptyLayout.vue
├── pages/<Domain>/<Halaman>/
│     ├── Index.vue
│     ├── partials/      # komponen khusus halaman ini
│     ├── composables/   # state/logika khusus halaman ini
│     └── types.ts       # tipe khusus halaman ini
├── components/
│     ├── ui/            # primitif shadcn-vue (Button, Dialog, ...)
│     └── shared/        # komponen aplikasi lintas halaman (DataView, FormField, ...)
├── composables/         # composable global (useDataTable, useCrudResource, ...)
├── services/            # klien HTTP per domain (satu file per domain)
├── utils/               # fungsi murni (format, status, ky, error)
├── types/               # tipe manual + generated.d.ts + generated-enums.ts
├── routes/ & actions/   # hasil generate Wayfinder
└── theme.ts, lib/utils.ts
```

Aturan pemisahan yang membuat struktur ini tidak berantakan:

- Komponen yang dipakai **satu halaman** wajib berada di `pages/.../partials/`, bukan di `components/`. Naik ke `components/shared/` hanya setelah dipakai ≥2 halaman.
- Logika halaman yang panjang dipindahkan ke `pages/.../composables/usePlan.ts` dan sejenisnya. `Index.vue` menjadi perakit, bukan tempat logika.
- `components/ui/` hanya berisi primitif tanpa pengetahuan domain.

### 13.1 Layer service frontend

Semua akses HTTP internal melewati `resources/js/services/<domain>.ts`, tidak pernah `ky`/`fetch` langsung di dalam komponen:

```ts
import ky from '@/utils/ky'

type RawKpiProposal = App.Data.Kpi.KpiProposal.KpiProposalSummaryData   // tipe hasil generate

const BASE_URL = '/api/internal/kpi/proposals'

export function mapProposal(raw: RawKpiProposal): KpiProposal { /* snake_case → camelCase */ }
function toPayload(form: KpiProposalForm) { /* camelCase → snake_case */ }

export async function fetchProposals(): Promise<KpiProposal[]> {
    const response = await ky.get(BASE_URL).json<Pagination<RawKpiProposal>>()
    return response.data.map(mapProposal)
}
```

Pola ini menempatkan konversi penamaan dan bentuk payload di satu berkas per domain. Komponen hanya mengenal tipe aplikasi (camelCase), tidak pernah menyentuh bentuk mentah API.

`utils/ky.ts` adalah instance `ky` yang menyisipkan header CSRF otomatis (dari cookie `XSRF-TOKEN`, dengan fallback ke `<meta name="csrf-token">`).

### 13.2 Composable generik

Dua composable menjadi tulang punggung halaman data:

- **`useDataTable<T>({ apiUrl, filters, sortOptions, perPageOptions, defaultView })`** — menangani paginasi, filter ter-debounce, sort multi-kolom, mode tampilan tabel/list, status loading dan error. Mengembalikan `items`, `loading`, `page`, `totalPage`, `nextPage`, `updateData`, dan seterusnya.
- **`useCrudResource<TRow, TForm>({ apiUrl, label, blankForm, toForm, toPayload, validate })`** — dibangun di atas `useDataTable`, menambahkan modal create/view/edit, penyimpanan, dialog konfirmasi hapus, toast sukses/gagal, dan pemetaan error API.

Halaman master data baru umumnya hanya perlu mendefinisikan `blankForm`, `toForm`, `toPayload`, dan kolom tabel. Inilah alasan halaman-halaman CRUD di sistem ini seragam.

Composable global lain yang layak disalin: `useConfirmDialog`, `useToast`, `usePageHeader`, `useTableFilter`, `useUnsavedGuard`, `useActions`, `useLocale`, `usePendingCounts`.

### 13.3 Tipe bersama PHP → TypeScript

`spatie/laravel-typescript-transformer` mengumpulkan kelas ber-atribut `#[TypeScript]` dan menuliskannya ke `resources/js/types/generated.d.ts` sebagai namespace global `App.Data....`. Konfigurasi penting (`config/typescript-transformer.php`):

- `auto_discover_types` → `app_path()`
- collector: `DefaultCollector` saja (enum diekspor secara selektif lewat atribut, bukan seluruhnya)
- transformer: `DataTypeScriptTransformer`, `EnumTransformer`, `SpatieStateTransformer`, `DtoTransformer`
- `default_type_replacements`: semua tipe tanggal → `string`

Perintah: `php artisan typescript:transform` (sudah dipasang di `post-update-cmd` composer).

### 13.4 Route bersama PHP → TypeScript

`laravel/wayfinder` + `@laravel/vite-plugin-wayfinder` meng-generate berkas TypeScript per grup route ke `resources/js/routes/**` (dan `actions/` bila diaktifkan) sehingga URL di frontend selalu sinkron dengan `routes/*.php`. Di `vite.config.js` plugin dipasang dengan `wayfinder({ actions: false })`.

### 13.5 Alias & entry

```js
resolve: { alias: { '@': 'resources/js', '%': 'resources' } }
input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/inertia-app.ts']
```

Resolusi halaman Inertia memakai glob dan menetapkan layout default secara otomatis:

```ts
resolve: async (name) => {
    const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue')
    const page = await pages[`./pages/${name}.vue`]!()
    page.default.layout = page.default.layout || DefaultLayout
    return page
}
```

Halaman yang butuh layout lain cukup mengekspor `layout` sendiri.

---

## 14. Menu & Navigasi

`MenuService` mendefinisikan menu sebagai array DTO `MenuItem` (key, label ter-translate, permission, route, routeGroup untuk penandaan aktif, ikon), menyaringnya berdasarkan permission pengguna, dan meng-cache-nya (`config('cache.duration.user_menu')`, nonaktif di lokal). Hasilnya dibagikan sebagai prop Inertia `layout.menu`.

Breadcrumb didefinisikan terpusat di `routes/breadcrumbs.php` memakai `diglactic/laravel-breadcrumbs`, lalu dikirim ke Inertia lewat `robertboes/inertia-breadcrumbs`.

---

## 15. Blade yang Tetap Dipakai

Meski frontend utama Vue, Blade tetap dipakai untuk tiga hal:

1. **Root template Inertia** — `resources/views/components/layouts/inertia-app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`). Diatur lewat `Inertia::setRootView(...)`.
2. **Halaman non-Inertia** — autentikasi/aktivasi/reset password berada di grup middleware `NonInertiaRoutes` dan dirender sebagai Blade biasa (`resources/views/pages/auth`), memakai komponen Blade di `app/View/Components/Shared/...`.
3. **Email dan PDF** — `resources/views/emails/**`, `resources/views/pdf/**`.

Memisahkan alur autentikasi dari bundel Inertia menjaga halaman login tetap ringan dan bebas dari state aplikasi.

---

## 16. Mail & Notifikasi

- Mailable per peristiwa di `app/Mail/<Domain>/`, mengimplementasikan `ShouldQueue`, memanggil `$this->afterCommit()` di constructor agar email tidak terkirim saat transaksi gagal.
- Bagian yang berulang (tabel item, sapaan, penguncian locale) diekstrak ke trait di `app/Mail/Concerns/` (`BuildsPlanItemsTable`, `GreetsRecipient`).
- Tautan dari email menuju route bertanda tangan (`Route::middleware(['signed'])->prefix('mail')`) yang ditangani `EmailRedirectController` dan diarahkan ke halaman aplikasi yang tepat. Tautan kedaluwarsa ditangani di `withExceptions` pada `bootstrap/app.php` yang merender `emails.link-expired` alih-alih halaman error umum.
- Notifikasi database memakai channel kustom; push notification lewat `laravel-notification-channels/webpush` dengan endpoint `api/internal/web-push`.

---

## 17. Konfigurasi

Parameter bisnis tidak ditulis sebagai angka ajaib di kode, melainkan di config domain sendiri — `config/ipp.php`:

```php
'plan'      => ['max_items' => 10, 'max_weight' => 100],
'review'    => ['score_precision' => 2, 'enforce_phase_window' => false, ...],
'appraisal' => ['max_achievement' => env('IPP_APPRAISAL_MAX_ACHIEVEMENT', 125), ...],
```

`config/roles.php` menyimpan kredensial akun bawaan untuk seeding, `config/cache.php` menambah blok `duration` khusus aplikasi. Saat kloning, buat satu file config bernama domain utama sistem dan letakkan seluruh ambang batas serta sakelar fitur di sana.

`bootstrap/app.php` menampung: middleware web (`SetLocale`, `HandleInertiaRequests`), `trustHosts`/`trustProxies`, pengalihan tamu ke `auth.login.form`, dan handler exception khusus.

`AppServiceProvider::boot()` juga menetapkan `SchemaBuilder::defaultStringLength(191)` dan `bcscale(4)` — presisi desimal dijaga di level aplikasi karena perhitungan skor memakai BCMath.

---

## 18. Database & Seeder

- Migrasi dikelompokkan **per domain, bukan per tabel**: `2026_06_30_022536_create_kpi_table.php` membuat seluruh tabel domain KPI dalam satu berkas. Migrasi infrastruktur (users, cache, jobs, permission, files, notifications, activity logs) tetap terpisah di awal.
- Seeder berpasangan dengan domain (`OrgSeeder`, `PeriodSeeder`, `KpiSeeder`, `IppSeeder`, `ReviewSeeder`, `AppraisalSeeder`, `CncSeeder`) plus seeder infrastruktur (`PermissionSeeder`, `RoleSeeder`, `SuperAdminSeeder`, `UserSeeder`).
- Soft delete dipakai luas; query daftar memperhitungkan `deleted_at` (mis. aturan validasi `Rule::exists(...)->whereNull('deleted_at')`).

---

## 19. Validasi

`FormRequest` per aksi: `StoreXRequest`, `UpdateXRequest`, `BulkUpdateXRequest`, diletakkan di `app/Http/Requests/<Domain>/<Entity>/`.

- Aturan ditulis sebagai array (bukan string pipa).
- Referensi tabel memakai `Rule::exists(Model::class, 'id')` agar tahan terhadap perubahan nama tabel.
- Aturan bersyarat memakai `required_if` daripada logika di controller.
- Validasi lintas-field yang rumit diekstrak ke `app/Rules/<Domain>/`.
- Otorisasi **tidak** ditaruh di `authorize()` milik FormRequest; semuanya di controller/policy agar satu tempat.

---

## 20. Pengujian

```
tests/
├── TestCase.php
├── Concerns/CreatesUsers.php
├── Feature/<Domain>/ + Feature/Concerns/
└── Unit/<Domain>/
```

- Feature test memakai `RefreshDatabase` dan menyemai seeder domain yang dibutuhkan di `setUp()`.
- Trait pembuat fixture (`CreatesUsers`, `CreatesDepartmentUsers`) menghindari duplikasi pembuatan aktor.
- Perhitungan murni diuji sebagai unit test.
- Praktik yang terlihat di repo dan layak ditiru: docblock di kelas test menjelaskan *mengapa* test itu ada (regresi apa yang dijaga), terutama untuk test paritas antara perhitungan frontend dan backend.

Perintah: `./vendor/bin/sail artisan test`.

---

## 21. Internasionalisasi

- Berkas `lang/en.json`, `lang/id.json`, dan direktori `lang/en`, `lang/id`.
- Backend memakai `__('...')` termasuk untuk label permission dan menu.
- Frontend memakai `laravel-vue-i18n`; berkas bahasa dimuat lewat glob di `inertia-app.ts`.
- Middleware `SetLocale` menetapkan locale per request; `LanguageController` (`/language/{lang}`) mengganti bahasa; locale aktif dibagikan sebagai prop `preferences.locale`.
- Email dapat memaksa locale tertentu (`forceEnglishLocale()`).

---

## 22. Urutan Pembangunan Repo Baru

Urutan berikut mengikuti ketergantungan antar lapisan:

1. `laravel new`, pasang paket inti: `inertiajs/inertia-laravel`, `spatie/laravel-data`, `spatie/laravel-permission`, `spatie/laravel-query-builder`, `spatie/laravel-typescript-transformer`, `spatie/laravel-model-states`, `laravel/wayfinder`, `laravel/sail`.
2. Frontend: Vue 3 + TS + Vite + Tailwind 4 + `@inertiajs/vue3` + shadcn-vue. Pasang alias `@` dan `%`, plugin wayfinder dan i18n di `vite.config.js`.
3. Kerangka `app/Extensions`: `BaseData`, `FromPolicy`, trait DTO, `Permissionable` + `PermissionGroup`, `CommonScopes`, helper.
4. Base class: `Web\Controller`, `Web\InertiaController`, `Api\ApiController`, `Http\Queries\Query`, `States\BaseState`.
5. Middleware `HandleInertiaRequests` (shared props), `SetLocale`, `NonInertiaRoutes`; root Blade Inertia; `AppServiceProvider` (Gate::before super admin, binding contracts, `bcscale`, `defaultStringLength`).
6. `RedirectorServiceProvider` (macro toast/message/intended) dan `App.vue` yang mengonsumsi flash.
7. Domain autentikasi: model `User`, enum `Roles`, `PermissionService`, `PermissionSeeder`, `RoleSeeder`, `SuperAdminSeeder`, halaman auth Blade.
8. `MenuService` + `MenuItem` DTO + `routes/breadcrumbs.php`.
9. Composable frontend generik: `utils/ky.ts`, `useDataTable`, `useCrudResource`, `useConfirmDialog`, `useToast`.
10. Baru kemudian domain bisnis, satu per satu, mengikuti urutan: migrasi domain → model + policy + enum permission → DTO → service → FormRequest → API controller → Web controller → halaman Vue + service frontend.

---

## 23. Aturan Ringkas (checklist konsistensi)

**Backend**

1. Controller tidak berisi logika bisnis; hanya otorisasi, pemetaan input, pemanggilan service.
2. Service `readonly`, dependensi di-inject, tanpa facade statis, semua tulis dalam transaksi, selalu mengembalikan DTO.
3. Model hanya relasi/scope/cast; policy diikat lewat `#[UsePolicy]`.
4. Permission selalu enum bernama `area:domain:entity:action`, tidak pernah string mentah.
5. Setiap DTO output mendeklarasikan `relations()` yang dibutuhkannya.
6. Hak akses per baris diekspos lewat `#[FromPolicy]`, bukan dihitung ulang di frontend.
7. Angka ambang dan sakelar fitur berada di `config/<domain>.php`.
8. Aturan organisasi yang bisa berubah diletakkan di balik `Contracts` dan di-bind di provider.

**Frontend**

9. Tidak ada `fetch`/`ky` langsung di komponen — selalu lewat `services/<domain>.ts`.
10. Tidak ada tipe data API yang ditulis tangan — pakai `App.Data.*` hasil generate.
11. Tidak ada URL hardcode ke route bernama — pakai hasil generate Wayfinder (atau konstanta `BASE_URL` di service).
12. Komponen spesifik halaman berada di `partials/` halaman tersebut.
13. Logika halaman panjang pindah ke `composables/` lokal halaman.
14. Warna status/severity berasal dari backend (`BaseState::severity()`), bukan `switch` di Vue.
15. Payload keluar snake_case, state internal camelCase; konversi hanya di layer service frontend.

---

## 24. Diagram Alur Singkat

```
┌────────────────────────── Browser ──────────────────────────┐
│  Vue page (pages/Domain/Halaman/Index.vue)                  │
│    ├─ props awal (Inertia)  ←──────────────┐                │
│    ├─ composables lokal + useDataTable     │                │
│    └─ services/<domain>.ts (ky) ──┐        │                │
└───────────────────────────────────┼────────┼────────────────┘
                                    │ JSON   │ Inertia
                   /api/internal/*  │        │  routes/web.php
                                    ▼        │
                       ┌────────────────────┐│  ┌────────────────────┐
                       │ Api\Internal\...   ││  │ Web\...Controller  │
                       │ Controller         ││  │ Inertia::render()  │
                       └─────────┬──────────┘│  └─────────┬──────────┘
        FormRequest (validasi) ──┤           └────────────┤
        Permission enum (authz) ─┤                        │
                                 ▼                        ▼
                       ┌───────────────────────────────────────┐
                       │ Service (readonly, transaksional)     │
                       │   ├─ Contracts/Scope (strategi)       │
                       │   └─ Calculator / Resolver            │
                       └─────────┬─────────────────────────────┘
                                 ▼
                       ┌───────────────────────┐
                       │ Model + Policy + State│
                       └─────────┬─────────────┘
                                 ▼
                       ┌───────────────────────┐
                       │ DTO (BaseData)        │
                       │  relations()          │
                       │  #[FromPolicy]        │
                       │  #[TypeScript] ───────┼──► generated.d.ts
                       └───────────────────────┘
```
