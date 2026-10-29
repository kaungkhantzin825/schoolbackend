# MAVER — Production Deployment Checklist

The app is public-facing and stores NRC numbers and dates of birth, so these
steps are not optional.

## 1. Environment (`.env` on the server)

```env
APP_ENV=production
APP_DEBUG=false          # ← critical: true leaks stack traces + DB credentials
APP_URL=https://backend.mmcertify.com
# Public URL of the React site. Password-reset and account-approval emails are
# built from this — if it is missing, recipients get a http://localhost link
# that only works on a developer's machine. A misconfiguration is logged as an
# error in storage/logs/laravel.log ("FRONTEND_URL is unset or points at
# localhost"), so grep for that if an emailed link looks wrong.
FRONTEND_URL=https://<your-frontend-domain>

# Optional, comma separated. While the site is reachable on two domains, list
# both so links match the domain the user actually came from. Only origins
# listed here are ever trusted from a request header.
# ACVR_FRONTEND_URLS=https://acvr.edu.mm,https://project.digiserve.com.mm

# Sender name on every outgoing email. Without it recipients see "Laravel".
MAIL_FROM_NAME="ACVR"

# Contact form destination + branding. This is the *recipient*; the From
# address must stay the authenticated SMTP account (Gmail refuses to send as
# an address it does not own).
ACVR_NAME="ACVR"
ACVR_SUPPORT_EMAIL="support@acvr.edu.mm"
ACVR_WEBSITE="https://acvr.edu.mm"

# Behind Cloudflare / a load balancer, set "*" or the proxy IPs. Otherwise the
# activity log records the proxy's IP and country for every visitor, and the
# per-IP rate limiters see a single address for the whole internet.
TRUSTED_PROXIES="*"
```

`APP_DEBUG=false` is the single most important line here. The API exception
handler already forces safe JSON, but the non-API surface and Laravel's own
error pages still depend on it.

Generate a fresh key on the server — never reuse the development one:

```bash
php artisan key:generate
```

## 2. CORS — currently wide open

`config/cors.php` ships with `'allowed_origins' => ['*']`. Before going live,
restrict it to the real frontend origin:

```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
```

## 3. Build caches (do this on every deploy, after pulling)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run `php artisan config:clear` locally afterwards if you share the checkout —
cached config ignores later `.env` edits.

## 4. Enable OPcache

Framework boot dominates request time without it. In `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   ; production only; requires a reload on deploy
```

## 5. Serve behind nginx + php-fpm

Do not use `php artisan serve` in production — it is single-threaded and
handles one request at a time. Size the php-fpm pool to the CPU count
(`pm.max_children` ≈ 4× cores as a starting point).

Point the document root at `backend/public`, and serve `public/uploads` directly
through nginx so student photos never hit PHP.

## 6. Writable paths

```bash
chmod -R 775 storage bootstrap/cache public/uploads
```

## 7. Uploaded files

Student photos are written to `public/uploads/student-photos/`. Make sure this
directory survives deploys (symlink it to persistent storage if you deploy by
replacing the directory), and back it up with the database.

## 7a. Contact form mailbox

The contact form emails `ACVR_SUPPORT_EMAIL`. Two things must be true or
messages vanish silently:

1. `support@acvr.edu.mm` must be a real mailbox with an MX record.
2. The SMTP account in `MAIL_USERNAME` must be allowed to send. It sends *as*
   itself with the visitor as `Reply-To` — do not set `MAIL_FROM_ADDRESS` to
   `support@acvr.edu.mm` unless that address is a verified alias on the SMTP
   provider, or every message will be rejected.

Send one test message from `/contact` after the first deploy and confirm it
arrives, including in spam.

## 7b. Activity log and IP geolocation

`user_activity_logs` is populated automatically — no cron, no queue. The country
for each sign-in comes from a free lookup (`ip-api.com`), cached 24 hours per IP
with a 2-second timeout, and the whole call is fail-open: a missing country
never blocks a sign-in.

- On a server with no outbound internet, set `ACVR_GEO_LOOKUP=false`; the log
  then shows `Unknown` instead of waiting on DNS.
- The log is read-only and visible to Super Admins only. There is no retention
  policy — add one (e.g. `DELETE FROM user_activity_logs WHERE created_at <
  NOW() - INTERVAL 12 MONTH`) once the table is large.

## 8. Scale notes

- The verification lookup is indexed on `(university_id, graduation_year)`.
  Measured at 50,000 students: ~8 ms with the index vs ~40 ms without.
- Name matching uses leading-wildcard `LIKE`, which cannot use an index. If a
  single university grows past a few hundred thousand records, move name
  matching to a MySQL `FULLTEXT` index or a search service.
- Rate limits are defined in `app/Providers/RouteServiceProvider.php` and key on
  the **account** (or user id) rather than the IP, so shared office connections
  are not collectively locked out. Tune the numbers there, not in the routes.
- Sessions/cache default to the filesystem. With more than one web node, move
  `CACHE_DRIVER` and the rate limiter to Redis — otherwise limits are counted
  per node.

## 9. Post-deploy smoke test

```bash
curl -s https://backend.mmcertify.com/api/universities/search?q=Tech   # 200 + JSON
curl -s -o /dev/null -w '%{http_code}\n' https://backend.mmcertify.com/api/users  # must be 401
curl -s https://backend.mmcertify.com/api/not-a-route                  # JSON 404, no stack trace
```

The third one is the `APP_DEBUG` check: if you see an HTML error page with file
paths, `APP_DEBUG` is still on.
