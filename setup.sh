#!/usr/bin/env bash
# ============================================================
# Content Factory — интерактивный установщик (curl | bash)
#   curl -sSL https://raw.githubusercontent.com/workzoneyou/content-factory-installer/main/setup.sh | bash
# Разворачивает WordPress + n8n + Traefik/SSL. Вопросы всплывают при запуске.
# ============================================================
set -euo pipefail

REPO_TARBALL="https://github.com/workzoneyou/content-factory-installer/archive/refs/heads/main.tar.gz"

RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; BLUE=$'\033[0;34m'; NC=$'\033[0m'
hdr(){ echo -e "\n${BLUE}════ $* ════${NC}"; }
ok(){ echo -e "${GREEN}✓${NC} $*"; }
warn(){ echo -e "${YELLOW}⚠${NC} $*"; }
die(){ echo -e "${RED}✗${NC} $*" >&2; exit 1; }

# --- ввод через /dev/tty (работает при curl | bash) ---
ask(){ # ask "Вопрос" ПЕРЕМЕННАЯ ["дефолт"]
  local prompt="$1" var="$2" def="${3:-}" val
  # неинтерактивный режим: если переменная уже задана в окружении — берём её, не спрашиваем
  if [ -n "${!var:-}" ]; then echo -e "${YELLOW}?${NC} ${prompt}: ${!var}"; return; fi
  local hint=""; [ -n "$def" ] && hint=" [${def}] (Enter — оставить)"
  read -rp "$(echo -e "${YELLOW}?${NC} ${prompt}${hint}: ")" val < /dev/tty || val=""
  [ -z "$val" ] && val="$def"
  printf -v "$var" '%s' "$val"
}
ask_secret(){ local prompt="$1" var="$2" val; read -rsp "$(echo -e "${YELLOW}?${NC} ${prompt}: ")" val < /dev/tty; echo; printf -v "$var" '%s' "$val"; }
clean_domain(){ echo "$1" | sed -e 's|^[^/]*//||' -e 's|/.*$||' -e 's|[[:space:]]||g' | tr 'A-Z' 'a-z'; }
valid_email(){ [[ "$1" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; }

[[ $EUID -eq 0 ]] || die "Запустите под root:  curl -sSL … | sudo bash   (или sudo -i, затем команда)"

# Логотип: рамку считаем программно (центрируем ASCII-строки), правый край всегда ровный
_bw=42; _rule=$(printf '─%.0s' $(seq "$_bw"))
_bline(){ local s="$1"; local n=${#s}; local l=$(( (_bw-n)/2 )); local r=$(( _bw-n-l )); printf '  │%*s%s%*s│\n' "$l" '' "$s" "$r" ''; }
echo
echo "  ┌${_rule}┐"
_bline "FIRSANOV TEAM"
_bline "Content Factory installer"
_bline "WordPress + n8n + SSL"
echo "  └${_rule}┘"

# ── Docker + зависимости ────────────────────────────────────
command -v curl >/dev/null || { apt-get update -qq && apt-get install -y -qq curl; }
if ! command -v docker >/dev/null; then hdr "Ставлю Docker"; curl -fsSL https://get.docker.com | sh; fi
docker compose version >/dev/null 2>&1 || die "Нужен docker compose v2"
ok "Docker готов"

# ── ВОПРОСЫ (только то, что нужно для самой установки) ───────
# Всё остальное (название сайта, автор, соцсети, Метрика) настраивается
# в самом WordPress ПОСЛЕ установки — здесь не спрашиваем.
hdr "Параметры установки"
while :; do ask "Домен блога (напр. blog.site.ru)" BLOG_DOMAIN; BLOG_DOMAIN=$(clean_domain "$BLOG_DOMAIN"); [ -n "$BLOG_DOMAIN" ] && break; warn "Домен обязателен"; done
while :; do ask "Домен n8n (напр. n8n.site.ru)"  N8N_DOMAIN;  N8N_DOMAIN=$(clean_domain "$N8N_DOMAIN");  [ -n "$N8N_DOMAIN"  ] && break; warn "Домен обязателен"; done
while :; do ask "Email для SSL-сертификата (Let's Encrypt)" SSL_EMAIL; valid_email "$SSL_EMAIL" && break; warn "Некорректный email"; done
ask "Логин WP-админа" WP_ADMIN_USER "admin"
ask "Email WP-админа" WP_ADMIN_EMAIL "$SSL_EMAIL"

# ── Фиксированные значения (точная копия системы Firsanov, не спрашиваем) ──
BRAND=firsanov                       # имена контейнеров/сети/папок И папка темы:
                                     # CSS и шрифты темы жёстко ждут /wp-content/themes/firsanov/
SITE_NAME="Блог"                     # заголовок сайта — меняется в WP: Настройки → Общие
BRAND_URL="https://${BLOG_DOMAIN}"
YANDEX_METRIKA_ID=""                 # Метрику подключают в WP уже после установки

# DNS-подсказка
SRV_IP=$(curl -s --max-time 8 https://ifconfig.me || echo "")
for d in "$BLOG_DOMAIN" "$N8N_DOMAIN"; do
  # || rip="" ОБЯЗАТЕЛЬНО: если домен не резолвится, getent выходит с кодом 2,
  # pipefail пробрасывает его в присваивание и set -e молча убивает установщик.
  rip=$(getent hosts "$d" 2>/dev/null | awk '{print $1}' | head -1) || rip=""
  [ -z "$rip" ] && warn "Домен $d пока не направлен на сервер. A-запись → ${SRV_IP:-<IP сервера>} (SSL не выпустится без этого)"
done

# генерируем секреты
gen(){ openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }
WP_ADMIN_PASSWORD=$(gen); DB_PASSWORD=$(gen); DB_ROOT_PASSWORD=$(gen); N8N_DB_PASSWORD=$(gen); N8N_ENCRYPTION_KEY=$(openssl rand -hex 16)

# ── Бутстрап: качаем репо целиком (если запущено как curl|bash) ──
if [ ! -f "wordpress/docker-compose.yml" ]; then
  hdr "Скачиваю компоненты"
  DEST="$HOME/${BRAND}-factory"
  mkdir -p "$DEST"; tmp=$(mktemp)
  curl -fsSL "$REPO_TARBALL" -o "$tmp" || die "Не удалось скачать репозиторий (публичный ли он?)"
  tar xzf "$tmp" -C "$DEST" --strip-components=1; rm -f "$tmp"
  cd "$DEST"; ok "Компоненты в $DEST"
fi
ROOT="$(pwd)"

# ── .env из ответов ─────────────────────────────────────────
DB_NAME=wordpress; DB_USER=wp; TZ=Europe/Moscow
cat > .env <<EOF
BRAND=$BRAND
BRAND_URL=$BRAND_URL
BLOG_DOMAIN=$BLOG_DOMAIN
N8N_DOMAIN=$N8N_DOMAIN
SSL_EMAIL=$SSL_EMAIL
SITE_NAME=$SITE_NAME
WP_ADMIN_USER=$WP_ADMIN_USER
WP_ADMIN_EMAIL=$WP_ADMIN_EMAIL
WP_ADMIN_PASSWORD=$WP_ADMIN_PASSWORD
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD
YANDEX_METRIKA_ID=$YANDEX_METRIKA_ID
N8N_DB_PASSWORD=$N8N_DB_PASSWORD
N8N_ENCRYPTION_KEY=$N8N_ENCRYPTION_KEY
TZ=$TZ
EOF
ok "Конфигурация сохранена в .env"
set -a; . ./.env; set +a
WP="${BRAND}-wp"; wp_root(){ docker exec "$WP" wp --allow-root --path=/var/www/html "$@"; }

# firewall
if command -v ufw >/dev/null; then for p in 22 80 443; do ufw allow ${p}/tcp >/dev/null 2>&1||true; done; ufw status|grep -q "Status: active"||ufw --force enable >/dev/null; fi

# ── WordPress ───────────────────────────────────────────────
hdr "Разворачиваю WordPress"
mkdir -p wordpress/wordpress wordpress/db-data wordpress/traefik/letsencrypt wordpress/backup
touch wordpress/traefik/letsencrypt/acme.json; chmod 600 wordpress/traefik/letsencrypt/acme.json
( cd wordpress && docker compose --env-file ../.env up -d )
for i in $(seq 1 60); do docker exec "$WP" curl -sf http://localhost/ -o /dev/null 2>&1 && break; sleep 2; [ $i -eq 60 ] && die "WP не стартовал"; done
ok "WP поднят"
docker exec "$WP" which wp >/dev/null 2>&1 || docker exec "$WP" bash -c "curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp"
wp_root core is-installed 2>/dev/null || wp_root core install --url="https://${BLOG_DOMAIN}" --title="${SITE_NAME}" --admin_user="${WP_ADMIN_USER}" --admin_password="${WP_ADMIN_PASSWORD}" --admin_email="${WP_ADMIN_EMAIL}" --skip-email
wp_root language core install ru_RU --activate 2>/dev/null || true
wp_root rewrite structure '/%postname%/' --hard >/dev/null
wp_root option update blogdescription '' >/dev/null
wp_root theme is-installed firsanov 2>/dev/null && wp_root theme activate firsanov >/dev/null || die "Тема firsanov не найдена"
ok "WordPress установлен, тема firsanov активна"
bash wordpress/scripts/install-plugins.sh
docker cp wordpress/scripts/configure-plugins.php "$WP:/tmp/cfg.php"
docker exec -e YANDEX_METRIKA_ID="${YANDEX_METRIKA_ID:-}" "$WP" php /tmp/cfg.php
bash wordpress/scripts/wp-settings.sh

# ── n8n ─────────────────────────────────────────────────────
hdr "Разворачиваю n8n"
mkdir -p n8n/n8n-db n8n/n8n-data
# n8n внутри работает под uid 1000 (node). Если папка данных принадлежит root —
# EACCES на /home/node/.n8n/config и крэш-луп. Отдаём её node заранее.
chown -R 1000:1000 n8n/n8n-data
( cd n8n && docker compose --env-file ../.env up -d )
ok "n8n поднят (пустой — воркфлоу и креды заводятся отдельно)"

# ── Доступы отдельным файлом (чтобы не потерялись) ───────────
cat > "${ROOT}/ДОСТУПЫ.txt" <<TXT
WordPress
  Адрес:  https://${BLOG_DOMAIN}/wp-admin
  Логин:  ${WP_ADMIN_USER}
  Пароль: ${WP_ADMIN_PASSWORD}

n8n
  Адрес:  https://${N8N_DOMAIN}
  Вход:   логина заранее нет — при первом заходе n8n попросит
          создать аккаунт владельца (свой email + любой пароль)

Все секреты (пароли БД, ключ n8n): ${ROOT}/.env
TXT
chmod 600 "${ROOT}/ДОСТУПЫ.txt"

# ── Итог ────────────────────────────────────────────────────
cat <<EOF

${GREEN}════════════════════════════════════════════════════════════${NC}
${GREEN}✓ Установка завершена${NC}

${YELLOW}━━━━━━━━━━━━━━ ДАННЫЕ ДЛЯ ВХОДА ━━━━━━━━━━━━━━${NC}

  WordPress
    Адрес:   https://${BLOG_DOMAIN}/wp-admin
    Логин:   ${WP_ADMIN_USER}
    Пароль:  ${WP_ADMIN_PASSWORD}

  n8n
    Адрес:   https://${N8N_DOMAIN}
    Вход:    логина заранее нет — при первом заходе n8n сам
             попросит создать аккаунт владельца (email + пароль).

  Сохранено в файл: ${ROOT}/ДОСТУПЫ.txt
  Все секреты:      ${ROOT}/.env   (сохраните в бэкап!)
${GREEN}════════════════════════════════════════════════════════════${NC}

${YELLOW}Настроить в WordPress (wp-admin):${NC}
  • Настройки → Общие: название сайта.
  • Профиль автора: имя, описание, ВК/Telegram/RuTube/TenChat (поля уже добавлены).
  • Метрика: вставить ID счётчика в настройках плагина.

${YELLOW}Настроить в n8n:${NC} импорт воркфлоу, креды (OpenRouter/KIE/Perplexity/Google/WP),
  подключить Google-таблицу (лист ОПУБЛИКОВАНО оставить пустым), залить темы → тест-прогон.
  Первый SSL — 30–60 сек: curl -sI https://${BLOG_DOMAIN}
EOF
