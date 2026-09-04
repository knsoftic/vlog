# VlogHub — aaPanel par Deploy karne ki Step-by-Step Guide

Yeh guide aaPanel (Linux server, Nginx + PHP 8.2 + MySQL) par VlogHub ko production mein chalane ke liye hai.
Har step ko order mein follow karein.

---

## 0. Zaroori cheezein

| Item | Value |
|---|---|
| Server | Ubuntu 22.04 / Debian 12 (1 vCPU, 2 GB RAM minimum) |
| aaPanel | Latest (free version kafi hai) |
| PHP | 8.2 (extensions: `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `zip`, `curl`, `exif`, `intl` optional) |
| Database | MySQL 8.0 ya MariaDB 10.6+ |
| Web server | Nginx |
| Domain | e.g. `example.com` (DNS A record server IP par point kare) |
| Repo | `https://github.com/knsoftic/vlog.git` |

---

## 1. aaPanel install karein (agar pehle se nahi hai)

SSH se server par login karein aur run karein:

```bash
URL=https://www.aapanel.com/script/install_7.0_en.sh && if [ -f $URL ];then rm -f $URL;fi && wget -O install_7.0_en.sh "$URL" && sudo bash install_7.0_en.sh aapanel
```

Install ke end mein panel URL, username aur password milega. Browser mein panel kholein.

---

## 2. Software install karein (App Store → One-click)

aaPanel → **App Store** mein yeh install karein:

1. **Nginx** (1.24+)
2. **MySQL** (8.0) ya **MariaDB** (10.6+)
3. **PHP 8.2**
4. PHP 8.2 → **Settings → Install extensions**: `fileinfo`, `opcache`, `exif`, `intl`, `imagemagick` (optional), `redis` (optional)
5. PHP 8.2 → **Settings → Disabled functions**: is list se `proc_open`, `putenv`, `symlink`, `exec` ko **remove** karein (Laravel/Composer ko chahiye)
6. PHP 8.2 → **Settings → Configuration**:
   - `upload_max_filesize = 512M`
   - `post_max_size = 512M`
   - `memory_limit = 512M`
   - `max_execution_time = 300`
7. **Composer** aur **Node.js** (App Store → "Node.js Version Manager" → Node 20 install)

Terminal se check karein:

```bash
/www/server/php/82/bin/php -v
composer --version
node -v && npm -v
```

Agar `composer` nahi mila:

```bash
curl -sS https://getcomposer.org/installer | /www/server/php/82/bin/php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 3. Database banayein

aaPanel → **Database** → **Add database**:

- Database name: `vlog_cms`
- Username: `vlog_cms`
- Password: (strong password, copy kar lein)
- Character set: `utf8mb4`
- Access: `localhost`

---

## 4. Website banayein

aaPanel → **Website** → **Add site**:

- Domain: `example.com` aur `www.example.com`
- Root directory: `/www/wwwroot/example.com`
- PHP version: **PHP-82**
- Database: **No** (step 3 mein ban chuka hai)

Site banne ke baad **abhi root directory badalna hai** (step 6).

---

## 5. Code clone karein

aaPanel → **Terminal** (ya SSH):

```bash
cd /www/wwwroot
rm -rf example.com
git clone https://github.com/knsoftic/vlog.git example.com
cd example.com
```

Dependencies install karein:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

`npm run build` ke baad `public/build/` folder ban jayega (yeh git mein nahi hota, server par hi banta hai).

---

## 6. .env configure karein

```bash
cp .env.example .env
nano .env
```

Yeh values set karein:

```env
APP_NAME="Your Site Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vlog_cms
DB_USERNAME=vlog_cms
DB_PASSWORD=YOUR_DB_PASSWORD

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Pehle admin ke liye (seed se pehle set karein)
ADMIN_NAME="Site Owner"
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=ChooseAStrongPassword123

# Google OAuth (AdSense + Search Console) — admin panel se bhi dal sakte hain
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# Optional: MaxMind GeoLite2 country database
GEOIP_DATABASE=
```

Key generate karein aur database setup karein:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

> `db:seed` sirf pehli baar chalayein. Yeh roles, legal pages, ad slots, home sections, settings aur sample content banata hai. Dobara chalana safe hai (existing data delete nahi hota) lekin zaroori nahi.

Permissions:

```bash
chown -R www:www /www/wwwroot/example.com
chmod -R 775 storage bootstrap/cache
```

---

## 7. Nginx: root ko `public` par set karein

aaPanel → **Website** → example.com → **Site directory**:

- **Running directory**: `/public` select karein → Save
- **Anti-XSS attack** ko off rakhein (open_basedir se Laravel ko masla hota hai) ya `open_basedir` mein project path add karein.

Phir **Website → example.com → Config** (Nginx config) mein `location /` block ko yeh banayein (agar pehle se `try_files` nahi hai):

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

Save karein aur Nginx reload karein (aaPanel automatically reload karta hai).

**URL Rewrite tab** mein "laravel5" preset bhi select kar sakte hain — result same hai.

---

## 8. SSL (HTTPS) lagayein

aaPanel → **Website** → example.com → **SSL** → **Let's Encrypt** → domain select → **Apply**.
Phir **Force HTTPS** on karein.

`.env` mein `APP_URL=https://example.com` hona chahiye (Google OAuth redirect URI HTTPS par hi kaam karta hai).

---

## 9. Cron job (scheduler) — zaroori

aaPanel → **Cron** → **Add task**:

- Type: **Shell script**
- Name: `vloghub-scheduler`
- Execution cycle: **N minutes → 1**
- Script:

```bash
cd /www/wwwroot/example.com && /www/server/php/82/bin/php artisan schedule:run >> /dev/null 2>&1
```

Yeh scheduler har minute chalta hai aur yeh jobs run karta hai:

| Job | Kab | Kaam |
|---|---|---|
| `posts:publish-scheduled` | har minute | scheduled vlogs publish |
| `analytics:aggregate` | har 10 min | dashboard ke liye daily summaries |
| `google:sync all` | har 6 ghante | AdSense + Search Console reports |
| `analytics:retention` | roz 03:00 | purana data delete / IP anonymise |
| `backup:run auto` | roz 02:00 | database (aur media) backup |
| `links:check` | har hafte | broken links |

---

## 10. Cache warm karein (deploy ke baad har baar)

```bash
cd /www/wwwroot/example.com
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Ab `https://example.com` kholein. Home page, `/sitemap.xml`, `/robots.txt`, `/ads.txt` check karein.

---

## 11. Admin panel setup

1. `https://example.com/admin/login` par jayein aur `.env` wale `ADMIN_EMAIL` / `ADMIN_PASSWORD` se login karein.
2. **Profile** → password change karein.
3. **Settings → General/Branding**: site name, logo, favicon, colours.
4. **Settings → Cookie Consent**: EEA/UK traffic ke liye certified CMP script (agar hai).
5. **Settings → Google Integrations**:
   - Google Cloud Console → **APIs & Services → Credentials → Create OAuth client ID → Web application**
   - Authorized redirect URI: `https://example.com/admin/google/callback`
   - **AdSense Management API** aur **Google Search Console API** enable karein
   - Client ID + Secret yahan paste karein → Save
6. **Monetization → Settings**: Publisher ID (`pub-…`) dalein, AdSense enable karein → **Connect with Google**.
7. **Monetization → Ad Units**: AdSense se har unit ka `data-ad-slot` id dalein aur enable karein.
8. **Monetization → Ads.txt**: validate karke save karein → `https://example.com/ads.txt` par check karein.
9. **SEO → Search Console → Connect with Google** → property select → Sync.
10. **Settings → Analytics**: GA4 Measurement ID (optional).
11. **Pages**: Privacy Policy, Cookie Policy, Terms, Disclaimer, About, Contact apne site ke mutabiq edit karein.
12. **Content → Vlogs**: sample posts delete karein aur apna content dalein.
13. **Monetization → Policy Checklist** run karein — sab green hone ke baad AdSense apply karein.

---

## 12. Update / redeploy (naya code aane par)

```bash
cd /www/wwwroot/example.com
php artisan down
git pull
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Migrations backward-safe hain; existing data delete nahi hota.

---

## 13. Backups

- Admin → **Settings → Backup**: automatic database backup on (daily), keep 7.
- Files `storage/app/private/backups/` mein hote hain; admin panel se download/restore.
- aaPanel → **Cron → Backup site / Backup database** se bhi offsite (Google Drive / S3) backup set karein.

---

## 14. Troubleshooting

| Masla | Hal |
|---|---|
| 500 error, blank page | `storage/logs/laravel.log` dekhein; `chmod -R 775 storage bootstrap/cache`; `php artisan optimize:clear` |
| "open_basedir restriction" | aaPanel → Website → Site directory → Anti-XSS off, ya `open_basedir` mein `/www/wwwroot/example.com/:/tmp/` |
| Images 404 (`/storage/...`) | `php artisan storage:link`; aaPanel mein symlink allow ho (Anti-XSS off) |
| CSS/JS load nahi ho raha | `npm run build` chalayein; `public/hot` file delete karein |
| Scheduled post publish nahi hua | Cron job check karein (Admin → Logs → System Health "scheduler running") |
| Google connect error `redirect_uri_mismatch` | Cloud Console mein exact URI `https://example.com/admin/google/callback` add karein |
| AdSense "Data unavailable" | Connect + Sync karein; naye account mein data 24–48h baad aata hai |
| Upload fail (large video) | PHP `upload_max_filesize`/`post_max_size` aur Nginx `client_max_body_size 512m;` badhayein |
| Country "unknown" | Cloudflare proxy on karein (CF-IPCountry header) ya GeoLite2 `.mmdb` ka path `GEOIP_DATABASE` mein dein |

---

## 15. Security checklist (production)

- `APP_DEBUG=false`, `APP_ENV=production`
- Strong admin password, unused users deactivate
- aaPanel ka default port/entry badlein, panel par 2FA on karein
- Firewall: sirf 80/443 (aur SSH) open
- `.env` kabhi git mein commit na karein
- Admin → Logs → Security regularly dekhein
