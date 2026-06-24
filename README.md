# PGB - SPLIS

Laboratory Information System built with [Laravel](https://laravel.com) and [Vite](https://vitejs.dev).

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- npm
- MySQL 8+ (database: `splis`)

## Local setup (without Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
php artisan serve
```

## Docker (production / Coolify)

Build and run with an external MySQL service (database `splis`):

```bash
docker build -t pgb-lis .
docker run -p 8080:80 \
  -e APP_KEY=base64:YOUR_KEY_HERE \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_URL=https://your-domain.example \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=your-mysql-host \
  -e DB_PORT=3306 \
  -e DB_DATABASE=splis \
  -e DB_USERNAME=splis \
  -e DB_PASSWORD=your-password \
  -v pgb-lis-storage:/var/www/html/storage/app \
  pgb-lis
```

### Coolify environment variables

| Variable | Example |
|----------|---------|
| `APP_KEY` | `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://lis.example.gov.ph` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | internal MySQL hostname |
| `DB_DATABASE` | `splis` |
| `DB_USERNAME` | `splis` |
| `DB_PASSWORD` | *(your password)* |

Mount a persistent volume at `/var/www/html/storage/app` for uploaded files (PDFs).

On startup the container runs `php artisan migrate --force` automatically.

## Docker Compose (local stack)

Includes MySQL with database `splis` on host port `3307`:

```bash
# Generate a key first, then add to .env or export:
export APP_KEY="$(php artisan key:generate --show)"
docker compose up --build
```

App: http://localhost:8080

## Development (Laravel Herd)

This project is linked as **http://splis.test** via Herd:

```bash
herd link splis   # already done once; re-run if needed
npm run dev       # Vite hot reload (keep running while developing)
```

Open **http://splis.test** in your browser.

**Default login:** `admin` / `password`

**Setup commands (first time):**

```bash
php artisan migrate
php artisan db:seed
php artisan splis:import-lookups
php artisan splis:import-resolutions
php artisan splis:import-from-csv --lookups
php artisan resolutions:link-pdfs
php artisan resolutions:verify-pdfs --limit=100
```

Without Herd, use `npm run dev:full` and open http://127.0.0.1:8000 instead.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Vite 8, Tailwind CSS 4
- **Database:** MySQL (`splis`)
- **Runtime:** Nginx + PHP-FPM (Alpine)
