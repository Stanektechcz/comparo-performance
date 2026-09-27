#!/usr/bin/env bash
# Comparo Performance — staging/production release deploy.
#
#   tools/deploy/deploy.sh <git-ref> [--maintenance]
#
# Layout under DEPLOY_ROOT (releases/shared/current — Laravel's classic
# zero-downtime layout; see docs/operations/staging.md and
# ~/.claude/reference-repos/panel-operations.md "Laravel deployment sequence"):
#
#   DEPLOY_ROOT/
#     releases/<timestamp>-<ref>/   one checkout per deploy, kept for 5 releases
#     shared/.env                   the real environment file (never in a release dir)
#     shared/storage/               persistent storage (uploads, logs, framework caches)
#     current -> releases/<latest>  atomic symlink swap
#
# Required environment:
#   DEPLOY_ROOT   absolute path containing releases/, shared/.env, shared/storage/
#   GIT_REPO      git remote/path to fetch the ref from
#
# Optional environment:
#   KEEP_RELEASES         releases to retain after a successful deploy (default 5)
#   RUN_SMOKE_TEST         "false" to skip the post-deploy smoke test (default true)
#   SMOKE_EMAIL / SMOKE_PASSWORD / SMOKE_AFTER_LOGIN   forwarded to smoke.mjs
#
# This script never runs a destructive database command: no migrate:fresh,
# migrate:reset, db:wipe, or force-drops. Migrations are `migrate --force`
# (additive/reversible-by-code-review) only.
set -euo pipefail

log() { printf '[deploy] %s\n' "$*" >&2; }
die() {
    printf '[deploy] ERROR: %s\n' "$*" >&2
    exit 1
}

# ---------------------------------------------------------------------------
# Arguments and environment
# ---------------------------------------------------------------------------

REF="${1:-}"
MAINTENANCE=false

for arg in "$@"; do
    case "$arg" in
        --maintenance) MAINTENANCE=true ;;
    esac
done

[ -n "$REF" ] || die "usage: deploy.sh <git-ref> [--maintenance]"
: "${DEPLOY_ROOT:?DEPLOY_ROOT must be set (e.g. /var/www/comparo-staging)}"
: "${GIT_REPO:?GIT_REPO must be set (git remote or bare-repo path to fetch <git-ref> from)}"

KEEP_RELEASES="${KEEP_RELEASES:-5}"
RUN_SMOKE_TEST="${RUN_SMOKE_TEST:-true}"

RELEASES_DIR="$DEPLOY_ROOT/releases"
SHARED_DIR="$DEPLOY_ROOT/shared"
CURRENT_LINK="$DEPLOY_ROOT/current"

[ -f "$SHARED_DIR/.env" ] || die "$SHARED_DIR/.env not found — create it from .env.staging.example first"
[ -d "$SHARED_DIR/storage" ] || die "$SHARED_DIR/storage not found — seed it from a fresh 'php artisan storage:link' release once"

TIMESTAMP="$(date -u +%Y%m%d%H%M%S)"
SAFE_REF="$(printf '%s' "$REF" | tr -c 'A-Za-z0-9._-' '-')"
RELEASE_DIR="$RELEASES_DIR/${TIMESTAMP}-${SAFE_REF}"
PREVIOUS_RELEASE=""
if [ -L "$CURRENT_LINK" ]; then
    PREVIOUS_RELEASE="$(readlink -f "$CURRENT_LINK" 2>/dev/null || true)"
fi

mkdir -p "$RELEASES_DIR"

# ---------------------------------------------------------------------------
# 1. Fetch + checkout the release
# ---------------------------------------------------------------------------

log "checking out $REF into $RELEASE_DIR"
git clone --quiet --no-checkout "$GIT_REPO" "$RELEASE_DIR"
git -C "$RELEASE_DIR" fetch --quiet --depth 1 origin "$REF"
git -C "$RELEASE_DIR" checkout --quiet FETCH_HEAD
COMMIT_SHA="$(git -C "$RELEASE_DIR" rev-parse HEAD)"
log "release $RELEASE_DIR at commit $COMMIT_SHA"

# ---------------------------------------------------------------------------
# 2. Link shared .env and storage
# ---------------------------------------------------------------------------

log "linking shared .env and storage"
ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
mkdir -p "$SHARED_DIR/storage/framework/"{cache,sessions,views}
mkdir -p "$SHARED_DIR/storage/logs"
mkdir -p "$SHARED_DIR/storage/app/public"

cd "$RELEASE_DIR"

# ---------------------------------------------------------------------------
# 3. Backend dependencies
# ---------------------------------------------------------------------------

log "composer install (no-dev, optimized autoloader)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

# ---------------------------------------------------------------------------
# 4. Frontend build (Inertia 3 + React 19 SSR via vite-plus/vp)
# ---------------------------------------------------------------------------
# Wayfinder's vite plugin runs `php artisan wayfinder:generate` during this
# build, so the app must already boot: shared/.env with a real APP_KEY.

log "npm ci"
npm ci

log "npm run build:ssr (client + SSR bundles; Wayfinder routes generated during this step)"
npm run build:ssr

# ---------------------------------------------------------------------------
# 5. Optional maintenance window
# ---------------------------------------------------------------------------

if [ "$MAINTENANCE" = true ]; then
    MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-$(openssl rand -hex 16)}"
    log "entering maintenance mode"
    if [ -n "$MAINTENANCE_SECRET" ]; then
        php artisan down --secret="$MAINTENANCE_SECRET" || die "php artisan down failed"
        log "maintenance bypass: ${APP_URL:-<APP_URL>}/${MAINTENANCE_SECRET}"
    else
        php artisan down || die "php artisan down failed"
    fi
fi

release_on_failure() {
    status=$?
    if [ "$MAINTENANCE" = true ]; then
        log "deploy failed (exit $status) — bringing the app back up"
        php artisan up || true
    fi
    if [ -n "$PREVIOUS_RELEASE" ]; then
        log "rollback: point $CURRENT_LINK back at $PREVIOUS_RELEASE and rerun the 'current' steps (storage:link, optimize, horizon:terminate, inertia:stop-ssr) there"
    fi
    exit "$status"
}
trap release_on_failure ERR

# ---------------------------------------------------------------------------
# 6. Migrate (additive/backward-compatible; never destructive) + role seed
# ---------------------------------------------------------------------------

log "php artisan migrate --force"
php artisan migrate --force

log "php artisan db:seed --class=RolesAndPermissionsSeeder --force"
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# ---------------------------------------------------------------------------
# 7. Caches and the public storage symlink
# ---------------------------------------------------------------------------

log "php artisan optimize"
php artisan optimize

log "php artisan storage:link"
php artisan storage:link || true

# ---------------------------------------------------------------------------
# 8. Atomic symlink swap
# ---------------------------------------------------------------------------

log "swapping $CURRENT_LINK -> $RELEASE_DIR"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK.tmp"
mv -Tf "$CURRENT_LINK.tmp" "$CURRENT_LINK"

# ---------------------------------------------------------------------------
# 9. Search index settings + worker/SSR restart (supervisor restarts them)
# ---------------------------------------------------------------------------

log "php artisan comparo:search:sync-settings"
php artisan comparo:search:sync-settings

log "php artisan horizon:terminate (supervisor restarts Horizon with the new release)"
php artisan horizon:terminate || true

log "php artisan inertia:stop-ssr (supervisor restarts the SSR server with the new release)"
php artisan inertia:stop-ssr || true

# ---------------------------------------------------------------------------
# 10. Leave maintenance mode
# ---------------------------------------------------------------------------

if [ "$MAINTENANCE" = true ]; then
    log "php artisan up"
    php artisan up
fi

trap - ERR

# ---------------------------------------------------------------------------
# 11. Smoke test
# ---------------------------------------------------------------------------

if [ "$RUN_SMOKE_TEST" = "true" ]; then
    APP_URL_VALUE="$(grep -E '^APP_URL=' "$SHARED_DIR/.env" | tail -n1 | cut -d= -f2- | tr -d '"')"
    if [ -z "$APP_URL_VALUE" ]; then
        log "WARNING: APP_URL not found in $SHARED_DIR/.env — skipping smoke test"
    else
        log "running smoke test against $APP_URL_VALUE"
        node "$RELEASE_DIR/tools/staging/smoke.mjs" "$APP_URL_VALUE" || die "smoke test failed against $APP_URL_VALUE"
    fi
else
    log "RUN_SMOKE_TEST=false — skipping smoke test"
fi

# ---------------------------------------------------------------------------
# 12. Prune old releases (keep the last KEEP_RELEASES)
# ---------------------------------------------------------------------------

log "pruning releases beyond the last $KEEP_RELEASES"
# shellcheck disable=SC2012
ls -1dt "$RELEASES_DIR"/*/ 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" | while IFS= read -r old; do
    old="${old%/}"
    if [ "$old" != "$RELEASE_DIR" ] && [ "$(readlink -f "$CURRENT_LINK" 2>/dev/null || true)" != "$old" ]; then
        log "removing old release $old"
        rm -rf "$old"
    fi
done

log "deploy complete: $RELEASE_DIR (commit $COMMIT_SHA)"
if [ -n "$PREVIOUS_RELEASE" ]; then
    log "rollback hint: ln -sfn '$PREVIOUS_RELEASE' '$CURRENT_LINK' && (cd '$PREVIOUS_RELEASE' && php artisan storage:link && php artisan optimize && php artisan horizon:terminate && php artisan inertia:stop-ssr)"
else
    log "no previous release recorded (first deploy) — no rollback target"
fi
