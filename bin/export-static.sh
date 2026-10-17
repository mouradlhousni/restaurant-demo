#!/usr/bin/env bash
#
# Builds the static showcase of the site into dist/.
#
#   bin/export-static.sh [--from <dir>]
#
# Environment:
#   WP_CLI    WP-CLI command for the local site (default: "wp"; with wp-env: "npx wp-env run cli wp").
#   SITE_URL  Public URL of the local site (default: `wp option get home`).
#   OUT       Output folder (default: dist).
#   PUBLIC_URL  Where dist/ will be deployed, for canonical URLs (optional; dropped when unset).
#
# Steps:
#   1. Turn OE_STATIC_BUILD on (restored on exit, even after an error).
#   2. Copy the site as HTML: a folder exported by the Simply Static plugin (--from),
#      or `wget --mirror` when available, or the dependency-free Node crawler.
#   3. Make every URL relative, remove wp-login.php, xmlrpc.php, /wp-json/ and admin links,
#      add _headers (security + cache), then validate that nothing dynamic is left.
#
# The result can be deployed as-is to Cloudflare Pages, Netlify or GitHub Pages,
# or previewed with `npx serve dist`.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${OUT:-$ROOT/dist}"
WP="${WP_CLI:-wp}"
FROM=""

while [[ $# -gt 0 ]]; do
	case "$1" in
		--from) FROM="$2"; shift 2 ;;
		-h|--help) sed -n '2,21p' "$0"; exit 0 ;;
		*) echo "Unknown option: $1" >&2; exit 1 ;;
	esac
done

wp_cli() {
	# shellcheck disable=SC2086
	$WP "$@"
}

SITE_URL="${SITE_URL:-$(wp_cli option get home | tr -d '\r')}"
SITE_URL="${SITE_URL%/}/"

echo "→ Exporting $SITE_URL to $OUT"

# 1. Static build flag, restored on exit.
PREVIOUS="$(wp_cli config get OE_STATIC_BUILD 2>/dev/null | tr -d '\r' || true)"
restore() {
	if [[ -z "$PREVIOUS" ]]; then
		wp_cli config delete OE_STATIC_BUILD --type=constant >/dev/null 2>&1 || true
	else
		wp_cli config set OE_STATIC_BUILD "$PREVIOUS" --raw --type=constant >/dev/null 2>&1 || true
	fi
	echo "→ OE_STATIC_BUILD restored"
}
trap restore EXIT

wp_cli config set OE_STATIC_BUILD true --raw --type=constant >/dev/null
wp_cli rewrite flush >/dev/null 2>&1 || true

# Start from a clean output folder (only ever inside the project).
case "$OUT" in
	"$ROOT"/*) mkdir -p "$OUT" && find "$OUT" -mindepth 1 -delete ;;
	*) echo "Refusing to clean $OUT: it must be inside $ROOT" >&2; exit 1 ;;
esac
mkdir -p "$OUT"

# 2. HTML snapshot.
if [[ -n "$FROM" ]]; then
	echo "→ Using the Simply Static export in $FROM"
	cp -R "$FROM"/. "$OUT"/
elif command -v wget >/dev/null 2>&1; then
	echo "→ Mirroring with wget"
	CUT_DIRS="$(echo "${SITE_URL#*://*/}" | awk -F/ '{ n = 0; for (i = 1; i <= NF; i++) if ($i != "") n++; print n }')"
	wget --mirror --page-requisites --no-parent --no-host-directories --cut-dirs="$CUT_DIRS" \
		--execute robots=off --quiet --directory-prefix="$OUT" \
		--reject-regex '(\?|wp-admin|wp-login\.php|xmlrpc\.php|wp-json|/feed/|/comments/)' \
		"$SITE_URL" || true
	wget --quiet --content-on-error --output-document="$OUT/404.html" "${SITE_URL}oe-static-404-page/" || true
else
	echo "→ wget not found: using the Node crawler"
	node "$ROOT/bin/static-export.js" crawl --site="$SITE_URL" --out="$OUT"
fi

# Leaflet requests its marker images by name at runtime: no file references them.
LEAFLET_OUT="$OUT/wp-content/plugins/oe-reservations/assets/vendor/leaflet"
if [[ -d "$LEAFLET_OUT" ]]; then
	rm -f "$LEAFLET_OUT/images/index.html"
	mkdir -p "$LEAFLET_OUT/images"
	cp "$ROOT/plugins/oe-reservations/assets/vendor/leaflet/images/"*.png "$LEAFLET_OUT/images/"
fi

# 3. Relative URLs, cleanup, headers, validation.
node "$ROOT/bin/static-export.js" finalize --site="$SITE_URL" --out="$OUT" --public="${PUBLIC_URL:-}"

echo "✓ Static site ready in $OUT — preview with: npx serve $(basename "$OUT")"
