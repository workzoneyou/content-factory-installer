#!/usr/bin/env bash
# ============================================================
# Content Factory — авто-установщик (WordPress + n8n + Traefik/SSL)
# ------------------------------------------------------------
# Разворачивает контент-завод на чистом Ubuntu 22.04+ сервере.
# Идемпотентен — можно запускать повторно.
#
#   1. cp .env.example .env
#   2. nano .env          # заполнить BRAND, домены, SSL_EMAIL, автора
#   3. sudo ./install.sh
# ============================================================
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log(){ echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*"; }
ok(){  echo -e "${GREEN}✓${NC} $*"; }
warn(){ echo -e "${YELLOW}⚠${NC} $*"; }
die(){ echo -e "${RED}✗${NC} $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Запустите под root: sudo ./install.sh"

# ── .env ────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
  cp .env.example .env
  warn "Создан .env из шаблона. Заполните BRAND / домены / SSL_EMAIL и запустите снова."
  exit 0
fi
set -a; . ./.env; set +a

[[ -n "${BRAND:-}" ]]           || die "Укажите BRAND в .env"
[[ "${BLOG_DOMAIN:-}" != "blog.example.com" && -n "${BLOG_DOMAIN:-}" ]] || die "Укажите BLOG_DOMAIN в .env"
[[ "${N8N_DOMAIN:-}"  != "n8n.example.com"  && -n "${N8N_DOMAIN:-}"  ]] || die "Укажите N8N_DOMAIN в .env"
[[ "${SSL_EMAIL:-}"   != "admin@example.com" && -n "${SSL_EMAIL:-}" ]] || die "Укажите SSL_EMAIL в .env"

gen(){ openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }
set_env(){ grep -qE "^$1=" .env && sed -i.bak "s|^$1=.*|$1=$2|" .env || echo "$1=$2" >> .env; }
for v in WP_ADMIN_PASSWORD DB_PASSWORD DB_ROOT_PASSWORD N8N_DB_PASSWORD; do
  if [[ -z "${!v:-}" ]]; then val=$(gen); set_env "$v" "$val"; log "Сгенерирован $v"; fi
done
[[ -n "${N8N_ENCRYPTION_KEY:-}" ]] || { key=$(openssl rand -hex 16); set_env N8N_ENCRYPTION_KEY "$key"; log "Сгенерирован N8N_ENCRYPTION_KEY"; }
set -a; . ./.env; set +a; rm -f .env.bak

WP="${BRAND}-wp"
wp_root(){ docker exec "$WP" wp --allow-root --path=/var/www/html "$@"; }

# ── Docker ──────────────────────────────────────────────────
if ! command -v docker >/dev/null; then
  log "Ставлю Docker"; curl -fsSL https://get.docker.com | sh; ok "Docker установлен"
else ok "Docker: $(docker --version)"; fi
docker compose version >/dev/null 2>&1 || die "Нужен docker compose v2"

# ── Firewall ────────────────────────────────────────────────
if command -v ufw >/dev/null; then
  for p in 22 80 443; do ufw allow ${p}/tcp >/dev/null 2>&1 || true; done
  ufw status | grep -q "Status: active" || ufw --force enable >/dev/null
  ok "Firewall: 22, 80, 443"
fi

# ============================================================
# WORDPRESS-СТЕК
# ============================================================
log "── Разворачиваю WordPress-стек ──"
mkdir -p wordpress/wordpress wordpress/db-data wordpress/traefik/letsencrypt wordpress/backup
touch wordpress/traefik/letsencrypt/acme.json
chmod 600 wordpress/traefik/letsencrypt/acme.json
( cd wordpress && docker compose --env-file ../.env up -d )
ok "WP-контейнеры запущены"

log "Жду WordPress (до 2 мин)..."
for i in $(seq 1 60); do
  docker exec "$WP" curl -sf http://localhost/ -o /dev/null 2>&1 && { ok "WP отвечает"; break; }
  sleep 2; [[ $i -eq 60 ]] && die "WP не стартовал"
done

# WP-CLI (теряется при рестарте контейнера — ставим при каждом запуске)
docker exec "$WP" which wp >/dev/null 2>&1 || docker exec "$WP" bash -c \
  "curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"
ok "WP-CLI готов"

if ! wp_root core is-installed 2>/dev/null; then
  wp_root core install --url="https://${BLOG_DOMAIN}" --title="${SITE_NAME}" \
    --admin_user="${WP_ADMIN_USER}" --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" --skip-email
  ok "WordPress установлен"
else ok "WordPress уже установлен"; fi

wp_root language core install ru_RU --activate 2>/dev/null || true
wp_root rewrite structure '/%postname%/' --hard >/dev/null
wp_root option update blogdescription '' >/dev/null
ok "Локаль ru_RU, permalinks /%postname%/"

# Тема
wp_root theme is-installed "${BRAND}" 2>/dev/null && wp_root theme activate "${BRAND}" >/dev/null \
  && ok "Тема ${BRAND} активна" || die "Тема ${BRAND} не найдена в themes/"

# Плагины + конфиг
bash wordpress/scripts/install-plugins.sh
docker cp wordpress/scripts/configure-plugins.php "$WP:/tmp/cfg.php"
docker exec -e YANDEX_METRIKA_ID="${YANDEX_METRIKA_ID:-}" "$WP" php /tmp/cfg.php
ok "Плагины настроены"

# Наши настройки (aspect-ratio 3/2, права, Yoast noindex, автор, соцполя)
bash wordpress/scripts/wp-settings.sh

# ============================================================
# n8n-СТЕК
# ============================================================
log "── Разворачиваю n8n-стек ──"
mkdir -p n8n/n8n-db n8n/n8n-data
( cd n8n && docker compose --env-file ../.env up -d )
ok "n8n-контейнеры запущены"
log "Жду n8n (до 2 мин)..."
for i in $(seq 1 60); do
  docker exec "${BRAND}-n8n" wget -qO- http://localhost:5678/healthz >/dev/null 2>&1 && { ok "n8n отвечает"; break; }
  sleep 2; [[ $i -eq 60 ]] && warn "n8n не ответил на healthz за 2 мин — проверьте логи"
done
bash n8n/setup.sh

# ============================================================
# ИТОГ
# ============================================================
cat <<EOF

${GREEN}════════════════════════════════════════════════════════════${NC}
${GREEN}✓ Инфраструктура развёрнута${NC}
${GREEN}════════════════════════════════════════════════════════════${NC}
  Блог:     https://${BLOG_DOMAIN}   (admin: ${WP_ADMIN_USER} / ${WP_ADMIN_PASSWORD})
  n8n:      https://${N8N_DOMAIN}
  DB root:  ${DB_ROOT_PASSWORD}
  Секреты — в .env. Обязательно сохраните его в бэкап.

${YELLOW}ОСТАЛОСЬ РУЧНОЕ (см. README, шаги 6–8):${NC}
  1. n8n → Credentials: завести OpenRouter, KIE, Perplexity, Google Sheets(OAuth), WP(basic auth)
  2. Открыть 6 воркфлоу, выбрать в нодах эти креды (n8n подсветит красным)
  3. Скопировать шаблон Google-таблиц (google/SHEETS_TEMPLATE.md), вписать GOOGLE_SHEET_ID в .env, перезапустить n8n/setup.sh
  4. Google Drive OAuth для бэкапа статей (UpdraftPlus + n8n)
  5. Заполнить КОНВЕЙЕР темами, ПРОМПТЫ, ОФФЕРЫ → тест-прогон МАСТЕРА
  6. Первый SSL — 30–60 сек: curl -sI https://${BLOG_DOMAIN}
EOF
