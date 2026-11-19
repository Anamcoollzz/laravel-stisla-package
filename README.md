# Laravel Stisla Package

Publishable Laravel Stisla admin panel, authentication, RBAC (`spatie/laravel-permission`), settings, logs, database backup/table viewer, and modular app starter package.

## Requirements

- PHP `^8.3`
- Laravel `^11.0 | ^12.0 | ^13.0`

## Installation

Install the package via Composer:

```bash
composer require anamcoollzz/laravel-stisla-package
```

## Quick Setup

Run the installer command to publish all stubs (controllers, models, commands, exports, imports, middleware, requests, mail, services, `config/stisla.php`, views, public assets, routes, migrations, seeders, factories, `indonesia.sql`, and translations) and register modular routes in `routes/web.php`:

```bash
php artisan stisla:install --module=all --force --seed
```

If you have not configured your database connection in `.env` yet, run without `--seed` first:

```bash
php artisan stisla:install --module=all --force
# Configure DB_* in .env
php artisan storage:link
php artisan migrate --seed
```

### Installer Options

| Option | Default | Description |
| --- | --- | --- |
| `--module=` | `core` | App module routes & `.env` `STISLA_ADMIN_MENU_APP` to enable: `core`, `online-shop`, `news`, `spp-payment`, `lp-compro-workers`, or `all` |
| `--force` | `false` | Overwrite existing application files (recommended on fresh Laravel installs for `User.php`, `DatabaseSeeder.php`, etc.) |
| `--seed` | `false` | Automatically run `php artisan storage:link` and `php artisan migrate --seed` after publishing |

## Configuration & Environment Variables

Configuration is published to `config/stisla.php` (`is_demo`, `admin_template`, `menu_app`). The installer also ensures the following keys exist in `.env` and `.env.example`:

```dotenv
STISLA_IS_DEMO=false
STISLA_ADMIN_TEMPLATE=stisla
STISLA_ADMIN_MENU_APP=online-shop
```

- `STISLA_ADMIN_MENU_APP`: Switches active sidebar menus and modular routes (`online-shop`, `news`, `spp-payment`, `lp-compro-workers`). If you change this value while route caching is enabled, run `php artisan route:cache` (or `php artisan route:clear`).
- `STISLA_IS_DEMO`: Set to `true` to enable demo mode protections (`PreventInDemoMode` middleware, disabled destructive backup/database actions, and demo account quick-fill on login pages).

## Selective Publishing

If you only want to publish specific parts, use `vendor:publish` with any of the available tags:

```bash
php artisan vendor:publish --tag=stisla-all --force
```

| Tag | Destination |
| --- | --- |
| `stisla-controllers` | `app/Http/Controllers` |
| `stisla-models` | `app/Models` |
| `stisla-commands` | `app/Console/Commands` |
| `stisla-exports` | `app/Exports` |
| `stisla-imports` | `app/Imports` |
| `stisla-middleware` | `app/Http/Middleware` |
| `stisla-requests` | `app/Http/Requests` |
| `stisla-mail` | `app/Mail` |
| `stisla-providers` | `app/Providers` |
| `stisla-services` | `app/Services` |
| `stisla-config` | `config` |
| `stisla-views` | `resources/views` |
| `stisla-public` | `public` |
| `stisla-routes` | `routes` |
| `stisla-database` | `database` (`migrations`, `seeders`, `factories`, `indonesia.sql`) |
| `stisla-lang` | `lang` |
| `stisla-all` | All of the above |

## Default Seeded Admin Account

After running `php artisan migrate --seed`:

- **Email:** `admin@example.com`
- **Password:** `password`
