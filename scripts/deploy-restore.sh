#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 /absolute/or/relative/path/to/backup.dump [--force]"
  exit 1
fi

DUMP_FILE="$1"
FORCE_FLAG="${2:-}"

if [[ ! -f "$DUMP_FILE" ]]; then
  echo "Error: dump file not found: $DUMP_FILE"
  exit 1
fi

if [[ "$FORCE_FLAG" != "--force" ]]; then
  echo "Safety check: restore will overwrite database '$DB_DATABASE'."
  echo "Re-run with --force to continue."
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Error: .env not found in $APP_ROOT"
  exit 1
fi

# shellcheck disable=SC1091
source .env

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
if [[ "$DB_CONNECTION" != "pgsql" ]]; then
  echo "Error: This script supports pgsql only. DB_CONNECTION=$DB_CONNECTION"
  exit 1
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
  echo "Error: DB_DATABASE/DB_USERNAME missing in .env"
  exit 1
fi

echo "[1/4] Putting app in maintenance mode..."
php artisan down --render='errors::503' || true

cleanup() {
  echo "[4/4] Bringing app up..."
  php artisan up || true
}
trap cleanup EXIT

export PGPASSWORD="$DB_PASSWORD"

echo "[2/4] Dropping and recreating database: $DB_DATABASE"
dropdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" --if-exists "$DB_DATABASE"
createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$DB_DATABASE"

echo "[3/4] Restoring from: $DUMP_FILE"
pg_restore \
  -h "$DB_HOST" \
  -p "$DB_PORT" \
  -U "$DB_USERNAME" \
  -d "$DB_DATABASE" \
  --clean \
  --if-exists \
  "$DUMP_FILE"

echo "Restore complete: $DUMP_FILE"
