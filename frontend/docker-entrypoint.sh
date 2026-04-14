#!/bin/sh
set -e

# ─── Frontend dev entrypoint ───────────────────────────────────
# Ensures dependencies are installed before starting Vite dev server.
# Shared by all React frontend containers in the Maya ecosystem.
# ────────────────────────────────────────────────────────────────

cd /app

# Install dependencies if node_modules is missing or package.json changed
if [ ! -d "node_modules" ] || [ "package.json" -nt "node_modules/.package-lock.json" ]; then
    echo "[entrypoint] Installing npm dependencies..."
    npm install
else
    echo "[entrypoint] Dependencies up to date, skipping npm install"
fi

echo "[entrypoint] Starting Vite dev server..."
exec npm run dev -- --host 0.0.0.0
