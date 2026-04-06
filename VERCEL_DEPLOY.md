# Deploy Monica On Vercel

This repository now includes a Vercel PHP entrypoint and routing config:

- `api/index.php` forwards requests to Laravel's `public/index.php`.
- `vercel.json` runs the app with `vercel-php@0.9.0`.

## 1) Create The Vercel Project

1. Import this GitHub repo in Vercel.
2. Set **Framework Preset** to `Other`.
3. Keep project root at `/`.

## 2) Configure Environment Variables

At minimum, set:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-domain>`
- `APP_KEY=<generated-laravel-key>`
- `DB_CONNECTION` + matching DB credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- `QUEUE_CONNECTION=sync`

Recommended for serverless:

- `CACHE_STORE=database`
- `SESSION_DRIVER=database`

## 3) Prepare A Managed Database

Do not use local SQLite files on Vercel for production. Use a managed MySQL/PostgreSQL database.

After the first deployment, run migrations against that database:

```bash
php artisan migrate --force
```

## 4) First Deploy

Deploy from Vercel dashboard (or `vercel --prod`), then open your domain.

Static assets are served from:

- `/build/*` -> `public/build/*`
- `/images/*` -> `public/images/*`
- `/favicon.ico`, `/robots.txt`, `/security.txt`

All other routes are handled by Laravel via `api/index.php`.

## Notes

- Background workers and long-running jobs are not a natural fit for Vercel serverless functions.
- User-uploaded files should use external object storage (for example S3-compatible storage), not local disk.
