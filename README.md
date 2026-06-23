# PGB - LIS

Laboratory Information System built with [Laravel](https://laravel.com) and [Vite](https://vitejs.dev).

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- npm

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

## Development

Run the Laravel server and Vite dev server:

```bash
php artisan serve
npm run dev
```

Visit `http://localhost:8000`.

## Tech Stack

- **Backend:** Laravel 13
- **Frontend:** Vite, Tailwind CSS
- **Database:** SQLite (default) — configure in `.env`
