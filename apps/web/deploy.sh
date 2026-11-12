#!/usr/bin/env bash
# Pull latest code and rebuild the app container on the server.
# Usage: ./deploy.sh [branch] [--seed] [--fresh]   (default branch: main)
#   --seed   run reference seeders (first deploy, or after reference data changes)
#   --fresh  drop all tables and re-run every seeder (asks for confirmation)
set -euo pipefail

cd "$(dirname "$0")"

BRANCH="main"
SEED=0
FRESH=0
for arg in "$@"; do
    case "$arg" in
        --seed) SEED=1 ;;
        --fresh) FRESH=1 ;;
        *) BRANCH="$arg" ;;
    esac
done

DC=(docker compose --env-file .env.production -f docker-compose.production.yml)

if [ ! -f .env.production ]; then
    echo ".env.production not found" >&2
    exit 1
fi

git pull origin "$BRANCH"
# --wait blocks until the healthcheck passes, i.e. entrypoint.sh has run migrations.
"${DC[@]}" up -d --build --wait --wait-timeout 300 simjadwal

if [ "$FRESH" -eq 1 ]; then
    read -r -p "This deletes ALL database data. Type 'fresh' to continue: " answer
    if [ "$answer" != "fresh" ]; then
        echo "Skipped migrate:fresh."
        exit 0
    fi
    "${DC[@]}" exec simjadwal php artisan migrate:fresh --seed --force
    "${DC[@]}" exec simjadwal php artisan cache:clear
elif [ "$SEED" -eq 1 ]; then
    # Demo seeders stay out in production unless SEED_DEMO=true is set in .env.production.
    "${DC[@]}" exec simjadwal php artisan db:seed --force
fi

"${DC[@]}" ps
