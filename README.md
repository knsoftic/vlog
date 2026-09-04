# PineCast TV — Vlogging CMS, Analytics & Monetization Platform

A Laravel 12 + MySQL vlogging / content-publishing platform with a complete admin panel:
content (vlogs, articles, categories, tags, authors, media), first-party analytics, Google Analytics 4,
Google Search Console, Google AdSense (Management API reporting + policy-safe ad placement), SEO tooling,
role-based users, audit/security/system logs, backups, cookie consent (Consent Mode v2) and report exports.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `openssl`, `zip`, `curl`
- MySQL 8 / MariaDB 10.4+
- Composer 2, Node 18+ (for building assets)

## Quick start (XAMPP / local)

```bash
composer install
cp .env.example .env          # already done in this checkout
php artisan key:generate
# set DB_* in .env (database vlog_cms is used by default)
php artisan migrate --seed    # creates schema, roles, legal pages, ad slots, sample content
php artisan storage:link
npm install && npm run build
php artisan serve             # http://127.0.0.1:8000
```

Default admin (change immediately): `admin@pinecasttv.com` / `ChangeMe12345!`
(override with `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD` in `.env` before seeding).

Admin panel: `/admin`

### Scheduler (required for scheduled publishing, aggregation, syncs, backups, retention)

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Locally: `php artisan schedule:work`. Jobs: `posts:publish-scheduled` (every minute), `analytics:aggregate` (10 min),
`analytics:retention` (daily 03:00), `google:sync all` (every 6 h), `links:check` (weekly), `backup:run auto` (daily/weekly).
Dashboards also lazily re-aggregate "today" when opened, so small sites work without cron.

## Google integrations

1. Google Cloud Console → create an OAuth 2.0 **Web application** client.
   Enable **AdSense Management API** and **Google Search Console API**.
2. Redirect URI: `https://your-domain/admin/google/callback`.
3. Admin → Settings → Google Integrations → enter Client ID / Secret (secret is stored encrypted).
4. Connect AdSense (Monetization → Settings) and Search Console (SEO → Search Console). Tokens are encrypted at rest.
5. GA4: Settings → Analytics → Measurement ID. Events sent: page_view (once, via gtag config), session_start / user_engagement
   (gtag automatic + engagement pings), scroll, video_start, video_progress, video_complete, search, share, outbound_click.

All AdSense / Search Console figures shown in the panel come from the authorised APIs. If not connected or not yet synced the
UI shows **"Data unavailable / sync pending"** — nothing is estimated locally.

## Monetization & AdSense policy guardrails

- `/ads.txt` is served dynamically from Monetization → Ads.txt (validated, with last-updated date).
- Ad slots (header, in-article, between content, sidebar, below content, related, footer) are managed centrally with
  per-device on/off. Ads are labelled, given reserved space (no CLS), kept away from navigation / play button, and are
  not shown on thin pages, search pages, previews, error pages, to bots, or to logged-in admins.
- Ad code containing auto-click, auto-refresh, popup or hidden-ad logic is rejected. Click-encouraging labels are rejected.
- Cookie consent with Google Consent Mode v2; in EEA/UK/CH (and unknown locations) ad/analytics storage waits for consent.
  For those regions Google requires a **certified CMP**: paste its script under Settings → Cookie Consent → External CMP.
- Monetization → Policy Checklist runs a pre-launch review. Approval is never guaranteed.

## Analytics & privacy

- First-party analytics identifies visitors by a random cookie; IPs are never stored as identifiers (a salted daily hash on
  sessions is cleared after 7 days). Bots/crawlers are classified and kept out of human metrics; Googlebot is never blocked.
- Country from CDN headers (Cloudflare `CF-IPCountry` etc.) or an optional MaxMind DB (`GEOIP_DATABASE` in `.env`).
- Retention (Settings → Analytics): raw rows deleted, log IPs anonymised, consents purged by `analytics:retention`.
- Three impression types are always shown in separate cards: **Website Page Views**, **Google Search Impressions**,
  **AdSense Ad Impressions**.

## Roles

Super Admin, Admin, Editor, Author, SEO Manager (editable matrix under Users → Permissions).

## Tests

```bash
php artisan test
```

Covers public pages, SEO endpoints, tracking beacons + aggregation, consent, bot filtering, login lockout, every admin
screen, post CRUD / scheduling / trash, AdSense setting validation, ads.txt validation, thin-page noindex, redirects,
upload rejection and scheduled commands.

## Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, strong `APP_KEY`, HTTPS.
- Point the web root at `public/`. Run `php artisan config:cache route:cache view:cache`.
- Configure cron (above), `QUEUE_CONNECTION=database` worker or rely on the scheduled `queue:work` tick.
- Put `/storage` behind a CDN for video delivery if needed (Settings → Performance → CDN URL).
- Run Monetization → Policy Checklist and SEO → Overview before applying to AdSense.
