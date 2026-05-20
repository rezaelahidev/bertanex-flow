#!/usr/bin/env bash
#
# Production build for Growth Orchestrator.
# Run from plugin root: npm run build:production
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Growth Orchestrator production build"
echo "    Root: $ROOT"

# --- PHP dependencies (Action Scheduler, autoload) ---
if ! command -v composer >/dev/null 2>&1; then
  echo "error: composer is required. Install Composer 2.x." >&2
  exit 1
fi

echo "==> composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

# --- Admin rules visual editor (Vite / React IIFE bundle) ---
if ! command -v npm >/dev/null 2>&1; then
  echo "error: npm is required." >&2
  exit 1
fi

echo "==> admin-rules-ui (vite production)"
cd "$ROOT/admin-rules-ui"
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi
npm run build:production
cd "$ROOT"

BUNDLE="$ROOT/assets/admin/rules-visual-editor.bundle.js"
if [[ ! -s "$BUNDLE" ]]; then
  echo "error: missing or empty bundle at assets/admin/rules-visual-editor.bundle.js" >&2
  exit 1
fi

# --- Persian translations (.mo) ---
if command -v msgfmt >/dev/null 2>&1; then
  echo "==> translations (msgfmt)"
  bash "$ROOT/languages/compile-translations.sh"
else
  echo "warn: msgfmt not found — skip compiling languages/*.mo"
fi

echo "==> Production build complete"
echo "    Bundle: assets/admin/rules-visual-editor.bundle.js ($(wc -c < "$BUNDLE" | tr -d ' ') bytes)"
echo "    Vendor: vendor/ (composer --no-dev)"
echo "    Ready to deploy this plugin directory to WordPress."
