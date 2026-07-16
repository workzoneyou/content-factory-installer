#!/usr/bin/env bash
# ============================================================
# Импорт воркфлоу в n8n + подстановка домена и Google Sheet ID.
# Запускается из install.sh и вручную (после того как завели
# Google-таблицу и вписали GOOGLE_SHEET_ID в .env).
# ============================================================
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"; set -a; . ./.env; set +a
N8N="${BRAND}-n8n"; DB="${BRAND}-n8n-db"
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
ok(){ echo -e "${GREEN}✓${NC} $*"; }; warn(){ echo -e "${YELLOW}⚠${NC} $*"; }

# уже импортированы? (идемпотентность — не плодим дубли)
CNT=$(docker exec "$DB" psql -U n8n -d n8n -t -A -c "select count(*) from workflow_entity" 2>/dev/null | tr -d ' ' || echo 0)
if [[ "${CNT:-0}" -gt 0 ]]; then
  warn "В n8n уже есть воркфлоу ($CNT шт.) — импорт пропущен."
  warn "Чтобы обновить Sheet ID в уже импортированных — правьте в UI или пересоздайте n8n-db."
  exit 0
fi

if [[ -z "${GOOGLE_SHEET_ID:-}" ]]; then
  warn "GOOGLE_SHEET_ID пуст. Скопируйте шаблон таблиц (google/SHEETS_TEMPLATE.md),"
  warn "впишите ID в .env и запустите: bash n8n/setup.sh"
  exit 0
fi

# подстановка плейсхолдеров
tmp=$(mktemp)
sed "s|__BLOG_DOMAIN__|${BLOG_DOMAIN}|g; s|__N8N_DOMAIN__|${N8N_DOMAIN}|g; s|__SHEET_ID__|${GOOGLE_SHEET_ID}|g" \
  n8n/workflows/all.json > "$tmp"

docker cp "$tmp" "$N8N:/tmp/wf.json" >/dev/null
if docker exec -u node "$N8N" n8n import:workflow --input=/tmp/wf.json 2>/dev/null; then
  ok "Воркфлоу импортированы (6 шт.)"
else
  warn "CLI-импорт не прошёл. Импортируйте вручную: n8n → ⋯ → Import from File → n8n/workflows/all.json"
fi
docker exec "$N8N" rm -f /tmp/wf.json 2>/dev/null || true
rm -f "$tmp"
docker restart "$N8N" >/dev/null
ok "n8n перезапущен, воркфлоу загружены"

cat <<EOF
${YELLOW}Дальше вручную в n8n (${N8N_DOMAIN}):${NC}
  • Credentials → создать: Google Sheets (OAuth2), OpenRouter, KIE (Header Auth: Authorization=Bearer <key>),
    Perplexity, WordPress (Basic Auth: ${WP_ADMIN_USER} + пароль приложения).
  • Открыть каждый из 6 воркфлоу → в нодах с красным значком выбрать созданные креды.
  • Проверить, что лист ОПУБЛИКОВАНО в таблице ПУСТОЙ (иначе внутренние ссылки будут 404).
  • Активировать МАСТЕР (расписание) или запускать вручную «Execute workflow».
EOF
