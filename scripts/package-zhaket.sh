#!/usr/bin/env bash
# Build the Zhaket upload archive.
# Root of the archive is Plugin/bertanex-flow.zip plus help.pdf.
# https://ircdn.zhaket.com/public/other/seller/Submit-Help-v1.3.pdf
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! command -v zip >/dev/null 2>&1; then
  echo "error: zip is required." >&2
  exit 1
fi

VERSION="$(
  grep -m1 'Version:' "$ROOT/bertanex-flow.php" \
    | sed -E 's/.*Version:[[:space:]]+//' \
    | tr -d '\r\n' \
    | awk '{print $1}'
)"

if command -v msgfmt >/dev/null 2>&1; then
  bash "$ROOT/languages/compile-translations.sh"
fi

STAGING="$(mktemp -d "${TMPDIR:-/tmp}/bertanex-flow-zhaket.XXXXXX")"
cleanup() { rm -rf "$STAGING"; }
trap cleanup EXIT

PLUGIN_STAGE="$STAGING/plugin-src"
mkdir -p "$PLUGIN_STAGE" "$STAGING/product/Plugin"
rsync -a --exclude-from="$ROOT/.distignore" --exclude docs --exclude scripts "$ROOT/" "$PLUGIN_STAGE/bertanex-flow/"
(cd "$PLUGIN_STAGE" && zip -rq "$STAGING/product/Plugin/bertanex-flow.zip" "bertanex-flow")

HELP_HTML="$ROOT/docs/zhaket/help.html"
HELP_PDF="$STAGING/product/help.pdf"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
if [[ -x "$CHROME" ]]; then
  "$CHROME" --headless --disable-gpu --no-pdf-header-footer --print-to-pdf="$HELP_PDF" "file://$HELP_HTML" >/dev/null 2>&1 || true
fi
if [[ ! -s "$HELP_PDF" ]]; then
  rm -f "$HELP_PDF"
  cp "$HELP_HTML" "$STAGING/product/help.html"
fi

OUT_DIR="$ROOT/dist"
mkdir -p "$OUT_DIR"
ARCHIVE="$OUT_DIR/bertanex-flow-${VERSION}-zhaket.zip"
rm -f "$ARCHIVE"
(cd "$STAGING/product" && zip -rq "$ARCHIVE" .)

echo "Zhaket archive: $ARCHIVE"
unzip -l "$ARCHIVE"
