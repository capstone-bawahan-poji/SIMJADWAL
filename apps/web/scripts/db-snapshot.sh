#!/usr/bin/env bash
#
# Usage: scripts/db-snapshot.sh [--prod] save [name]
#        scripts/db-snapshot.sh [--prod] restore [name]
#        scripts/db-snapshot.sh [--prod] list
#
#   (default)  dev: Sail containers from compose.yaml, credentials from .env
#   --prod     production: docker-compose.production.yml, credentials from .env.production
#   name       snapshot name, default "demo". Files live in storage/snapshots/<name>.dump (git-ignored).
#
set -euo pipefail

cd "$(dirname "$0")/.."

PROD=0
if [ "${1:-}" = "--prod" ]; then
    PROD=1
    shift
fi
ACTION="${1:-}"
NAME="${2:-demo}"

if [ "$PROD" -eq 1 ]; then
    ENV_FILE=.env.production
    DC=(docker compose --env-file .env.production -f docker-compose.production.yml)
    APP=simjadwal
else
    ENV_FILE=.env
    DC=(docker compose -f compose.yaml)
    APP=laravel.test
fi

[ -f "$ENV_FILE" ] || { echo "$ENV_FILE not found" >&2; exit 1; }
env_value() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- | tr -d "\"'"; }
DB="${DB_NAME:-$(env_value DB_DATABASE)}"
USER_="$(env_value DB_USERNAME)"
SNAP_DIR=storage/snapshots
FILE="$SNAP_DIR/$NAME.dump"
psql_() { "${DC[@]}" exec -T pgsql psql -U "$USER_" -d postgres -v ON_ERROR_STOP=1 "$@"; }

case "$ACTION" in
    save)
        mkdir -p "$SNAP_DIR"
        "${DC[@]}" exec -T pgsql pg_dump -U "$USER_" -d "$DB" -Fc --no-owner > "$FILE.tmp"
        mv "$FILE.tmp" "$FILE"
        echo "Saved $FILE ($(du -h "$FILE" | cut -f1))"
        ;;
    restore)
        [ -f "$FILE" ] || { echo "$FILE not found. Run: $0 save $NAME" >&2; exit 1; }
        if [ "$PROD" -eq 1 ]; then
            read -r -p "Replace the PRODUCTION database '$DB' with snapshot '$NAME'? Type 'restore': " answer
            [ "$answer" = "restore" ] || { echo "Skipped."; exit 0; }
        fi
        psql_ -c "DROP DATABASE IF EXISTS \"$DB\" WITH (FORCE)" -c "CREATE DATABASE \"$DB\" OWNER \"$USER_\""
        "${DC[@]}" exec -T pgsql pg_restore -U "$USER_" -d "$DB" --no-owner < "$FILE"
        "${DC[@]}" exec -T "$APP" php artisan cache:clear >/dev/null 2>&1 || echo "cache:clear skipped (app container not running)"
        echo "Restored '$NAME' into $DB"
        ;;
    list)
        ls -lh "$SNAP_DIR"/*.dump 2>/dev/null || echo "No snapshots yet."
        ;;
    *)
        sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
        ;;
esac
