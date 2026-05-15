# WebBook SaaS — Complete Installation & Server Configuration Guide
**Version 2.0.0 | API Base: `https://2030b.com/webbook/api`**

---

## Table of Contents
1. [System Requirements](#1-system-requirements)
2. [Directory Structure](#2-directory-structure)
3. [Quick Start (TL;DR)](#3-quick-start-tldr)
4. [Server Setup — Apache](#4-server-setup--apache)
5. [Server Setup — Nginx](#5-server-setup--nginx)
6. [PHP Configuration](#6-php-configuration)
7. [File Permissions](#7-file-permissions)
8. [Environment Variables & Secrets](#8-environment-variables--secrets)
9. [config.php Reference](#9-configphp-reference)
10. [SQLite & Data Directory](#10-sqlite--data-directory)
11. [Email Configuration](#11-email-configuration)
12. [CORS Configuration](#12-cors-configuration)
13. [Uploads Configuration](#13-uploads-configuration)
14. [Rate Limiting](#14-rate-limiting)
15. [SSL/TLS (HTTPS)](#15-ssltls-https)
16. [First-Run Verification](#16-first-run-verification)
17. [API Test Suite](#17-api-test-suite)
18. [Production Hardening](#18-production-hardening)
19. [Backup & Maintenance](#19-backup--maintenance)
20. [Troubleshooting](#20-troubleshooting)
21. [Upgrade Guide (v1 → v2)](#21-upgrade-guide-v1--v2)

---

## 1. System Requirements

| Component       | Minimum                    | Recommended                 |
|-----------------|----------------------------|-----------------------------|
| PHP             | 8.1                        | 8.2 or 8.3                  |
| SQLite          | 3.35+                      | 3.40+                       |
| Web Server      | Apache 2.4 / Nginx 1.18+   | Apache 2.4+ / Nginx 1.24+   |
| Disk Space      | 500 MB                     | 5 GB+                       |
| RAM             | 256 MB                     | 1 GB+                       |
| OS              | Linux (any distro)         | Ubuntu 22.04 / Debian 12 LTS|

### Required PHP Extensions

```bash
# Verify all required extensions are loaded
php -m | grep -E 'pdo_sqlite|json|mbstring|fileinfo|openssl|hash|curl'
```

Expected output:
```
fileinfo
hash
json
mbstring
openssl
pdo_sqlite
```

**Install on Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install -y php8.2-sqlite3 php8.2-mbstring php8.2-fileinfo \
                        php8.2-curl php8.2-opcache php8.2-xml
```

**Install on CentOS/RHEL/AlmaLinux:**
```bash
sudo dnf install -y php-pdo php-pdo_sqlite php-mbstring php-fileinfo php-curl php-opcache
```

**Verify SQLite version:**
```bash
php -r "echo (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn() . PHP_EOL;"
# Must be ≥ 3.35.0
```

---

## 2. Directory Structure

```
/var/www/html/webbook/               ← web root (DocumentRoot)
│
├── webbook-saas/                    ← PHP API backend (this package)
│   ├── index.php                    ← single entry-point router
│   ├── bootstrap.php                ← helpers, CORS, rate-limit, DB utils
│   ├── config.php                   ← ALL constants (edit this file)
│   ├── .htaccess                    ← Apache rewrite rules + security
│   │
│   ├── lib/
│   │   ├── MainDB.php               ← main SQLite schema + helpers
│   │   └── BookDB.php               ← per-book SQLite schema + helpers
│   │
│   ├── routes/
│   │   ├── auth.php                 ← register/login/logout/profile
│   │   ├── books.php                ← books CRUD + analytics
│   │   ├── chapters.php             ← markdown chapters CRUD + revisions
│   │   ├── sync.php                 ← IndexedDB push/pull
│   │   ├── public.php               ← public endpoints (no auth)
│   │   ├── stats.php                ← author statistics dashboard
│   │   ├── verify.php               ← email verify + password reset
│   │   ├── upload.php               ← media file uploads
│   │   ├── webhooks.php             ← webhook management + dispatch
│   │   └── admin.php                ← admin endpoints
│   │
│   ├── schema/
│   │   ├── main.sql                 ← main.sqlite DDL reference
│   │   └── book.sql                 ← per-book SQLite DDL reference
│   │
│   └── data/                        ← ⚠️ NEVER web-accessible
│       ├── main.sqlite              ← users, sessions, books registry
│       ├── books/                   ← {bookId}.sqlite per book
│       ├── uploads/                 ← user-uploaded media files
│       │   └── {userId}/            ← per-user upload directory
│       ├── markdown/                ← legacy markdown storage
│       ├── rate/                    ← rate-limit counter files
│       └── error.log                ← PHP error log
│
├── webbook-v7.html                  ← reader frontend (latest)
├── webbook-api-test.html            ← interactive API test suite
└── book/                            ← static markdown content
```

---

## 3. Quick Start (TL;DR)

```bash
# 1. Clone / upload files to server
cd /var/www/html/webbook

# 2. Create data directories
mkdir -p webbook-saas/data/{books,uploads,markdown,rate}
chown -R www-data:www-data webbook-saas/data/
chmod -R 750 webbook-saas/data/

# 3. Set secrets (see Section 8 for details)
export WB_TOKEN_SECRET=$(openssl rand -hex 32)
export WB_WEBHOOK_SECRET=$(openssl rand -hex 16)

# 4. Configure Apache or Nginx (see Sections 4/5)
# 5. Enable HTTPS (see Section 15)
# 6. Verify: GET https://yourdomain.com/webbook/api/
```

---

## 4. Server Setup — Apache

### 4.1 Enable Required Modules

```bash
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2
```

### 4.2 VirtualHost Configuration

Create `/etc/apache2/sites-available/webbook.conf`:

```apache
# ── HTTP → redirect to HTTPS ──────────────────────────────
<VirtualHost *:80>
    ServerName 2030b.com
    ServerAlias www.2030b.com

    RewriteEngine On
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]

    ErrorLog  /var/log/apache2/webbook_error.log
    CustomLog /var/log/apache2/webbook_access.log combined
</VirtualHost>

# ── HTTPS ─────────────────────────────────────────────────
<VirtualHost *:443>
    ServerName 2030b.com
    ServerAlias www.2030b.com
    DocumentRoot /var/www/html/webbook

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/2030b.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/2030b.com/privkey.pem
    SSLProtocol           all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite        HIGH:!aNULL:!MD5

    # ── Security headers ──────────────────────────────────
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Content-Type-Options    "nosniff"
    Header always set X-Frame-Options           "SAMEORIGIN"
    Header always set Referrer-Policy           "strict-origin-when-cross-origin"
    Header always set Permissions-Policy        "camera=(), microphone=(), geolocation=()"
    Header always unset X-Powered-By

    # ── Web root ──────────────────────────────────────────
    <Directory /var/www/html/webbook>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # ── CRITICAL: Block data/ directory entirely ──────────
    <Directory /var/www/html/webbook/webbook-saas/data>
        Require all denied
    </Directory>

    # ── API directory ─────────────────────────────────────
    <Directory /var/www/html/webbook/webbook-saas>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    # ── Serve uploads via separate alias ──────────────────
    Alias /webbook/uploads/ /var/www/html/webbook/webbook-saas/data/uploads/
    <Directory /var/www/html/webbook/webbook-saas/data/uploads/>
        Options -Indexes
        AllowOverride None
        Require all granted
        # Only allow image types
        <FilesMatch "\.(jpg|jpeg|png|webp|gif|svg)$">
            Require all granted
        </FilesMatch>
        <FilesMatch "^((?!\.(jpg|jpeg|png|webp|gif|svg)$).)*$">
            Require all denied
        </FilesMatch>
    </Directory>

    # ── Environment variables (secrets) ───────────────────
    # Generate these: openssl rand -hex 32
    SetEnv WB_TOKEN_SECRET    "REPLACE_WITH_64_CHAR_HEX_SECRET"
    SetEnv WB_WEBHOOK_SECRET  "REPLACE_WITH_32_CHAR_HEX_SECRET"
    SetEnv WB_MAIL_FROM       "noreply@2030b.com"
    SetEnv WB_MAIL_ENABLED    "1"
    # Optional SMTP (see Section 11):
    # SetEnv SMTP_HOST        "smtp.sendgrid.net"
    # SetEnv SMTP_USER        "apikey"
    # SetEnv SMTP_PASS        "SG.xxxxx"

    ErrorLog  /var/log/apache2/webbook_error.log
    CustomLog /var/log/apache2/webbook_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite webbook.conf
sudo apache2ctl configtest     # must say "Syntax OK"
sudo systemctl reload apache2
```

### 4.3 The `.htaccess` File (already included)

The `webbook-saas/.htaccess` handles:
- Rewriting all `/webbook/api/*` requests → `index.php`
- Blocking direct access to `.sqlite`, `.sql`, `.log`, `.env`, `.json`
- Blocking `data/` directory traversal
- Security headers
- PHP upload/memory limits

---

## 5. Server Setup — Nginx

### 5.1 Install PHP-FPM

```bash
sudo apt-get install -y php8.2-fpm
sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm
```

### 5.2 Nginx Server Block

Create `/etc/nginx/sites-available/webbook`:

```nginx
# ── HTTP → HTTPS redirect ──────────────────────────────────
server {
    listen 80;
    listen [::]:80;
    server_name 2030b.com www.2030b.com;

    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://$host$request_uri; }
}

# ── HTTPS main server ──────────────────────────────────────
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name 2030b.com www.2030b.com;

    root  /var/www/html/webbook;
    index index.html index.php;

    # ── SSL ──────────────────────────────────────────────
    ssl_certificate     /etc/letsencrypt/live/2030b.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/2030b.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_stapling        on;
    ssl_stapling_verify on;

    # ── Security headers ──────────────────────────────────
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options    "nosniff" always;
    add_header X-Frame-Options           "SAMEORIGIN" always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy        "camera=(), microphone=(), geolocation=()" always;

    # ── BLOCK: sensitive files ─────────────────────────────
    location ~* \.(sqlite|sql|log|env|sh)$ {
        deny all;
        return 403;
    }

    # ── BLOCK: data/ directory ─────────────────────────────
    location ~ /webbook-saas/data/ {
        deny all;
        return 403;
    }

    # ── API: /webbook/api/* → webbook-saas/index.php ──────
    location /webbook/api/ {
        # Map the URL to the filesystem
        root  /var/www/html/webbook;
        # Strip /webbook/api prefix, serve from webbook-saas/
        rewrite ^/webbook/api/(.*)$ /webbook-saas/$1 break;

        try_files $uri $uri/ /webbook-saas/index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
            fastcgi_index  index.php;
            fastcgi_param  SCRIPT_FILENAME /var/www/html/webbook$fastcgi_script_name;
            include        fastcgi_params;

            # ── Secrets via FastCGI params ─────────────
            fastcgi_param WB_TOKEN_SECRET    "REPLACE_WITH_64_CHAR_HEX_SECRET";
            fastcgi_param WB_WEBHOOK_SECRET  "REPLACE_WITH_32_CHAR_HEX_SECRET";
            fastcgi_param WB_MAIL_FROM       "noreply@2030b.com";
            fastcgi_param WB_MAIL_ENABLED    "1";
            # Optional SMTP:
            # fastcgi_param SMTP_HOST        "smtp.sendgrid.net";
            # fastcgi_param SMTP_USER        "apikey";
            # fastcgi_param SMTP_PASS        "SG.xxxxx";

            fastcgi_read_timeout 30s;
            fastcgi_buffer_size  16k;
            fastcgi_buffers      4 16k;
        }
    }

    # ── User uploads: /webbook/uploads/{userId}/{file} ────
    location /webbook/uploads/ {
        alias /var/www/html/webbook/webbook-saas/data/uploads/;
        expires 7d;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options "nosniff" always;

        # Only serve allowed image types
        location ~* \.(jpg|jpeg|png|webp|gif|svg)$ {
            try_files $uri =404;
        }
        # Deny everything else
        location ~ . {
            deny all;
            return 403;
        }
    }

    # ── Static assets with caching ────────────────────────
    location ~* \.(html|js|css|woff2|woff|ttf|ico|png|jpg|jpeg|webp|svg|json)$ {
        try_files $uri =404;
        expires 1d;
        add_header Cache-Control "public";
    }

    # ── Default: serve static files ───────────────────────
    location / {
        try_files $uri $uri/ =404;
    }

    # ── Logs ──────────────────────────────────────────────
    error_log  /var/log/nginx/webbook_error.log;
    access_log /var/log/nginx/webbook_access.log;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/webbook /etc/nginx/sites-enabled/
sudo nginx -t                  # must say "test is successful"
sudo systemctl reload nginx
```

### 5.3 Alternative: Simple Nginx Proxy to Apache

If you're running Apache on a non-standard port (8080) behind Nginx:

```nginx
location /webbook/api/ {
    proxy_pass         http://127.0.0.1:8080/webbook/api/;
    proxy_set_header   Host $host;
    proxy_set_header   X-Real-IP $remote_addr;
    proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header   X-Forwarded-Proto $scheme;
    proxy_read_timeout 30s;
}
```

---

## 6. PHP Configuration

### 6.1 Recommended `php.ini` Settings

Edit `/etc/php/8.2/fpm/php.ini` (for Nginx+FPM) or `/etc/php/8.2/apache2/php.ini`:

```ini
; ── Execution limits ──────────────────────────────────────
max_execution_time   = 30
max_input_time       = 30
memory_limit         = 128M

; ── Upload (for /upload/cover and /upload/avatar) ─────────
file_uploads         = On
upload_max_filesize  = 10M
post_max_size        = 12M
max_file_uploads     = 5

; ── Security ──────────────────────────────────────────────
display_errors       = Off
display_startup_errors = Off
log_errors           = On
error_log            = /var/www/html/webbook/webbook-saas/data/error.log
expose_php           = Off
allow_url_fopen      = Off       ; keep On if you need webhook outbound via file_get_contents
session.cookie_secure = On
session.cookie_httponly = On

; ── SQLite WAL mode ────────────────────────────────────────
default_socket_timeout = 60

; ── OPcache (strongly recommended) ───────────────────────
opcache.enable                = 1
opcache.enable_cli            = 0
opcache.memory_consumption    = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps   = 0    ; set to 1 in development
opcache.fast_shutdown         = 1
opcache.interned_strings_buffer = 16

; ── Timezone ──────────────────────────────────────────────
date.timezone = UTC
```

### 6.2 PHP-FPM Pool (Nginx, high traffic)

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
[www]
user  = www-data
group = www-data

listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode  = 0660

; Process manager: dynamic for most setups
pm                   = dynamic
pm.max_children      = 20
pm.start_servers     = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests      = 500

; Slowlog (optional, debug only)
; slowlog = /var/log/php8.2-fpm.log.slow
; request_slowlog_timeout = 5s

; Environment variables passed to PHP workers
env[WB_TOKEN_SECRET]    = REPLACE_WITH_64_CHAR_HEX_SECRET
env[WB_WEBHOOK_SECRET]  = REPLACE_WITH_32_CHAR_HEX_SECRET
env[WB_MAIL_FROM]       = noreply@2030b.com
env[WB_MAIL_ENABLED]    = 1
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 7. File Permissions

```bash
# Set ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/html/webbook/webbook-saas/

# PHP files: read-only for web server
sudo find /var/www/html/webbook/webbook-saas -name "*.php" -exec chmod 644 {} \;
sudo find /var/www/html/webbook/webbook-saas -name "*.sql" -exec chmod 640 {} \;

# data/ directories: writable by web server only, not world-readable
sudo chmod 750 /var/www/html/webbook/webbook-saas/data/
sudo chmod 750 /var/www/html/webbook/webbook-saas/data/books/
sudo chmod 750 /var/www/html/webbook/webbook-saas/data/uploads/
sudo chmod 750 /var/www/html/webbook/webbook-saas/data/markdown/
sudo chmod 750 /var/www/html/webbook/webbook-saas/data/rate/

# config.php: read only
sudo chmod 640 /var/www/html/webbook/webbook-saas/config.php

# .htaccess: readable
sudo chmod 644 /var/www/html/webbook/webbook-saas/.htaccess

# Verify data/ is NOT accessible from web (must return 403)
curl -o /dev/null -w "%{http_code}\n" -s https://2030b.com/webbook-saas/data/main.sqlite
# Expected: 403
```

---

## 8. Environment Variables & Secrets

**All secrets must be provided via environment variables.** Never hardcode them in `config.php`.

### 8.1 Required Variables

| Variable           | Description                                       | Example                                |
|--------------------|---------------------------------------------------|----------------------------------------|
| `WB_TOKEN_SECRET`  | HMAC-SHA256 key for signing auth tokens           | 64-char hex (256-bit)                  |
| `WB_WEBHOOK_SECRET`| Default secret for webhook payload signatures     | 32-char hex                            |
| `WB_MAIL_FROM`     | Sender email for verification/reset emails        | `noreply@2030b.com`                    |
| `WB_MAIL_ENABLED`  | `1` = send real emails, `0` = log to error.log    | `1`                                    |

### 8.2 Optional Variables (SMTP)

| Variable   | Description           | Example                    |
|------------|-----------------------|----------------------------|
| `SMTP_HOST`| SMTP relay hostname   | `smtp.sendgrid.net`        |
| `SMTP_USER`| SMTP username/key     | `apikey`                   |
| `SMTP_PASS`| SMTP password/API key | `SG.xxxxxxxxxxxxxxxxx`     |
| `SMTP_PORT`| SMTP port             | `587`                      |

### 8.3 Generate Secure Secrets

```bash
# WB_TOKEN_SECRET — 64 hex chars = 256-bit key
openssl rand -hex 32
# Example: a3f8d2e1c7b4a9f5e2d8c3b1a7e4f9d2c8b5a3f7e1d4c9b2a8f5e3d7c1b4a9f6

# WB_WEBHOOK_SECRET — 32 hex chars
openssl rand -hex 16
# Example: 8f3a1b2c4d5e6f7a9b0c1d2e3f4a5b6c
```

### 8.4 Setting Variables — Apache

In your VirtualHost `<VirtualHost *:443>` block:
```apache
SetEnv WB_TOKEN_SECRET   "a3f8d2e1c7b4a9f5e2d8c3b1a7e4f9d2..."
SetEnv WB_WEBHOOK_SECRET "8f3a1b2c4d5e6f7a..."
SetEnv WB_MAIL_FROM      "noreply@2030b.com"
SetEnv WB_MAIL_ENABLED   "1"
```

### 8.5 Setting Variables — Nginx + PHP-FPM

**Option A:** In the Nginx `fastcgi_param` block (per-server):
```nginx
fastcgi_param WB_TOKEN_SECRET   "a3f8d2e1...";
fastcgi_param WB_WEBHOOK_SECRET "8f3a1b2c...";
```

**Option B:** In PHP-FPM pool config (recommended — applies to all PHP):
```ini
# /etc/php/8.2/fpm/pool.d/www.conf
env[WB_TOKEN_SECRET]   = a3f8d2e1c7b4a9f5e2d8c3b1a7e4f9d2...
env[WB_WEBHOOK_SECRET] = 8f3a1b2c4d5e6f7a...
env[WB_MAIL_FROM]      = noreply@2030b.com
env[WB_MAIL_ENABLED]   = 1
```

### 8.6 Using a `.env` File (Development / Simple Setups)

Create `/var/www/html/webbook/webbook-saas/.env`:
```dotenv
WB_TOKEN_SECRET=a3f8d2e1c7b4a9f5e2d8c3b1a7e4f9d2c8b5a3f7e1d4c9b2a8f5e3d7c1b4a9f6
WB_WEBHOOK_SECRET=8f3a1b2c4d5e6f7a9b0c1d2e3f4a5b6c
WB_MAIL_FROM=noreply@2030b.com
WB_MAIL_ENABLED=0
```

Add this loader at the **top of `config.php`** (before any `define()`):
```php
// Load .env file (development / simple servers only)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (!getenv(trim($k))) putenv(trim($k) . '=' . trim($v));
    }
}
```

**Block `.env` from web access** (add to `.htaccess`):
```apache
<Files ".env">
    Require all denied
</Files>
```

And in Nginx:
```nginx
location ~ /\.env { deny all; return 404; }
```

---

## 9. `config.php` Reference

This is the master configuration file. All constants are defined here:

```php
<?php
// ── Version & Base URL ─────────────────────────────────
define('WB_VERSION',   '2.0.0');
define('WB_API_BASE',  'https://2030b.com/webbook/api');   // ← YOUR DOMAIN
define('WB_APP_NAME',  'WebBook SaaS');
define('WB_APP_URL',   'https://2030b.com');               // ← YOUR DOMAIN

// ── Filesystem Paths ───────────────────────────────────
define('WB_ROOT',      __DIR__);                           // API root dir
define('WB_DATA',      WB_ROOT . '/data');                 // data dir (NOT web-accessible)
define('WB_DB_MAIN',   WB_DATA . '/main.sqlite');          // main database
define('WB_DB_BOOKS',  WB_DATA . '/books');                // per-book SQLite files
define('WB_MD_ROOT',   WB_DATA . '/markdown');             // legacy markdown
define('WB_UPLOADS',   WB_DATA . '/uploads');              // user-uploaded media

// ── Security Secrets (from environment) ───────────────
define('WB_TOKEN_SECRET',   getenv('WB_TOKEN_SECRET')   ?: 'CHANGE_THIS_SECRET_IN_ENV');
define('WB_TOKEN_TTL',      60 * 60 * 24 * 30);          // 30 days
define('WB_VERIFY_TOKEN_TTL', 60 * 60 * 24 * 3);         // 3 days
define('WB_RESET_TOKEN_TTL',  60 * 60 * 2);              // 2 hours
define('WB_WEBHOOK_SECRET', getenv('WB_WEBHOOK_SECRET') ?: 'CHANGE_WEBHOOK_SECRET');

// ── Plan Limits ────────────────────────────────────────
define('WB_PLAN_LIMITS', [
    'free'       => ['books' => 3,   'chapters' => 30,  'upload_mb' => 5],
    'pro'        => ['books' => 50,  'chapters' => 500, 'upload_mb' => 100],
    'enterprise' => ['books' => 999, 'chapters' => 9999,'upload_mb' => 1000],
]);

// ── Caps ───────────────────────────────────────────────
define('WB_MAX_BOOKS',    50);              // platform max
define('WB_MAX_MD_SIZE',  512 * 1024);     // 512 KB per chapter
define('WB_MAX_SYNC_ROWS', 5000);          // max rows per sync push

// ── Uploads ────────────────────────────────────────────
define('WB_UPLOAD_MAX_BYTES', 10 * 1024 * 1024);  // 10 MB per file
define('WB_UPLOAD_ALLOWED', [
    'image/jpeg', 'image/png', 'image/webp',
    'image/gif',  'image/svg+xml',
]);
define('WB_UPLOAD_URL_BASE', 'https://2030b.com/webbook/uploads');  // ← YOUR DOMAIN

// ── CORS ───────────────────────────────────────────────
define('WB_ALLOWED_ORIGINS', [
    'https://2030b.com',
    'https://www.2030b.com',
    // 'http://localhost:3000',  // development only
    // '*',                      // REMOVE IN PRODUCTION
]);

// ── Rate Limiting ──────────────────────────────────────
define('WB_RATE_LIMIT',  120);   // max requests per window
define('WB_RATE_WINDOW', 60);    // window in seconds
define('WB_RATE_DIR',    WB_DATA . '/rate');

// ── Webhooks ───────────────────────────────────────────
define('WB_WEBHOOK_EVENTS', [
    'book.created', 'book.published', 'book.archived', 'book.deleted',
    'reader.sync', 'reader.first_visit',
]);
define('WB_WEBHOOK_TIMEOUT', 10);  // seconds

// ── Email ──────────────────────────────────────────────
define('WB_MAIL_FROM',    getenv('WB_MAIL_FROM')    ?: 'noreply@2030b.com');
define('WB_MAIL_ENABLED', (bool)(getenv('WB_MAIL_ENABLED') ?: false));
```

### Key Settings to Change for Your Server

| Constant           | Value to Change                        |
|--------------------|----------------------------------------|
| `WB_API_BASE`      | Your domain: `https://yourdomain.com/webbook/api` |
| `WB_APP_URL`       | Your domain: `https://yourdomain.com`  |
| `WB_UPLOAD_URL_BASE` | `https://yourdomain.com/webbook/uploads` |
| `WB_ALLOWED_ORIGINS` | Your frontend domains (remove `*`)   |
| `WB_TOKEN_SECRET`  | Via env var (never hardcode)           |
| `WB_WEBHOOK_SECRET`| Via env var (never hardcode)           |

---

## 10. SQLite & Data Directory

### 10.1 Initialize Data Directories

`bootstrap.php` auto-creates directories on first request, but you can pre-create them:

```bash
cd /var/www/html/webbook/webbook-saas/
mkdir -p data/{books,uploads,markdown,rate}
chown -R www-data:www-data data/
chmod -R 750 data/
```

### 10.2 Database Files

| File                           | Contains                                      |
|--------------------------------|-----------------------------------------------|
| `data/main.sqlite`             | users, sessions, books registry, webhooks     |
| `data/books/{bookId}.sqlite`   | chapters, reader data, analytics per book     |

### 10.3 SQLite WAL Mode

All databases use **WAL (Write-Ahead Logging)** mode, configured automatically by `wb_init_main_db()` and `wb_init_book_db()`. Benefits:
- Concurrent reads without blocking writes
- Better performance under load
- Automatic checkpointing

### 10.4 Manual Database Initialization

If the first request fails due to permissions, initialize manually:

```bash
php -r "
require_once '/var/www/html/webbook/webbook-saas/bootstrap.php';
\$db = wb_init_main_db();
echo 'Main DB initialized: ' . WB_DB_MAIN . PHP_EOL;
"
```

### 10.5 SQLite Tuning for Production

```sql
-- These are set automatically by bootstrap.php
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA synchronous = NORMAL;   -- good balance of safety/speed
-- Optional for busy servers (run manually):
PRAGMA cache_size = -10000;    -- 10MB page cache
PRAGMA temp_store = MEMORY;    -- temp tables in RAM
```

---

## 11. Email Configuration

### 11.1 Default: PHP `mail()` Function

Requires a working MTA (Postfix, Sendmail, etc.) on the server.

```bash
# Install Postfix on Ubuntu
sudo apt-get install -y postfix mailutils
sudo systemctl enable postfix

# Test email delivery
echo "Test from WebBook" | mail -s "WebBook Test Email" your@email.com

# Check mail log
sudo tail -f /var/log/mail.log
```

Set in environment:
```
WB_MAIL_FROM=noreply@2030b.com
WB_MAIL_ENABLED=1
```

### 11.2 SMTP Relay via PHPMailer (Recommended for Production)

```bash
cd /var/www/html/webbook/webbook-saas/
composer require phpmailer/phpmailer
```

Replace `_send_email()` in `routes/verify.php` with:

```php
function _send_email(string $to, string $subject, string $text, string $html = ''): bool
{
    if (!WB_MAIL_ENABLED) {
        error_log("[WB-MAIL] DISABLED — Would send to {$to}: {$subject}");
        return true;
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.sendgrid.net';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: 'apikey';
        $mail->Password   = getenv('SMTP_PASS') ?: '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(WB_MAIL_FROM, WB_APP_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html ?: nl2br(htmlspecialchars($text));
        $mail->AltBody = strip_tags($text);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[WB-MAIL] SMTP error: ' . $e->getMessage());
        return false;
    }
}
```

### 11.3 Popular SMTP Providers

| Provider   | Host                  | Port | Auth         |
|------------|-----------------------|------|--------------|
| SendGrid   | `smtp.sendgrid.net`   | 587  | `apikey` / API Key |
| AWS SES    | `email-smtp.us-east-1.amazonaws.com` | 587 | SMTP credentials |
| Mailgun    | `smtp.mailgun.org`    | 587  | SMTP credentials |
| Gmail      | `smtp.gmail.com`      | 587  | Email / App Password |
| Postmark   | `smtp.postmarkapp.com`| 587  | API Token    |

### 11.4 Disable Emails (Development)

```bash
WB_MAIL_ENABLED=0
```
All emails will be logged to `data/error.log` instead of being sent.

---

## 12. CORS Configuration

Edit `config.php` to set allowed origins:

```php
// Production: only your domain(s)
define('WB_ALLOWED_ORIGINS', [
    'https://2030b.com',
    'https://www.2030b.com',
    // 'https://staging.2030b.com',  // staging (optional)
]);

// Development: add localhost variants
define('WB_ALLOWED_ORIGINS', [
    'https://2030b.com',
    'https://www.2030b.com',
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8080',
    // '*',   ← ONLY for testing, REMOVE in production
]);
```

### CORS Headers Sent (by `bootstrap.php`)

```
Access-Control-Allow-Origin:  <matched origin or https://2030b.com>
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-WB-Book, X-WB-Reader
Access-Control-Max-Age:       86400
Vary:                         Origin
```

### Test CORS Preflight

```bash
curl -v -X OPTIONS https://2030b.com/webbook/api/auth/login \
  -H "Origin: https://2030b.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization"
# Must see: HTTP/2 204 and Access-Control-Allow-Origin header
```

---

## 13. Uploads Configuration

### 13.1 How Uploads Work

1. Client sends `multipart/form-data` to `POST /upload/cover` or `POST /upload/avatar`
2. Server validates: MIME type (finfo), file size, user plan quota
3. File saved to `data/uploads/{userId}/{cover|avatar}-{uuid}.{ext}`
4. URL returned: `https://2030b.com/webbook/uploads/{userId}/{filename}`
5. `cover_url` / `avatar_url` updated in database

### 13.2 Upload Limits (config.php)

```php
define('WB_UPLOAD_MAX_BYTES', 10 * 1024 * 1024);  // 10 MB per file
define('WB_UPLOAD_ALLOWED', [
    'image/jpeg', 'image/png', 'image/webp',
    'image/gif',  'image/svg+xml',
]);
// Per-plan storage quotas:
// free:       5 MB total
// pro:       100 MB total
// enterprise: 1 GB total
```

### 13.3 Serving Uploads

Uploads in `data/uploads/` are blocked by `.htaccess`. Create a separate alias:

**Apache** (already in VirtualHost above):
```apache
Alias /webbook/uploads/ /var/www/html/webbook/webbook-saas/data/uploads/
```

**Nginx** (already in server block above):
```nginx
location /webbook/uploads/ {
    alias /var/www/html/webbook/webbook-saas/data/uploads/;
    ...
}
```

### 13.4 CDN Integration (Optional)

For better performance, serve uploads from a CDN. Change in `config.php`:
```php
define('WB_UPLOAD_URL_BASE', 'https://cdn.2030b.com/uploads');
```
Then configure your CDN to pull from `https://2030b.com/webbook/uploads/`.

---

## 14. Rate Limiting

### 14.1 How It Works

Rate limiting is **file-based** — each IP gets a JSON counter file in `data/rate/`. On each request, the counter is checked and incremented.

```php
// config.php defaults
define('WB_RATE_LIMIT',  120);   // max requests per window
define('WB_RATE_WINDOW', 60);    // window in seconds (1 minute)
```

**Default**: 120 requests per minute per IP → returns `HTTP 429` if exceeded.

### 14.2 Adjust for Your Traffic

```php
// Lenient (high-traffic public API)
define('WB_RATE_LIMIT',  300);
define('WB_RATE_WINDOW', 60);

// Strict (API with abuse potential)
define('WB_RATE_LIMIT',  30);
define('WB_RATE_WINDOW', 60);
```

### 14.3 Clean Rate Files (Cron Job)

Rate files accumulate over time. Add a cron job:

```bash
# Edit crontab
crontab -e -u www-data

# Add: purge rate files older than 5 minutes, every 5 minutes
*/5 * * * * find /var/www/html/webbook/webbook-saas/data/rate/ -name "*.json" -mmin +5 -delete 2>/dev/null
```

### 14.4 Better Alternative: Nginx Rate Limiting

For production, use Nginx's built-in rate limiter (more performant):

```nginx
# In http {} block (nginx.conf or conf.d/default.conf)
limit_req_zone $binary_remote_addr zone=wb_api:10m rate=120r/m;
limit_req_zone $binary_remote_addr zone=wb_auth:1m  rate=5r/m;    # strict for auth

# In server {} block
location /webbook/api/ {
    # API: 120 req/min, burst of 30
    limit_req zone=wb_api burst=30 nodelay;
    limit_req_status 429;
    # ... PHP config ...
}

location /webbook/api/auth/ {
    # Auth endpoints: 5 req/min, no burst
    limit_req zone=wb_auth burst=5 nodelay;
    limit_req_status 429;
    # ... PHP config ...
}
```

If using Nginx rate limiting, you can disable PHP-level rate limiting:
```php
// In bootstrap.php, comment out:
// wb_rate_check();
```

---

## 15. SSL/TLS (HTTPS)

### 15.1 Let's Encrypt (Free Certificate)

```bash
# Install Certbot
sudo apt-get install -y certbot

# Apache
sudo apt-get install -y python3-certbot-apache
sudo certbot --apache -d 2030b.com -d www.2030b.com

# Nginx
sudo apt-get install -y python3-certbot-nginx
sudo certbot --nginx -d 2030b.com -d www.2030b.com

# Verify auto-renewal
sudo systemctl status certbot.timer
# Or test renewal
sudo certbot renew --dry-run
```

### 15.2 Auto-Renewal Cron (if systemd timer not available)

```bash
# Edit root crontab
sudo crontab -e

# Check twice daily + reload web server
0 3,15 * * * certbot renew --quiet --post-hook "systemctl reload apache2"
# or for Nginx:
0 3,15 * * * certbot renew --quiet --post-hook "systemctl reload nginx"
```

### 15.3 SSL Security Settings (Nginx)

```nginx
ssl_protocols             TLSv1.2 TLSv1.3;
ssl_ciphers               ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_cache         shared:SSL:10m;
ssl_session_timeout       10m;
ssl_stapling              on;
ssl_stapling_verify       on;
resolver                  8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout          5s;
```

Test your SSL: https://www.ssllabs.com/ssltest/

---

## 16. First-Run Verification

After deployment, run these checks **in order**:

### Step 1: Health Check

```bash
curl -s https://2030b.com/webbook/api/ | python3 -m json.tool
```

Expected:
```json
{
  "service": "WebBook SaaS",
  "version": "2.0.0",
  "status": "ok",
  "endpoint": "https://2030b.com/webbook/api",
  "platform": {
    "total_users": 0,
    "total_authors": 0,
    "total_readers": 0,
    "total_books": 0,
    "total_published": 0
  }
}
```

### Step 2: Register an Author

```bash
curl -s -X POST https://2030b.com/webbook/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":        "author@example.com",
    "username":     "authoruser",
    "password":     "SecurePass123!",
    "display_name": "Test Author",
    "role":         "author"
  }' | python3 -m json.tool

# Save the token:
TOKEN="<token from response>"
```

### Step 3: Create a Book

```bash
curl -s -X POST https://2030b.com/webbook/api/books \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title":      "كتابي الأول",
    "language":   "ar",
    "direction":  "rtl",
    "visibility": "public",
    "theme":      "dark-night"
  }' | python3 -m json.tool

# Save: BOOK_ID and api_key from response
BOOK_ID="<id from response>"
API_KEY="<api_key from response>"
```

### Step 4: Create & Publish a Chapter

```bash
# Create chapter
curl -s -X POST "https://2030b.com/webbook/api/books/$BOOK_ID/chapters" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title":        "الفصل الأول",
    "file_id":      "ch01",
    "part":         "الجزء الأول",
    "content":      "# الفصل الأول\n\nمرحباً بك في هذا الكتاب.",
    "is_published": 1
  }' | python3 -m json.tool

# Publish the book
curl -s -X PUT "https://2030b.com/webbook/api/books/$BOOK_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status": "published", "visibility": "public"}' | python3 -m json.tool
```

### Step 5: Test Public Endpoints (No Auth)

```bash
# Book info
curl -s "https://2030b.com/webbook/api/public/$BOOK_ID/info" | python3 -m json.tool

# Chapters list
curl -s "https://2030b.com/webbook/api/public/$BOOK_ID/chapters" | python3 -m json.tool

# Markdown cache
curl -s "https://2030b.com/webbook/api/public/$BOOK_ID/mdcache" | python3 -m json.tool
```

### Step 6: Test Reader Sync (Anonymous)

```bash
curl -s -X POST "https://2030b.com/webbook/api/books/$BOOK_ID/sync" \
  -H "Content-Type: application/json" \
  -H "X-WB-Reader: anon-test-reader-001" \
  -d '{
    "kv": [{"k":"sessions","v":"1","updated_at":0}],
    "activity": [{"type":"session_start","label":"Start","ts":0}]
  }' | python3 -m json.tool
```

### Step 7: Open Reader Frontend

```
https://2030b.com/webbook-v7.html?book=<BOOK_ID>
```

The reader should:
- Load book metadata from the API
- Fetch and render chapters from markdown cache
- Allow anonymous sync via `X-WB-Reader` header

### Step 8: Create Admin User (Optional)

```bash
# 1. Register a normal user first
# 2. Then use SQLite CLI to promote to admin:
sqlite3 /var/www/html/webbook/webbook-saas/data/main.sqlite \
  "UPDATE users SET role='admin' WHERE email='admin@example.com';"

# 3. Verify admin access
ADMIN_TOKEN="<login as admin>"
curl -s https://2030b.com/webbook/api/admin/stats \
  -H "Authorization: Bearer $ADMIN_TOKEN" | python3 -m json.tool
```

---

## 17. API Test Suite

The project includes an interactive HTML test suite at `webbook-api-test.html`.

### Access

```
https://2030b.com/webbook-api-test.html
```

### Features

| Feature                 | Description                                              |
|-------------------------|----------------------------------------------------------|
| **Dashboard**           | Health check, platform stats, session info               |
| **Configuration Panel** | Set API base URL, token, book/chapter IDs                |
| **Auto Test Runner**    | Runs 20 automated tests end-to-end (register → sync → logout) |
| **Auth Tests**          | Register, login, logout, profile update, verify, reset   |
| **Book Tests**          | Create, list, update, publish, delete, analytics         |
| **Chapter Tests**       | Create, list, update, revisions                          |
| **Sync Tests**          | Push/pull, KV, annotations, MD cache                     |
| **Public API Tests**    | No-auth endpoints                                        |
| **Upload Tests**        | Cover image, avatar, file list                           |
| **Webhook Tests**       | Create, list, test fire                                  |
| **Admin Tests**         | Platform stats, user management                          |

### Auto Test Runner (20 tests)

1. Health Check → `GET /`
2. Register Reader → `POST /auth/register`
3. Register Author → `POST /auth/register` (role=author)
4. Login Author → `POST /auth/login`
5. GET /auth/me
6. Create Book → `POST /books`
7. List Books → `GET /books`
8. Create Chapter → `POST /books/{id}/chapters`
9. Get Chapter → `GET /books/{id}/chapters/{ch}`
10. Publish Book → `PUT /books/{id}` (status=published)
11. Public Info → `GET /public/{id}/info` (no auth)
12. Public Chapters → `GET /public/{id}/chapters` (no auth)
13. Public MD Cache → `GET /public/{id}/mdcache` (no auth)
14. Sync Push (anonymous) → `POST /books/{id}/sync` (X-WB-Reader)
15. Sync Pull (anonymous) → `GET /books/{id}/sync` (X-WB-Reader)
16. Book Stats → `GET /books/{id}/stats`
17. Author Stats → `GET /auth/me/stats`
18. Chapter Revisions → `GET /books/{id}/chapters/{ch}/revisions`
19. Create Webhook → `POST /webhooks`
20. Logout → `POST /auth/logout`

---

## 18. Production Hardening

### 18.1 Remove Wildcard CORS

```php
// config.php — NO '*' in production
define('WB_ALLOWED_ORIGINS', [
    'https://2030b.com',
    'https://www.2030b.com',
]);
```

### 18.2 Verify Secrets Are Set

```bash
# Check that placeholder strings are NOT used
php -r "
define('WB_TOKEN_SECRET', getenv('WB_TOKEN_SECRET') ?: 'CHANGE_THIS_SECRET_IN_ENV');
if (strpos(WB_TOKEN_SECRET, 'CHANGE') !== false) {
    echo 'WARNING: WB_TOKEN_SECRET is using the placeholder!';
} else {
    echo 'OK: Token secret is set (' . strlen(WB_TOKEN_SECRET) . ' chars)';
}
echo PHP_EOL;
"
```

### 18.3 Verify Data Directory Is Blocked

```bash
# All must return 403:
curl -o /dev/null -w "main.sqlite: %{http_code}\n" https://2030b.com/webbook-saas/data/main.sqlite
curl -o /dev/null -w "error.log: %{http_code}\n"   https://2030b.com/webbook-saas/data/error.log
curl -o /dev/null -w "data dir: %{http_code}\n"    https://2030b.com/webbook-saas/data/
```

### 18.4 Harden PHP-FPM Execution

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
security.limit_extensions = .php   ; only execute .php files
```

### 18.5 Error Logging

```bash
# Monitor errors in real-time
tail -f /var/www/html/webbook/webbook-saas/data/error.log
tail -f /var/log/apache2/webbook_error.log     # Apache
tail -f /var/log/nginx/webbook_error.log       # Nginx
```

### 18.6 Fail2Ban (Block Brute Force)

Create `/etc/fail2ban/filter.d/webbook.conf`:
```ini
[Definition]
failregex = ^<HOST> .* "(?:POST|GET) /webbook/api/auth/(?:login|register).*" (?:401|429)
ignoreregex =
```

Create `/etc/fail2ban/jail.d/webbook.conf`:
```ini
[webbook-api]
enabled   = true
port      = http,https
filter    = webbook
logpath   = /var/log/apache2/webbook_access.log
            /var/log/nginx/webbook_access.log
maxretry  = 10
bantime   = 3600
findtime  = 600
```

```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status webbook-api
```

### 18.7 OPcache Warm-up After Deployment

```bash
find /var/www/html/webbook/webbook-saas -name "*.php" | while read f; do
    php -l "$f" > /dev/null 2>&1
done
echo "OPcache primed"
```

### 18.8 Disable API Test Suite in Production (Optional)

The `webbook-api-test.html` creates test data on the server. To restrict access:

```apache
# Apache: password-protect the test file
<Files "webbook-api-test.html">
    AuthType Basic
    AuthName "WebBook API Test"
    AuthUserFile /etc/apache2/.htpasswd
    Require valid-user
</Files>
```

Or simply rename/remove it:
```bash
mv /var/www/html/webbook/webbook-api-test.html /var/www/html/webbook/webbook-api-test-DISABLED.html
```

---

## 19. Backup & Maintenance

### 19.1 SQLite Backup (Cron)

```bash
# Create backup directory
sudo mkdir -p /var/backups/webbook
sudo chown www-data:www-data /var/backups/webbook

# Edit root crontab
sudo crontab -e

# Daily backup at 2:00 AM — main database
0 2 * * * sqlite3 /var/www/html/webbook/webbook-saas/data/main.sqlite \
  ".backup '/var/backups/webbook/main-$(date +\%Y\%m\%d).sqlite'" 2>&1

# Daily backup at 2:30 AM — all book databases
30 2 * * * for f in /var/www/html/webbook/webbook-saas/data/books/*.sqlite; do \
  bn=$(basename $f .sqlite); \
  sqlite3 "$f" ".backup '/var/backups/webbook/book-${bn}-$(date +\%Y\%m\%d).sqlite'"; \
done 2>&1

# Keep 30 days of backups
0 4 * * * find /var/backups/webbook/ -name "*.sqlite" -mtime +30 -delete

# Keep 7 days of uploads backup
0 4 * * * find /var/backups/webbook/uploads-* -mtime +7 -delete 2>/dev/null; \
  cp -r /var/www/html/webbook/webbook-saas/data/uploads/ \
        /var/backups/webbook/uploads-$(date +\%Y\%m\%d)/ 2>&1
```

### 19.2 Clean Rate Limit Files (Cron)

```bash
# Every 5 minutes: delete rate files older than 5 min
*/5 * * * * find /var/www/html/webbook/webbook-saas/data/rate/ -name "*.json" -mmin +5 -delete 2>/dev/null
```

### 19.3 Restore from Backup

```bash
# Stop web server during restore
sudo systemctl stop apache2  # or nginx

# Restore main DB
cp /var/backups/webbook/main-20260430.sqlite \
   /var/www/html/webbook/webbook-saas/data/main.sqlite

# Restore a book DB
cp /var/backups/webbook/book-{bookId}-20260430.sqlite \
   /var/www/html/webbook/webbook-saas/data/books/{bookId}.sqlite

# Fix permissions
chown www-data:www-data /var/www/html/webbook/webbook-saas/data/main.sqlite
chmod 640 /var/www/html/webbook/webbook-saas/data/main.sqlite

sudo systemctl start apache2  # or nginx
```

### 19.4 SQLite WAL Checkpoint (Maintenance)

```bash
# Force WAL checkpoint (merge WAL back into main DB)
sqlite3 /var/www/html/webbook/webbook-saas/data/main.sqlite "PRAGMA wal_checkpoint(TRUNCATE);"
for f in /var/www/html/webbook/webbook-saas/data/books/*.sqlite; do
  sqlite3 "$f" "PRAGMA wal_checkpoint(TRUNCATE);"
done
```

### 19.5 Monitor Disk Usage

```bash
# Check data directory size
du -sh /var/www/html/webbook/webbook-saas/data/
du -sh /var/www/html/webbook/webbook-saas/data/books/
du -sh /var/www/html/webbook/webbook-saas/data/uploads/

# Count SQLite files
ls -la /var/www/html/webbook/webbook-saas/data/books/*.sqlite | wc -l
```

---

## 20. Troubleshooting

### ❌ 403 Forbidden on `/webbook/api/`

**Apache cause**: Missing `mod_rewrite` or `AllowOverride None`.
```bash
sudo a2enmod rewrite
# Verify VirtualHost has: AllowOverride All
sudo apache2ctl -t && sudo systemctl reload apache2
```

**Nginx cause**: `try_files` not routing to `index.php`.
```bash
sudo nginx -t && sudo systemctl reload nginx
# Check: the fastcgi_pass socket exists
ls -la /run/php/php8.2-fpm.sock
```

---

### ❌ 500 Internal Server Error

```bash
# 1. Check error log
tail -50 /var/www/html/webbook/webbook-saas/data/error.log

# 2. Check Apache/Nginx logs
tail -20 /var/log/apache2/webbook_error.log
tail -20 /var/log/nginx/webbook_error.log

# 3. Check PHP-FPM log
tail -20 /var/log/php8.2-fpm.log
```

**Common causes:**
- Missing PHP extension (`pdo_sqlite`, `mbstring`, `fileinfo`)
- `data/` directory not writable by web server
- SQLite version < 3.35.0
- PHP < 8.1 (uses `match`, `never` return type, `str_starts_with`)

```bash
# Check PHP version
php --version

# Check SQLite via PHP
php -r "echo (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn() . PHP_EOL;"

# Test data/ is writable
sudo -u www-data touch /var/www/html/webbook/webbook-saas/data/test.tmp && echo "Writable OK" && \
  sudo -u www-data rm /var/www/html/webbook/webbook-saas/data/test.tmp
```

---

### ❌ CORS errors in browser console

```bash
# Test preflight
curl -v -X OPTIONS "https://2030b.com/webbook/api/auth/login" \
  -H "Origin: https://2030b.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization"
# Must see: HTTP/2 204 and Access-Control-Allow-Origin: https://2030b.com
```

**Fix**: Add your frontend domain to `WB_ALLOWED_ORIGINS` in `config.php`.

---

### ❌ "database is locked" (SQLite)

```bash
# Check SQLite version (must be ≥ 3.35)
php -r "echo (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn();"

# Check for stale WAL files
ls -la /var/www/html/webbook/webbook-saas/data/*.sqlite-wal
ls -la /var/www/html/webbook/webbook-saas/data/*.sqlite-shm

# Force checkpoint
sqlite3 /var/www/html/webbook/webbook-saas/data/main.sqlite "PRAGMA wal_checkpoint(FULL);"
```

**Prevention**: SQLite WAL mode (already set in `bootstrap.php`) significantly reduces locking.

---

### ❌ Emails not being sent

```bash
# 1. Check WB_MAIL_ENABLED is "1"
php -r "echo getenv('WB_MAIL_ENABLED') . PHP_EOL;"

# 2. If using PHP mail(), test MTA
echo "Test" | mail -s "WB Test" your@email.com
sudo tail /var/log/mail.log

# 3. If WB_MAIL_ENABLED=0, check error.log for [WB-MAIL] entries
grep "WB-MAIL" /var/www/html/webbook/webbook-saas/data/error.log
```

---

### ❌ Rate limit files accumulating

```bash
# Check count
ls /var/www/html/webbook/webbook-saas/data/rate/ | wc -l

# Manual cleanup
find /var/www/html/webbook/webbook-saas/data/rate/ -name "*.json" -delete

# Add the cron job (Section 19.2)
```

---

### ❌ webbook-v7.html cannot reach API

**Check browser console** for the specific error type:
- **CORS**: Add frontend URL to `WB_ALLOWED_ORIGINS`
- **404**: Check URL path matches — should be `/webbook/api/` not `/api/`
- **SSL**: Verify HTTPS certificate is valid and not expired
- **Network**: Confirm server firewall allows ports 80/443

```bash
# Verify API is reachable
curl -s "https://2030b.com/webbook/api/" | grep '"status"'
# Expected: "status": "ok"
```

---

### ❌ Upload fails with `Failed to save file`

```bash
# Check PHP upload settings
php -r "echo ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size');"
# Should be: 10M / 12M

# Check uploads directory is writable
ls -la /var/www/html/webbook/webbook-saas/data/uploads/
sudo -u www-data mkdir -p /var/www/html/webbook/webbook-saas/data/uploads/test
sudo -u www-data rmdir  /var/www/html/webbook/webbook-saas/data/uploads/test
```

---

## 21. Upgrade Guide (v1 → v2)

### What's New in v2.0

| Feature                        | Description                                      |
|--------------------------------|--------------------------------------------------|
| Email verification             | `POST /auth/verify/send` + `GET /auth/verify/{token}` |
| Password reset                 | `POST /auth/reset/request` + `POST /auth/reset/{token}` |
| `reader` role                  | New user role separate from `author`             |
| Webhooks                       | Full webhook management + delivery log           |
| Media uploads                  | Cover images and avatar upload endpoints         |
| Admin panel                    | User/book management + impersonation             |
| Author statistics              | `/auth/me/stats` with trend data                 |
| Plan limits                    | `WB_PLAN_LIMITS` per-plan quota system           |
| Book analytics                 | Daily `book_analytics` table with aggregates     |

### Migration Steps

```bash
# ── Step 1: Full backup FIRST ─────────────────────────────
cp data/main.sqlite data/main.sqlite.bak.$(date +%Y%m%d_%H%M%S)
for f in data/books/*.sqlite; do
  cp "$f" "${f}.bak.$(date +%Y%m%d_%H%M%S)"
done
echo "Backup complete"

# ── Step 2: Deploy new PHP files ──────────────────────────
# rsync -av --exclude='data/' webbook-saas/ /var/www/html/webbook/webbook-saas/
# or git pull

# ── Step 3: New tables are auto-created ───────────────────
# MainDB.php uses CREATE TABLE IF NOT EXISTS — existing data preserved.
# New tables created on next API request.

# ── Step 4: Update config.php with new constants ──────────
# Add if missing:
#   WB_VERIFY_TOKEN_TTL, WB_RESET_TOKEN_TTL
#   WB_PLAN_LIMITS, WB_UPLOAD_*, WB_WEBHOOK_*
#   WB_ALLOWED_ORIGINS (new format)

# ── Step 5: Add new environment variables ─────────────────
# WB_MAIL_ENABLED, WB_MAIL_FROM (if using email)

# ── Step 6: Verify ────────────────────────────────────────
curl -s https://2030b.com/webbook/api/ | python3 -m json.tool
# Expected: "status": "ok", "version": "2.0.0"

# ── Step 7: Promote existing users to 'author' if needed ──
sqlite3 data/main.sqlite \
  "UPDATE users SET role='author' WHERE role='user' OR role IS NULL OR role='';"
```

---

## Quick Reference Card

```
API Base:           https://2030b.com/webbook/api
Test Suite:         https://2030b.com/webbook-api-test.html
Health:             GET  /
Auth:               POST /auth/register | /auth/login | /auth/logout
Profile:            GET|PUT /auth/me
Verify email:       POST /auth/verify/send  |  GET /auth/verify/{token}
Password reset:     POST /auth/reset/request | POST /auth/reset/{token}
Sessions:           GET /auth/sessions | DELETE /auth/sessions/{id}
Author stats:       GET  /auth/me/stats[/books|/readers|/activity]
Books:              GET|POST /books  |  GET|PUT|PATCH|DELETE /books/{id}
Book stats:         GET /books/{id}/stats | /books/{id}/analytics
Chapters:           GET|POST /books/{id}/chapters
Chapter:            GET|PUT|PATCH|DELETE /books/{id}/chapters/{ch}
Revisions:          GET /books/{id}/chapters/{ch}/revisions
Reorder:            POST /books/{id}/chapters/reorder
Sync full push:     POST /books/{id}/sync
Sync full pull:     GET  /books/{id}/sync
Sync KV:            PUT  /books/{id}/sync/kv
Sync annotations:   POST /books/{id}/sync/anns
Sync replies:       POST /books/{id}/sync/replies
Sync activity:      POST /books/{id}/sync/activity
Sync scroll:        POST /books/{id}/sync/scrollpos
MD cache pull:      GET  /books/{id}/sync/mdcache
Upload cover:       POST /upload/cover  (multipart/form-data)
Upload avatar:      POST /upload/avatar (multipart/form-data)
Upload list:        GET  /upload/list
Upload delete:      DELETE /upload/{filename}
Webhooks:           GET|POST /webhooks  |  GET|PATCH|DELETE /webhooks/{id}
Webhook test:       POST /webhooks/{id}/test | GET /webhooks/{id}/logs
Admin stats:        GET  /admin/stats
Admin users:        GET  /admin/users[/{id}] | PATCH|DELETE /admin/users/{id}
Admin impersonate:  POST /admin/users/{id}/impersonate
Admin books:        GET  /admin/books[/{id}] | DELETE /admin/books/{id}
Public (no auth):   GET  /public/{bookId}/info | /chapters | /mdcache
```

---

*Last updated: April 2026 | WebBook SaaS v2.0.0*  
*Domain: 2030b.com | PHP ≥ 8.1 | SQLite ≥ 3.35 | Apache 2.4+ / Nginx 1.18+*
