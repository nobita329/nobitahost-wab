# NobitaHost

Full Node.js web panel — card-based dark UI with user dashboard, admin dashboard, user/team management, CMS content pages, analytics, 2FA, email auth flows and fully customisable settings.

## Ports

| Service | Port | URL |
|---|---|---|
| Web Panel | **3001** | http://localhost:3001 |
| REST API | **3002** | http://localhost:3002 |

## Quick Start

```bash
npm install
npm run build        # init DB + seed settings, content & default admin (admin/admin123)
npm start            # web panel  -> :3001
npm run api          # api        -> :3002
```

With PM2 (two apps):

```bash
npm install
npm run build
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

### Create a user (CLI)

```bash
npm run createuser                       # interactive
npm run createuser -- --username john --email john@x.com --password secret123 --role admin
```

## Features

- **Auth**: Login, Register, Forgot/Reset password (email), Two-Factor Authentication (TOTP + QR)
- **User pages**: Home, Team, Tutorials, Command, Analytics, Projects, Links, About, Activity, Profile
- **Admin**: Dashboard, Create User, User Management (edit/suspend/delete), Settings, Content, Tutorials, Activity Log
- **Team**: main user creates sub-users, edits, suspends, deletes them
- **Settings (General)**: Panel Name, Logo (upload / URL / emoji) with live preview, Favicon (upload/URL) with preview
- **Settings (Background)**: image/video upload or URL, 4K Wallpapers source picker (`cute-kawaii-wallpapers`, `ultrawide-monitor-hd-wallpapers`, `cool-wallpapers`, `black-dark`, `aesthetic-wallpapers`, `space`, `cr7-wallpapers`, `all`), overlay toggle
- **Settings (Music)**: mp3/video URL or upload, or YouTube background music widget
- **Settings (Appearance)**: transparent bar, blur bar, card radius, accent color
- **Settings (Email/SMTP)**: welcome + password reset emails
- **Analytics**: page views (last 7 days), login history, user stats — both web and `:3002/api/analytics`

## API (port 3002)

```
GET    /api/status
POST   /api/auth/login          { username, password }
POST   /api/auth/register       { username, email, password }
GET    /api/auth/me             (cookie JWT)
GET    /api/activity
GET    /api/analytics
GET    /api/settings
PUT    /api/settings            (admin)
GET    /api/admin/users         (admin)
POST   /api/admin/users         (admin) { username, email, password, role }
PUT    /api/admin/users/:id     (admin)
PUT    /api/admin/users/:id/suspend  (admin) { suspended: true }
DELETE /api/admin/users/:id     (admin)
```

Auth uses an httpOnly cookie (`nh_token`) so the web panel (:3001) and API (:3002) share the same login.

## Env (`.env`)

```
PORT=3001
API_PORT=3002
JWT_SECRET=...
PANEL_URL=http://localhost:3001
SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS / MAIL_FROM
```

## Structure

```
src/
  server.js          web panel (:3001)
  api.js             REST API (:3002)
  db.js              SQLite (better-sqlite3)
  settings.js        panel settings + wallpaper sources
  mailer.js          SMTP email
  routes/            web, auth, api routes
  middleware/        jwt auth, multer upload
scripts/
  build.js           setup / seed
  createuser.js      CLI user creation
views/               EJS (user + admin + auth + errors)
public/              css, js, uploads
```

> Default admin: `admin` / `admin123` — change it after first login!
