#!/usr/bin/env bash
# ============================================================
# Настройки WP поверх базовой установки — всё, что правили руками
# на Firsanov, чтобы система работала корректно.
# ============================================================
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"; set -a; . ./.env; set +a
WP="${BRAND}-wp"
wp_root(){ docker exec "$WP" wp --allow-root --path=/var/www/html "$@"; }
GREEN='\033[0;32m'; NC='\033[0m'; ok(){ echo -e "${GREEN}✓${NC} $*"; }

# 1. Пропорция картинок 3/2 — БЕЗ этого миниатюры в блоке «Читайте также»
#    схлопываются в высоту 0 (тема-мод не задан → aspect-ratio:auto + contain:strict).
wp_root theme mod set global_img_aspect_ratio '3 / 2' >/dev/null
ok "тема-мод global_img_aspect_ratio = 3/2"

# 2. Права на uploads/cache — строго www-data (НИКОГДА root!),
#    иначе WP не может перемещать загруженные картинки («не удалось переместить»).
docker exec "$WP" bash -c '
  mkdir -p wp-content/uploads wp-content/cache
  chown -R www-data:www-data wp-content/uploads wp-content/cache
  chmod -R 775 wp-content/uploads wp-content/cache' || true
ok "права www-data на uploads/cache"

# 3. Профиль автора (имя, bio, соцсети) НЕ настраивается здесь — заполняется
#    вручную в WP → Пользователи. Поля ВК/TG/RuTube/TenChat уже есть
#    (mu-плагин mu-plugins/social-fields.php примонтирован в compose).

# 4. Yoast: noindex автор-архивов (чтобы статьи автора не дублировались в поиске).
wp_root eval '$o=get_option("wpseo_titles"); if(!is_array($o))$o=[]; $o["noindex-author-wpseo"]=true; update_option("wpseo_titles",$o);' >/dev/null 2>&1 || true
ok "Yoast: noindex автор-архивов"

# 6. Чистим кэш.
rm -rf wordpress/wordpress/wp-content/cache/* 2>/dev/null || true
ok "настройки WP применены"
