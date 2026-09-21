#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# NobitaHost · PHP edition — dev server launcher
#
#   ./serve.sh              start on :8080
#   PORT=9000 ./serve.sh    start on a custom port
#
# Uses a real PHP binary when one is installed. Otherwise it falls back to the
# php-wasm runtime (@php-wasm/cli from npm) so the panel runs anywhere Node does.
# ---------------------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")"

PORT="${PORT:-8080}"
HOST="${HOST:-0.0.0.0}"
# php-wasm runtime: honour $PHP_WASM_CLI, else look next to the repo, one level
# up (sandbox layout) and in $HOME — whichever exists first.
WASM_CLI="${PHP_WASM_CLI:-}"
if [ -z "$WASM_CLI" ]; then
  for candidate in \
    "$(cd .. && pwd)/php-runtime/node_modules/.bin/php-wasm-cli" \
    "$(cd ../.. && pwd)/php-runtime/node_modules/.bin/php-wasm-cli" \
    "${HOME}/php-runtime/node_modules/.bin/php-wasm-cli"; do
    if [ -x "$candidate" ]; then WASM_CLI="$candidate"; break; fi
  done
fi

# 1. seed / migrate the database
if command -v php >/dev/null 2>&1; then
  PHP_BIN="php"
elif [ -x "$WASM_CLI" ]; then
  PHP_BIN="$WASM_CLI"
elif command -v php-wasm-cli >/dev/null 2>&1; then
  PHP_BIN="php-wasm-cli"
else
  echo "No PHP runtime found."
  echo "  • install PHP 8.1+ (apt install php-cli php-sqlite3), or"
  echo "  • npm i -g @php-wasm/cli   (WASM PHP 8.5, no native build needed)"
  exit 1
fi

echo "[NobitaHost] PHP runtime: $PHP_BIN"
"$PHP_BIN" cli/setup.php

echo "[NobitaHost] Serving http://${HOST}:${PORT}  (Ctrl+C to stop)"
exec "$PHP_BIN" -S "${HOST}:${PORT}" router.php
