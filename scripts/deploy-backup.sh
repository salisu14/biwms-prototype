#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

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

RELEASE_TAG="${1:-manual}"
TS="$(date +%F_%H%M%S)"
BACKUP_DIR="${BACKUP_DIR:-$APP_ROOT/storage/backups}"
mkdir -p "$BACKUP_DIR"

BASE="${DB_DATABASE}_${RELEASE_TAG}_${TS}"
FULL_DUMP="$BACKUP_DIR/${BASE}.dump"
SCHEMA_SQL="$BACKUP_DIR/${BASE}_schema.sql"

echo "[1/4] Putting app in maintenance mode..."
php artisan down --render='errors::503' || true

cleanup() {
  echo "[4/4] Bringing app up..."
  php artisan up || true
}
trap cleanup EXIT

export PGPASSWORD="$DB_PASSWORD"

echo "[2/4] Creating full backup: $FULL_DUMP"
pg_dump \
  -h "$DB_HOST" \
  -p "$DB_PORT" \
  -U "$DB_USERNAME" \
  -d "$DB_DATABASE" \
  -F c \
  -f "$FULL_DUMP"

echo "[3/4] Creating schema snapshot: $SCHEMA_SQL"
pg_dump \
  -h "$DB_HOST" \
  -p "$DB_PORT" \
  -U "$DB_USERNAME" \
  -d "$DB_DATABASE" \
  -s \
  -f "$SCHEMA_SQL"

echo "Backup complete"
echo "Full dump:   $FULL_DUMP"
echo "Schema dump: $SCHEMA_SQL"
