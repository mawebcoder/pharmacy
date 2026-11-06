# Online Pharmacy

Persian (RTL) online pharmacy: a Livewire storefront and a Filament admin panel.

- **Roadmap and specification:** [docs/pharmacy-roadmap.md](docs/pharmacy-roadmap.md) is the single source of truth.
- **Progress:** [ROADMAP_PROGRESS.md](ROADMAP_PROGRESS.md).

## Stack

PHP 8.5, Laravel 13, Livewire 4, Alpine, Tailwind CSS 4, TallStackUI 4, Filament 5, MySQL 8.4, Redis 7, Horizon under Supervisor, Pest 4.

Conventions: Persian-only UI, timestamps stored in UTC and shown as Jalali dates in Asia/Tehran, money stored as integer rial.

## Local setup (Docker)

```bash
cp .env.example .env
docker compose up -d --build --wait
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run build
```

| Service | Address |
|---|---|
| Storefront | http://localhost:7000 |
| Admin (moves to `/admin` in S03) | http://admin.localhost:7000 |
| Horizon | http://admin.localhost:7000/horizon |
| MySQL (from the host) | 127.0.0.1:7070 |
| Vite dev server | `docker compose exec app npm run dev` on port 5173 |

Run Artisan, Composer and npm inside the `app` container: the database (`mysql`) and Redis (`redis`) hosts only resolve there. The seeder creates the super admin from `config/auth.php` (override with `SUPERADMIN_*` in `.env`).

## Checks

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app php artisan test --parallel
```

CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)) runs the same checks plus `composer audit` and the asset build on every push.

## Modules

Business code lives in `app/Modules/{Module}` (roadmap C3), one service provider per module, extending `App\Modules\Shared\ModuleServiceProvider`. It loads the module's migrations, `Routes/web.php`, views (`catalog::…`), Livewire components (`catalog::product-page`) and console commands. Create one with:

```bash
docker compose exec app php artisan make:module Coupons
```

## Working on a roadmap step

Each step (`S00`–`S40`) gets its own branch, `feat/S{nn}-{slug}`. With Claude Code, run the `implement-roadmap-section` skill with a step id or entity (e.g. `S07` or `Brands`).
