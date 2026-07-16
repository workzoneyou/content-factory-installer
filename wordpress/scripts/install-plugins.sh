#!/usr/bin/env bash
# Скачивает и устанавливает плагины фиксированных версий.
# Вызывается из install.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

GREEN='\033[0;32m'; BLUE='\033[0;34m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*"; }
ok()   { echo -e "${GREEN}✓${NC} $*"; }

# slug:version:source
# source: "wp" (wordpress.org) | "wp-latest" (wordpress.org без пинна версии) | "github:owner/repo"
PLUGINS=(
  "indexnow:latest:wp-latest"
  "true-lazy-analytics:2.5.0:wp"
  "wordpress-seo:27.2:wp"
  "wp-fastest-cache:1.4.7:wp"
  "wp-graphql:2.10.1:wp"
  "wp-graphql-yoast-seo:v5.0.2:github:ashhitch/wp-graphql-yoast-seo"
)

mkdir -p plugins-zip

for entry in "${PLUGINS[@]}"; do
  slug=$(echo "$entry" | cut -d: -f1)
  version=$(echo "$entry" | cut -d: -f2)
  source=$(echo "$entry" | cut -d: -f3-)
  zip_file="plugins-zip/${slug}.${version}.zip"

  # Проверка: плагин уже активен?
  if docker exec ${BRAND}-wp wp --allow-root plugin is-active "$slug" 2>/dev/null; then
    ok "${slug} уже активен"
    continue
  fi

  # Определить URL
  case "$source" in
    wp)
      url="https://downloads.wordpress.org/plugin/${slug}.${version}.zip"
      ;;
    wp-latest)
      url="https://downloads.wordpress.org/plugin/${slug}.zip"
      ;;
    github:*)
      repo="${source#github:}"
      url="https://github.com/${repo}/releases/download/${version}/${slug}.zip"
      ;;
    *)
      echo "Неизвестный source: $source"; exit 1
      ;;
  esac

  # Скачать если нет
  if [[ ! -s "$zip_file" ]]; then
    log "Скачиваю ${slug} ${version}"
    curl -sLf "$url" -o "$zip_file" || { echo "Ошибка скачивания $url"; exit 1; }
  fi

  # Положить в контейнер и установить
  docker cp "$zip_file" "${BRAND}-wp:/tmp/${slug}.zip" >/dev/null
  docker exec ${BRAND}-wp wp --allow-root plugin install "/tmp/${slug}.zip" --force --activate >/dev/null
  docker exec ${BRAND}-wp rm -f "/tmp/${slug}.zip"
  ok "${slug} ${version} установлен и активирован"
done

ok "Все плагины готовы"
