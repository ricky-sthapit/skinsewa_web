#!/usr/bin/env bash
set -Eeuo pipefail

# Deploys the main branch from the dedicated repository checkout into the
# subdomain's Laravel application directory. Override the PHP executable on
# hosts that use a versioned command, for example:
# SKINSEWA_PHP_BIN=/usr/local/bin/ea-php83 ./scripts/deploy_skinsewa_api.sh

SKINSEWA_REPO_DIR="${SKINSEWA_REPO_DIR:-$HOME/repositories/skinsewa_api}"
SKINSEWA_DEPLOY_DIR="${SKINSEWA_DEPLOY_DIR:-$HOME/deployments/skinsewa_api}"
SKINSEWA_BRANCH="${SKINSEWA_BRANCH:-main}"
SKINSEWA_PHP_BIN="${SKINSEWA_PHP_BIN:-php}"
SKINSEWA_COMPOSER_BIN="${SKINSEWA_COMPOSER_BIN:-composer}"
SKINSEWA_MANIFEST_NAME=".skinsewa_api_deploy_manifest"

for SKINSEWA_COMMAND in git tar "$SKINSEWA_PHP_BIN" "$SKINSEWA_COMPOSER_BIN"; do
    if ! command -v "$SKINSEWA_COMMAND" >/dev/null 2>&1; then
        printf 'Required command not found: %s\n' "$SKINSEWA_COMMAND" >&2
        exit 1
    fi
done

if [[ ! -e "$SKINSEWA_REPO_DIR/.git" ]]; then
    printf 'Repository checkout not found: %s\n' "$SKINSEWA_REPO_DIR" >&2
    printf 'Clone the repository there before running this deployment script.\n' >&2
    exit 1
fi

if [[ -n "$(git -C "$SKINSEWA_REPO_DIR" status --porcelain)" ]]; then
    printf 'Repository checkout has local changes. Commit or remove them before deploying.\n' >&2
    exit 1
fi

mkdir -p "$SKINSEWA_DEPLOY_DIR"

if [[ ! -f "$SKINSEWA_DEPLOY_DIR/.env" ]]; then
    printf 'Production .env is missing: %s/.env\n' "$SKINSEWA_DEPLOY_DIR" >&2
    printf 'Create and configure it before deploying. The script will not copy a development .env.\n' >&2
    exit 1
fi

if git -C "$SKINSEWA_REPO_DIR" ls-files --error-unmatch -- .env >/dev/null 2>&1; then
    printf 'Refusing to deploy: .env is tracked by Git. Remove it from Git before continuing.\n' >&2
    exit 1
fi

printf 'Pulling origin/%s...\n' "$SKINSEWA_BRANCH"
git -C "$SKINSEWA_REPO_DIR" pull --ff-only origin "$SKINSEWA_BRANCH"

SKINSEWA_ARCHIVE="$(mktemp "${TMPDIR:-/tmp}/skinsewa_api.XXXXXXXX.tar")"
trap 'rm -f "$SKINSEWA_ARCHIVE"' EXIT

git -C "$SKINSEWA_REPO_DIR" archive --format=tar --output="$SKINSEWA_ARCHIVE" HEAD

printf 'Copying committed project files to %s...\n' "$SKINSEWA_DEPLOY_DIR"
"$SKINSEWA_PHP_BIN" -r 'printf("PHP %s\n", PHP_VERSION);'
tar -xf "$SKINSEWA_ARCHIVE" -C "$SKINSEWA_DEPLOY_DIR"

# Remove files that Git deleted since the previous deployment. The manifest
# contains only files managed by this script; runtime state stays in place.
SKINSEWA_MANIFEST="$SKINSEWA_DEPLOY_DIR/$SKINSEWA_MANIFEST_NAME"
if [[ -f "$SKINSEWA_MANIFEST" ]]; then
    while IFS= read -r -d '' SKINSEWA_OLD_FILE; do
        case "$SKINSEWA_OLD_FILE" in
            .env|vendor/*|storage/*|bootstrap/cache/*|public/storage|public/storage/*)
                continue
                ;;
        esac

        if ! git -C "$SKINSEWA_REPO_DIR" ls-files --error-unmatch -- "$SKINSEWA_OLD_FILE" >/dev/null 2>&1; then
            rm -f -- "$SKINSEWA_DEPLOY_DIR/$SKINSEWA_OLD_FILE"
        fi
    done < "$SKINSEWA_MANIFEST"
fi

SKINSEWA_NEXT_MANIFEST="$SKINSEWA_MANIFEST.next"
git -C "$SKINSEWA_REPO_DIR" ls-files -z > "$SKINSEWA_NEXT_MANIFEST"
mv -f "$SKINSEWA_NEXT_MANIFEST" "$SKINSEWA_MANIFEST"

mkdir -p \
    "$SKINSEWA_DEPLOY_DIR/storage/app/private" \
    "$SKINSEWA_DEPLOY_DIR/storage/framework/cache/data" \
    "$SKINSEWA_DEPLOY_DIR/storage/framework/sessions" \
    "$SKINSEWA_DEPLOY_DIR/storage/framework/views" \
    "$SKINSEWA_DEPLOY_DIR/storage/logs" \
    "$SKINSEWA_DEPLOY_DIR/bootstrap/cache"

chmod -R u+rwX \
    "$SKINSEWA_DEPLOY_DIR/storage" \
    "$SKINSEWA_DEPLOY_DIR/bootstrap/cache"

cd "$SKINSEWA_DEPLOY_DIR"

printf 'Installing production Composer dependencies...\n'
"$SKINSEWA_COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [[ "${SKINSEWA_RUN_MIGRATIONS:-0}" == "1" ]]; then
    printf 'Running database migrations...\n'
    "$SKINSEWA_PHP_BIN" artisan migrate --force
else
    printf 'Skipping migrations. Set SKINSEWA_RUN_MIGRATIONS=1 to run them.\n'
fi

printf 'Refreshing Laravel caches...\n'
"$SKINSEWA_PHP_BIN" artisan optimize:clear
"$SKINSEWA_PHP_BIN" artisan config:cache
"$SKINSEWA_PHP_BIN" artisan view:cache

printf 'Deployment complete: %s (%s)\n' \
    "$SKINSEWA_DEPLOY_DIR" \
    "$(git -C "$SKINSEWA_REPO_DIR" rev-parse --short HEAD)"
