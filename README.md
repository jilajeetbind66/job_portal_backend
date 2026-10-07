# Job Portal – Backend (Laravel API)

Ye project ka backend hai. Ye **API-only** Laravel app hai, jiska frontend alag React SPA hai (`../frontend`).

> **Status:** Abhi sirf Phase 0 ka scaffold hua hai. Yaani folder structure bana hai aur dependencies install hui hain. Koi feature, business logic, app tables ki migrations, `.env` config, middleware aliases, routes ya placeholder classes abhi nahi bane. Wo aage ke phases me honge.

---

## 1. Project kya hai (context)

- Job portal me **3 portals** hain:
  - **Candidate** – job dhoondhne aur apply karne wale log.
  - **Partner** – companies (jaise SBI) jo jobs post karti hain.
  - **Admin** – platform ka owner, jo sab kuch manage karta hai.
- **Multi-tenant** design: har Partner ek tenant hai. Database ek hi shared hai. Tenant ki rows me `partner_id` column hoga. Data isolation `BelongsToPartner` global scope se hoga (later phase me).
- **Roles:** `candidate`, `partner_recruiter`, `partner_admin`, `super_admin`.
- **Auth:** Laravel Sanctum, SPA cookie-based (token nahi).

---

## 2. Tech stack aur installed versions

| Cheez | Version |
|---|---|
| PHP | 8.3.33 |
| Composer | 2.10.3 |
| Laravel (laravel/framework) | ^13.17 |
| Laravel Sanctum | ^4.0 |
| spatie/laravel-permission | ^8.3 |
| Laravel Horizon | ^5.50 |
| Laravel Scout | ^11.9 |
| meilisearch/meilisearch-php | ^1.17 |
| http-interop/http-factory-guzzle | ^1.2 |
| league/flysystem-aws-s3-v3 | ^3.35 |

**Dev dependencies:**

| Package | Version | Kaam |
|---|---|---|
| pestphp/pest | ^4.7 | Testing framework |
| pestphp/pest-plugin-laravel | ^4.1 | Pest ke liye Laravel helpers |
| larastan/larastan | ^3.12 | Static analysis (PHPStan) |
| laravel/pint | ^1.32 | Code formatter |

Packages kis liye hain:
- **Sanctum** – SPA cookie authentication.
- **spatie/laravel-permission** – roles aur permissions.
- **Horizon** – Redis queues ka dashboard aur manager.
- **Scout + meilisearch-php** – job search (Meilisearch engine).
- **http-factory-guzzle** – Meilisearch client ko HTTP factory dene ke liye.
- **flysystem-aws-s3-v3** – S3 / MinIO me files (resumes, public files) rakhne ke liye.

---

## 3. Folder structure

```
backend/
├── app/
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Auth/
│   │   │   ├── Public/
│   │   │   ├── Candidate/
│   │   │   ├── Partner/
│   │   │   └── Admin/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Listeners/
│   ├── Models/
│   │   └── Concerns/          # yaha BelongsToPartner trait aayega
│   ├── Notifications/
│   ├── Observers/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
├── routes/
│   ├── api.php                # Sanctum install karte time bana
│   └── console.php
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
└── artisan
```

Jo naye folders humne banaye (khali hain, `.gitkeep` ke saath): `Events`, `Listeners`, `Notifications`, `Observers`, `Policies`, `Services`, `Http/Requests`, `Http/Resources`, `Http/Middleware`, `Models/Concerns`, `Http/Controllers/Api/V1/{Auth,Public,Candidate,Partner,Admin}`.

---

## 4. Ab tak kya ho chuka hai

- PHP 8.3 aur Composer system par install kiye (PHP `winget` se, Composer official phar se `C:\Users\hp\bin` me).
- `php.ini` banaya aur ye extensions on kiye: curl, fileinfo, mbstring, openssl, pdo_mysql, mysqli, pdo_sqlite, sqlite3, zip, intl, sodium, gd, exif.
- Laravel create kiya aur pehle se bane folders ke upar merge kiya.
- `php artisan install:api` chalaya. Isse `routes/api.php` aur Sanctum ki `personal_access_tokens` migration ban gayi.
- Saare composer packages (upar table me) install hue.

## 5. Abhi KYA NAHI hua (aage ke kaam)

- `.env` / `.env.example` configuration (MySQL, Redis, Meilisearch, MinIO, Mailpit, Sanctum domains).
- CORS config (sirf `FRONTEND_URL`, `supports_credentials=true`).
- `bootstrap/app.php` me `role`, `permission`, `role_or_permission` middleware aliases aur `statefulApi()`.
- Spatie permission / Horizon / Scout ke config publish karna aur spatie ki migration publish karna.
- Placeholder classes: `EnsurePartnerActive`, `EnsureUserActive`, `BelongsToPartner`, `RoleSeeder`, `SuperAdminSeeder`, `DemoSeeder`.
- `routes/api.php` me `v1` groups aur `GET /api/v1/health`.
- Tests ko Pest me convert karna aur health route ka test.
- Root level `docker-compose.yml` (MySQL, Redis, Meilisearch, MinIO, Mailpit), `.gitignore`, git init, commit.
- `php artisan migrate`, tests, Pint aur Larastan ka verify.

---

## 6. Important notes / dhyan rakhne wali baatein

1. **Horizon Windows par nahi chalega.** Horizon ko `pcntl` aur `posix` PHP extensions chahiye, jo Windows PHP me available nahi hote. Install karne ke liye `composer.json` me ye platform override daala gaya hai:
   ```
   composer config platform.ext-pcntl 8.3
   composer config platform.ext-posix 8.3
   ```
   Local Windows par queue chalane ke liye `php artisan queue:work` use karo, ya WSL / Docker me Horizon chalao.
2. **Guzzle psr7 downgrade:** `http-factory-guzzle` ko psr7 `^1` ya `^2` chahiye tha, jabki Laravel ne `3.x` lock kiya tha. Isliye `-W` flag se psr7 ko 2.x par laya gaya. Isse koi dikkat nahi hai.
3. **Naya terminal kholo:** `php` aur `composer` ka PATH naye terminals me hi milega. Purane terminal me "command not found" aa sakta hai.
4. **sqlite migrate warning:** Default Laravel `.env` me `DB_CONNECTION=sqlite` hota hai aur `install:api` ke end me migrate chalata hai. Isliye `database.sqlite` na hone par error aaya. Ye abhi theek hai, kyunki hum MySQL par shift karenge.
5. Laravel ke saath `AGENTS.md` aur `CLAUDE.md` files aayi hain. Zaroorat na ho to delete kar sakte ho.

---

## 7. Setup commands (jab config ho jaaye)

```bash
# 1. Root se docker services chalao (docker-compose.yml banne ke baad)
docker compose up -d

# 2. Backend me jao
cd backend

# 3. Dependencies install
composer install

# 4. Env file
cp .env.example .env
php artisan key:generate

# 5. Database tables
php artisan migrate

# 6. Server chalao
php artisan serve        # http://localhost:8000
```

Roz ke useful commands:

```bash
php artisan test                  # tests chalao
./vendor/bin/pint                 # code format karo
./vendor/bin/pint --test          # sirf check karo, change nahi
./vendor/bin/phpstan analyse      # Larastan (config baad me)
php artisan route:list            # saare routes dekho
php artisan queue:work            # queue worker (Windows par)
```

---

## 8. Planned ports (docker ke baad)

| Service | Port |
|---|---|
| Laravel API | 8000 |
| React (Vite) | 5173 |
| MySQL | 3306 |
| Redis | 6379 |
| Meilisearch | 7700 |
| MinIO API / Console | 9000 / 9001 |
| Mailpit SMTP / UI | 1025 / 8025 |

---

## 9. Planned API structure

Sab routes `/api/v1` ke neeche honge, 5 groups me:

| Group | Kaun use karega | Middleware (planned) |
|---|---|---|
| public | koi bhi (jobs, companies) | none / throttle |
| auth | login, register, logout | `guest` / `auth:sanctum` |
| candidate | candidate role | `auth:sanctum`, `EnsureUserActive`, `role:candidate` |
| partner | partner users | `auth:sanctum`, `EnsureUserActive`, `EnsurePartnerActive`, `role:partner_admin\|partner_recruiter` |
| admin | super admin | `auth:sanctum`, `EnsureUserActive`, `role:super_admin` |

Health check: `GET /api/v1/health` → `{"status":"ok"}`.
