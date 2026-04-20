#!/bin/sh
set -e

# ─── Frontend dev entrypoint ───────────────────────────────────
# Ensures dependencies are installed before starting Vite dev server.
# Shared by all React frontend containers in the Maya ecosystem.
# ────────────────────────────────────────────────────────────────

cd /app

# Install dependencies when node_modules is missing/stale or vite binary is absent.
if [ ! -d "node_modules" ] \
  || [ ! -f "node_modules/.package-lock.json" ] \
  || [ "package.json" -nt "node_modules/.package-lock.json" ] \
  || [ "package-lock.json" -nt "node_modules/.package-lock.json" ] \
  || [ ! -x "node_modules/.bin/vite" ]; then
    echo "[entrypoint] Installing npm dependencies..."
    npm install
else
    echo "[entrypoint] Dependencies up to date, skipping npm install"
fi

echo "[entrypoint] Starting Vite dev server..."
exec npm run dev -- --host 0.0.0.0
