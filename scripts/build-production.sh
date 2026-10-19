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

# --- Release ZIP (outside plugin root, WordPress-ready folder layout) ---
if ! command -v zip >/dev/null 2>&1; then
  echo "error: zip is required to create the release archive." >&2
  exit 1
fi

PLUGIN_SLUG="$(basename "$ROOT")"
OUT_DIR="$(dirname "$ROOT")"
VERSION="$(
  grep -m1 'Version:' "$ROOT/bertanex-flow.php" \
    | sed -E 's/.*Version:[[:space:]]+//' \
    | tr -d '\r\n' \
    | awk '{print $1}'
)"
ZIP="$OUT_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
STAGING="$(mktemp -d "${TMPDIR:-/tmp}/bertanex-flow-build.XXXXXX")"
cleanup() {
  rm -rf "$STAGING"
}
trap cleanup EXIT

echo "==> release archive (rsync + zip)"
rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGING/$PLUGIN_SLUG/"
rm -f "$ZIP"
(cd "$STAGING" && zip -rq "$ZIP" "$PLUGIN_SLUG")

echo "==> Production build complete"
echo "    Bundle: assets/admin/rules-visual-editor.bundle.js ($(wc -c < "$BUNDLE" | tr -d ' ') bytes)"
echo "    Vendor: vendor/ (composer --no-dev)"
echo "    Zip:    $ZIP ($(wc -c < "$ZIP" | tr -d ' ') bytes)"
echo "    Ready to deploy the plugin directory or install from the zip."
