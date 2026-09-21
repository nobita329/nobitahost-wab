# NobitaHost · PHP Edition (CasaOS UI)

A complete, dependency-free **PHP 8** port of the Node/Express + EJS panel that lives in the
repository root — every page, every route, every admin tool — wrapped in a **CasaOS-style
desktop shell** (glass topbar with live system widgets, icon dock, wallpaper background and an
app-grid home).

No framework, no Composer, no build step: plain PHP files + SQLite (`pdo_sqlite`).
The Node app in `src/` + `views/` is untouched; this edition lives entirely in `php/`.

```
Node edition : Express + EJS + better-sqlite3   →  :3001 (web) / :3002 (api)
PHP edition  : plain PHP 8 + PDO/SQLite         →  one port (web + api together)
```

---

## Quick start

```bash
cd php
php cli/setup.php          # create data/nobitahost.db, seed settings/content/roles/apps + admin & demo users
bash serve.sh              # = php -S 0.0.0.0:8080 router.php  (dev server)
```

Open <http://localhost:8080>

| Login | User | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Demo | `demo` | `demo123` (read-only demo account) |

> Change the admin password after the first login (`/profile` → Password).

### Useful setup flags

```bash
php cli/setup.php --import-node          # reuse the Node build's data/nobitahost.db (1:1 schema)
php cli/setup.php --admin=root:secret123 # create/replace the admin account
```

### Requirements

PHP **8.1+** with `pdo_sqlite`, `session`, `mbstring`, `openssl`, `hash`, `curl`, `json`
(`gd` optional — image uploads, `zip` optional). Verified on PHP 8.5.

---

## Production deploy

**Apache** — point the document root at `php/`; `.htaccess` (mod_rewrite) routes everything
through `index.php`. Allow overrides:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/nobitahost/php
    <Directory /var/www/nobitahost/php>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**nginx + php-fpm**

```nginx
root /var/www/nobitahost/php;
index index.php;
location / { try_files $uri $uri/ /index.php$is_args$args; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
location ~ ^/(data|storage/cache)/ { deny all; }
```

Writable paths: `php/data/` (SQLite DB) and `php/storage/uploads/`, `php/storage/cache/`.

---

## CasaOS UI

Every page renders inside the same shell (`views/layouts/app.php` + `views/partials/topbar.php`,
`dock.php`, `icons.php`, styled by `assets/css/casaos.css`):

- **Topbar** — panel logo/name, search, live widgets: CPU, RAM, disk, network, temperature and
  clock (`#csCpu #csMem #csDisk #csNet #csTemp #csClockTime`), theme toggle, dock toggle and the
  user menu (`#csUserMenu`). Widgets poll `/api/system` (+ `/api/system-history` for sparklines).
- **Dock** — icon launcher with tooltips, pinned apps + quick links; persisted in `localStorage`
  and toggleable per user (`casaos_dock` setting).
- **Wallpaper** — panel background from settings (upload / URL / 4K-wallpaper source) with blur +
  overlay alpha controls, exactly like the Node edition.
- **App grid home** (`/`) — desktop-style tiles grouped into sections (Panel, Admin, Content,
  Tools) plus the CMS "home" content panel. `/apps` is the full launcher page.
- **CasaOS-ish apps** — Storage (`/storage`, devices + usage), System (`/system`, live monitor),
  Apps (`/apps`, CRUD via `/api/apps`), Files-ish content manager, Terminal-ish command center.

All CasaOS features can be switched off in **Admin → Settings** (`casaos_mode`, `casaos_dock`,
`casaos_widgets`, `casaos_grid`, `casaos_wallpaper`, `casaos_opacity`) — with them off the panel
falls back to the classic card UI.

---

## What's ported

| Area | PHP | Notes |
|---|---|---|
| Routes | **175** (`routes/web.php` 143, `routes/auth.php` 12, `routes/api.php` 20) | every Node route from `web.routes.js` / `auth.routes.js` / `api.routes.js` + CasaOS extras (`/apps`, `/api/apps` CRUD, `/api/status`, `/api/storage`, `/favicon.ico`, `/403`, `/500`) |
| Views | **70** PHP templates | 1:1 from the 67 EJS views + CasaOS home/storage/system pages |
| Core modules | **26** classes in `app/Core/` | Router, Request, Response, View, DB, Auth, Settings, Totp, Http, Uploader, Mailer, Markdown, System, Nav, Blog, Github, Youtube, Wallpapers, Social, Comments, Obsidian, Cloudflare, Commands, NhObj + helpers |
| Database | **26 tables** | same schema as the Node build (`src/db.js`) + `casaos_apps` |
| Commands dataset | **13,653** entries | byte-for-byte parity with `src/commands.js` incl. ICU-style collation order |
| Cloudflare | 27 functions | token verify, zones, DNS CRUD, analytics, DDoS level, security/access rules, Zero Trust, zone settings |
| 2FA | TOTP + QR | secret/verify/provisioning URI server-side, QR rendered client-side (`assets/js/qr.js` + vendored `qrcode-generator`) |

Feature checklist (all working): login/register/forgot/reset (email), demo login, 2FA enable +
login challenge, profile (avatar upload, bio, password), team & sub-users, roles, admin user
management (edit/suspend/delete), settings (general, background/wallpapers, music, appearance,
SMTP, API keys), CMS content pages (about/terms/footer/navbar/custom pages), blog (public list +
post + feedback, admin CRUD), tutorials, projects, links, GitHub repos, docs, plans
(categories/plans/coupons/subscribers/transactions/trash/settings), analytics + traffic, activity
log, profile comments/reactions (social), wallpapers browser with favourites, system monitor,
storage devices, maintenance mode, bot-aware page tracking.

---

## Structure

```
php/
  index.php            front controller: bootstrap → globals → router → error pages
  router.php           php -S router (serves static assets, else index.php)
  .htaccess            Apache rewrite rules
  serve.sh             dev server shortcut
  app/
    bootstrap.php      autoloader, session, error mode, DB migrate, settings/globals
    Core/              26 framework + feature classes (see table above)
  routes/
    web.php            panel + admin + CasaOS pages
    auth.php           login / register / forgot / reset / verify-2fa / logout / demo
    api.php            JSON API (same session auth as the web panel)
  views/
    layouts/           app.php (CasaOS shell), auth.php                 (2)
    partials/          topbar, dock, icons, site-footer, social comments (6)
    pages/             user/ 19 · admin/ 22 · plans/ 9 · auth/ 5 · errors/ 4 · casaos/ 3
  assets/
    css/               base.css, lucentui.css, casaos.css
    js/                app.js, casaos.js (widgets/dock/theme), qr.js, vendor/qrcode-generator.js
    img/
  data/                nobitahost.db (gitignored)
  storage/             uploads/, cache/ (gitignored)
  cli/setup.php        DB creation + seeding
  tools/               ejs2php.mjs (EJS→PHP converter), gen-commands.mjs (commands dataset)
```

---

## API (same origin as the panel)

```
GET    /api/status              health + engine info
GET    /api/system              { success, system, data, devices }   (CasaOS widgets)
GET    /api/system/history      { success, history, data, range }
GET    /api/storage             storage devices + usage
POST   /api/auth/login          { username, password[, code] }
POST   /api/auth/register       { username, email, password }
GET    /api/auth/me             401 without a session
GET    /api/activity
GET    /api/analytics           (admin)
GET    /api/settings
PUT    /api/settings            (admin)
GET    /api/admin/users         (admin)
POST   /api/admin/users         (admin)
PUT    /api/admin/users/{id}    (admin)
PUT    /api/admin/users/{id}/suspend   (admin)
DELETE /api/admin/users/{id}    (admin)
GET    /api/apps                CasaOS app tiles (?scope=me)
POST   /api/apps                (admin) create tile
PUT    /api/apps/{id}           (admin) update tile
DELETE /api/apps/{id}           (admin) delete tile
```

**Auth model difference:** the Node edition uses a JWT cookie (`nh_token`) shared by :3001/:3002;
the PHP edition uses a native session cookie (`nh_php`, httpOnly) — the API and the panel share
that one session. Every HTML form carries a `_csrf` field (`csrf_field()`), validated on all
state-changing web routes.

---

## Notes for maintainers

- **Templates read data with `->`** (like EJS did): `View::normalize()` turns associative arrays
  into `NhObj`, lists stay lists. `NhObj` extends `stdClass` and returns `null` for unknown
  properties, so a missing key behaves like JS `undefined` instead of raising a warning. DB rows
  are wrapped the same way.
- **Helpers** (`app/Core/helpers.php`): `e()`, `nh_count()`, `nh_slice()`, `nh_num()`,
  `nh_time_ago()`, `nh_obj()`, `json_attr()`, `csrf_field()`, `partial()`, `fmt()`… — the JS-parity
  shims used all over the converted views.
- **Regenerating assets**: `node tools/gen-commands.mjs` rebuilds the commands dataset;
  `node tools/ejs2php.mjs --all` re-converts EJS views (it skips the hand-written CasaOS/auth/error
  pages — check the SKIP list before running).
- **Smoke tests**: every GET route (77) and 44 mutation routes were exercised end-to-end with a
  logged-in admin session — no fatals, no warnings, no notices in the PHP error log; the 2FA
  lifecycle (setup → enable → login challenge → verify → disable) and password change were
  verified against real TOTP codes.
