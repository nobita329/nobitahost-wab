#!/usr/bin/env bash
#
# NobitaHost · Auto Installer / Manager
#
#   ./run.sh install [--port N] [--url https://...]
#   ./run.sh uninstall [--purge]
#   ./run.sh update
#   ./run.sh restart
#   ./run.sh status
#   ./run.sh logs [app]
#   ./run.sh user create [--username x --email x@y.z --password p --role admin]
#   ./run.sh user list
#   ./run.sh user delete <id|username> [--force]
#   ./run.sh help
#

set -euo pipefail

cd "$(dirname "$0")"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; CYAN=$'\033[36m'; BOLD=$'\033[1m'; RESET=$'\033[0m'
ok()   { echo -e " ${GREEN}✔${RESET} $*"; }
info() { echo -e " ${CYAN}→${RESET} $*"; }
warn() { echo -e " ${YELLOW}⚠${RESET} $*"; }
err()  { echo -e " ${RED}✘${RESET} $*"; }
step() { echo -e "\n${BOLD}── $* ──${RESET}"; }

need() { command -v "$1" >/dev/null 2>&1 || { err "'$1' not found. $2"; exit 1; }; }

env_get() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r'; }

# ---------------------------------------------------------------------------

install_app() {
  local PORT=""
  local URL=""

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --port) PORT="${2:-}"; shift 2 ;;
      --url) URL="${2:-}"; shift 2 ;;
      *) shift ;;
    esac
  done

  step "NobitaHost · Install"
  need node "install Node.js 18+ first (https://nodejs.org)"
  need npm "npm is missing"
  need pm2 "PM2 missing — run: npm install -g pm2"

  local NODE_MAJOR
  NODE_MAJOR=$(node -p "process.versions.node.split('.')[0]")
  if (( NODE_MAJOR < 18 )); then err "Node.js 18+ required (found v$(node -v))."; exit 1; fi
  ok "Node $(node -v) / npm $(npm -v) / PM2 $(pm2 -v)"

  if [[ -f .env ]]; then
    warn ".env already exists — keeping it"
  else
    info "Creating .env from template"
    cp .env.example .env
    local SECRET
    SECRET=$(openssl rand -hex 32 2>/dev/null || node -e "console.log(require('crypto').randomBytes(32).toString('hex'))")
    sed -i "s|^JWT_SECRET=.*|JWT_SECRET=${SECRET}|" .env
    if [[ -n "$PORT" ]]; then sed -i "s|^PORT=.*|PORT=${PORT}|" .env; fi
    if [[ -n "$URL" ]]; then
      sed -i "s|^PANEL_URL=.*|PANEL_URL=${URL}|" .env
    else
      sed -i "s|^PANEL_URL=.*|PANEL_URL=http://localhost:$(env_get PORT || echo 3001)|" .env
    fi
    ok "JWT secret generated + .env configured"
  fi

  if [[ -d node_modules && -z "${FORCE:-}" ]]; then
    info "node_modules present — skipping npm install (use FORCE=1 to reinstall)"
  else
    info "Installing dependencies (npm install)"
    npm install
  fi

  info "Building / seeding database"
  npm run build

  step "Starting with PM2"
  if pm2 id nobitahost-web 2>/dev/null | grep -qE '\d'; then
    info "Already managed by PM2 — restarting"
    pm2 restart nobitahost-web --update-env
    pm2 restart nobitahost-api --update-env 2>/dev/null || true
  else
    pm2 start ecosystem.config.js
  fi
  pm2 save

  if (( EUID == 0 )) && command -v systemctl >/dev/null 2>&1; then
    if ! systemctl is-enabled pm2-root 2>/dev/null | grep -q enabled; then
      info "Enabling PM2 auto-start on boot"
      pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || warn "pm2 startup failed — run 'pm2 startup' manually"
    fi
  else
    warn "Skipping auto-start (run as root with 'pm2 startup' to enable boot persistence)"
  fi

  sleep 1.5
  step "Done 🎉"
  local WP
  WP="http://localhost:$(env_get PORT || echo 3001)"
  echo -e "  Web Panel : ${BOLD}${GREEN}${WP}${RESET}"
  echo -e "  API       : ${BOLD}${GREEN}http://localhost:$(env_get API_PORT || echo 3002)${RESET}"
  echo -e "  Default admin: ${YELLOW}admin / admin123${RESET}  (change it after first login!)"
  echo
  ./run.sh status
}

# ---------------------------------------------------------------------------

uninstall_app() {
  local PURGE=0
  [[ "${1:-}" == "--purge" ]] && PURGE=1

  step "NobitaHost · Uninstall"

  if (( EUID != 0 )); then
    warn "Not running as root — pm2 boot-removal may need sudo"
  fi

  if command -v pm2 >/dev/null 2>&1; then
    pm2 delete nobitahost-web 2>/dev/null || true
    pm2 delete nobitahost-api 2>/dev/null || true
    pm2 save 2>/dev/null || true
    ok "PM2 apps stopped & removed"
    pm2 unstartup systemd -u "${USER:-root}" </dev/null >/dev/null 2>&1 || true
  else
    warn "PM2 not installed — nothing to stop"
  fi

  if (( PURGE == 1 )); then
    info "Purging files (data, uploads, node_modules, .env)"
    rm -rf data node_modules public/uploads .env
    ok "Purged — project files removed"
  else
    warn "Files kept. Use '--purge' to also delete data/db, uploads, node_modules & .env"
  fi

  echo -e "\n${GREEN}✔ NobitaHost uninstalled.${RESET}"
}

# ---------------------------------------------------------------------------

update_app() {
  step "NobitaHost · Update"
  if [[ -d .git ]]; then
    info "Pulling latest changes"
    git pull --ff-only || warn "git pull failed (continuing)"
  fi
  if command -v pm2 >/dev/null 2>&1; then
    info "Installing deps + rebuilding"
    npm install
    npm run build
    pm2 restart nobitahost-web --update-env >/dev/null 2>&1 || pm2 start ecosystem.config.js
    pm2 restart nobitahost-api --update-env >/dev/null 2>&1 || true
    pm2 save >/dev/null 2>&1 || true
    ok "Updated & restarted"
  else
    npm install && npm run build
    ok "Deps updated — start with: npm start / npm run api"
  fi
  ./run.sh status
}

# ---------------------------------------------------------------------------

restart_app() {
  step "NobitaHost · Restart"
  if ! command -v pm2 >/dev/null 2>&1; then err "PM2 not installed."; exit 1; fi
  pm2 restart nobitahost-web --update-env
  pm2 restart nobitahost-api --update-env 2>/dev/null || true
  pm2 save >/dev/null 2>&1 || true
  ok "Restarted"
  ./run.sh status
}

# ---------------------------------------------------------------------------

show_status() {
  step "NobitaHost · Status"

  if ! command -v pm2 >/dev/null 2>&1; then
    warn "PM2 not installed — panel not running"
    exit 1
  fi

  local LIST
  LIST=$(pm2 jlist 2>/dev/null | node -e "
    let d=''; process.stdin.on('data',c=>d+=c).on('end',()=>{
      try {
        const arr=JSON.parse(d);
        const apps=arr.filter(a=>/^nobitahost-(web|api)$/.test(a.name));
        for (const a of apps) {
          const st=a.pm2_env.status;
          const up=a.pm2_env.pm_uptime;
          const diff=Math.max(0, Date.now()-up);
          const m=Math.floor(diff/60000), h=Math.floor(m/60), d=Math.floor(h/24);
          const upTxt = d>0? d+'d '+(h%24)+'h' : h>0? h+'h '+(m%60)+'m' : m+'m '+(Math.floor(diff/1000)%60)+'s';
          console.log((a.name+'').padEnd(16)+' '+st.padEnd(8)+' '+upTxt);
        }
      } catch(e){ console.error(''); }
    });
  " 2>/dev/null || true)

  if [[ -n "$LIST" ]]; then
    echo -e " ${BOLD}App${RESET}            Status    Uptime"
    while IFS= read -r line; do
      local status
      status=$(echo "$line" | awk '{print $2}')
      local color=$GREEN
      [[ "$status" != "online" ]] && color=$RED
      echo -e "  ${color}${line//$status/${status}}${RESET}"
    done <<<"$LIST"
  else
    warn "No NobitaHost processes found (run ./run.sh install)"
  fi

  echo
  if [[ -f .env ]]; then
    echo -e "  ${BOLD}Ports:${RESET} web $(env_get PORT || echo 3001) · api $(env_get API_PORT || echo 3002)"
    echo -e "  ${BOLD}Panel:${RESET} $(env_get PANEL_URL || echo http://localhost:$(env_get PORT || echo 3001))"
  fi

  local WP_API
  WP_API="http://localhost:$(env_get PORT || echo 3001)"
  if curl -s -o /dev/null --max-time 4 "$WP_API"; then
    local CODE
    CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 4 "$WP_API/")
    echo -e "  ${BOLD}Health:${RESET} web -> ${GREEN}HTTP ${CODE}${RESET}"
  else
    echo -e "  ${BOLD}Health:${RESET} web -> ${RED}unreachable${RESET}"
  fi
  local API_URL="http://localhost:$(env_get API_PORT || echo 3002)/api/status"
  if curl -s --max-time 4 "$API_URL" | grep -q '"success":true'; then
    echo -e "          api  -> ${GREEN}ok${RESET}"
  else
    echo -e "          api  -> ${RED}unreachable${RESET}"
  fi

  if [[ -f data/nobitahost.db ]]; then
    local SIZE
    SIZE=$(du -h data/nobitahost.db | cut -f1)
    echo -e "  ${BOLD}Database:${RESET} data/nobitahost.db (${SIZE})"
  fi

  if command -v pm2 >/dev/null 2>&1; then
    pm2 list --no-color 2>/dev/null | grep -E "nobitahost|┌|├|└" || true
  fi
}

# ---------------------------------------------------------------------------

user_cmd() {
  local sub="${1:-}"
  shift || true

  case "$sub" in
    create)
      npm run createuser -- "$@"
      ;;
    list)
      step "NobitaHost · Users"
      node - <<'EOF'
const path = require('path');
const db = require(path.join(process.cwd(), 'src', 'db'));
const rows = db.prepare("SELECT id, username, email, role, status, created_at FROM users ORDER BY id").all();
if (!rows.length) { console.log('No users found.'); process.exit(0); }
console.log('ID  USERNAME   ROLE      STATUS     EMAIL                CREATED');
for (const r of rows) {
  console.log(
    String(r.id).padEnd(4),
    (r.username || '').padEnd(10),
    (r.role || '').padEnd(10),
    (r.status || '').padEnd(10),
    (r.email || '').padEnd(20),
    r.created_at || ''
  );
}
EOF
      ;;
    delete)
      local target="${1:-}"
      [[ -z "$target" ]] && { err "Usage: ./run.sh user delete <id|username> [--force]"; exit 1; }
      if [[ "${2:-}" != "--force" ]]; then
        read -rp "Delete user '$target'? [y/N] " ans || true
        [[ "$ans" =~ ^[yY]$ ]] || { info "Aborted"; exit 0; }
      fi
      node - "$target" <<'EOF'
const path = require('path');
const db = require(path.join(process.cwd(), 'src', 'db'));
const target = process.argv[2];
const isNum = /^\d+$/.test(target);
const user = isNum
  ? db.prepare('SELECT * FROM users WHERE id = ?').get(Number(target))
  : db.prepare('SELECT * FROM users WHERE username = ?').get(target);
if (!user) { console.error("✘ User not found: " + target); process.exit(1); }
if (user.role === 'admin') {
  const admins = db.prepare("SELECT COUNT(*) n FROM users WHERE role = 'admin'").get().n;
  if (admins <= 1) { console.error("✘ Cannot delete the last admin."); process.exit(1); }
}
db.prepare('DELETE FROM users WHERE id = ?').run(user.id);
console.log('✔ User deleted: ' + user.username + ' (#' + user.id + ')');
EOF
      ;;
    *)
      echo -e "Usage: ./run.sh user {create|list|delete}"
      echo -e "  create  --username u --email u@x.com --password p --role user|admin"
      echo -e "  delete  <id|username> [--force]"
      ;;
  esac
}

# ---------------------------------------------------------------------------

show_logs() {
  local app="${1:-nobitahost-web}"
  if command -v pm2 >/dev/null 2>&1; then
    pm2 logs "$app"
  else
    err "PM2 not installed"
    exit 1
  fi
}

# ---------------------------------------------------------------------------

show_help() {
  cat <<EOF
${BOLD}NobitaHost — auto install / manage${RESET}

Usage: ./run.sh <command> [options]

  install [--port N] [--url https://...]   Full install: deps, .env, DB seed, PM2 + boot
  uninstall [--purge]                      Stop & remove from PM2 (--purge deletes files)
  update                                   git pull (if git), reinstall, rebuild, restart
  restart                                  Restart both PM2 apps
  status                                   Panel status, ports, health + PM2 table
  logs [web|api]                           Follow PM2 logs
  user create --username u --email u@x --password p --role admin
  user list
  user delete <id|username> [--force]
  help                                     Show this help

Examples:
  ./run.sh install
  ./run.sh install --port 8080 --url https://panel.example.com
  ./run.sh user create --username john --email john@x.com --password secret123 --role admin
  ./run.sh status
EOF
}

# ---------------------------------------------------------------------------

CMD="${1:-help}"
shift || true

case "$CMD" in
  install)   install_app "$@" ;;
  uninstall) uninstall_app "$@" ;;
  update)    update_app ;;
  restart)   restart_app ;;
  status)    show_status ;;
  logs)      show_logs "${1:-nobitahost-web}" ;;
  user)      user_cmd "$@" ;;
  help|-h|--help) show_help ;;
  *)         echo -e "${RED}Unknown command: ${CMD}${RESET}"; show_help; exit 1 ;;
esac
