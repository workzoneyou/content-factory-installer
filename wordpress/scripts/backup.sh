#!/usr/bin/env bash
# Бэкап: БД + uploads + .env → backup/<BRAND>-YYYYMMDD-HHMM.tar.gz
# (это резервный локальный бэкап; основной — UpdraftPlus → Google Drive)
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"   # корень репо
cd "$ROOT"
set -a; . ./.env; set +a
ts=$(date +%Y%m%d-%H%M)
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT
mkdir -p backup
docker exec "${BRAND}-db" mysqldump -uroot -p"${DB_ROOT_PASSWORD}" \
  --single-transaction --quick --lock-tables=false "${DB_NAME}" > "${tmp}/db.sql"
cp .env "${tmp}/.env"
tar czf "backup/${BRAND}-${ts}.tar.gz" -C "$tmp" db.sql .env \
  -C "$ROOT/wordpress" wordpress/wp-content/uploads 2>/dev/null || true
echo "✓ Бэкап: backup/${BRAND}-${ts}.tar.gz"
