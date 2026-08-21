#!/bin/bash
# Auto-push: commits & pushes any pending changes to GitHub
# Runs via cron. Safe to run frequently — exits fast when nothing changed.

REPO="/root/nobitahost-wab"
LOCK="/tmp/nh-autopush.lock"
LOG="/root/.pm2/nh-autopush.log"

exec 9>"$LOCK"
if ! flock -n 9; then exit 0; fi

cd "$REPO" || exit 1

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG"; }

# Nothing to do?
if [ -z "$(git status --porcelain)" ]; then
  exit 0
fi

git add -A

if git diff --cached --quiet; then
  exit 0
fi

CHANGED=$(git diff --cached --numstat | wc -l)
git commit -m "Auto-push: ${CHANGED} files updated @ $(date '+%Y-%m-%d %H:%M')" >/dev/null 2>&1

if GIT_TERMINAL_PROMPT=0 git push origin main >>"$LOG" 2>&1; then
  log "Pushed ${CHANGED} files."
else
  log "ERROR: push failed, will retry next run."
  # reset the commit? No — keep it local, next successful push includes it.
fi

# Keep log small
if [ -f "$LOG" ] && [ "$(wc -l < "$LOG")" -gt 500 ]; then
  tail -100 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
