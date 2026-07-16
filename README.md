# Content Factory — установщик инфраструктуры

Разворачивает «под ключ» инфраструктуру контент-блога: **WordPress** (тема, плагины, SEO,
все нужные настройки) + **n8n** (движок пайплайна, поднимается пустым) за единым **Traefik**
с бесплатным SSL (Let's Encrypt). Параметризован — один репозиторий, любой бренд/домен
задаётся в `.env`.

> Воркфлоу n8n и Google-таблица в установщик **не входят** — они импортируются/подключаются
> отдельно вручную после разворота (см. «Дальше вручную»).

## Архитектура

```
                 ┌── Traefik :80/:443 (SSL Let's Encrypt) ──┐
   BLOG_DOMAIN ──┤ → WordPress (Apache+PHP) + MariaDB        │
   N8N_DOMAIN  ──┤ → n8n + Postgres (пустой)                 │
                 └──────────────────────────────────────────┘
```

Два docker-compose стека (`wordpress/`, `n8n/`) в общей сети `${BRAND}-network`.

## Требования

- Чистый сервер **Ubuntu 22.04+**, root, ≥ 2 ГБ RAM, ~20 ГБ диска.
- Два поддомена A-записью на IP сервера: `BLOG_DOMAIN` и `N8N_DOMAIN`.

---

## Установка — одна команда

```bash
curl -sSL https://raw.githubusercontent.com/workzoneyou/content-factory-installer/main/setup.sh | sudo bash
```

Установщик **задаёт вопросы прямо в терминале** (бренд, домены, SSL-email, автор), сам
генерирует все пароли/ключи, скачивает компоненты и разворачивает систему. Редактировать
файлы не нужно. Вопросы читаются через `/dev/tty`, поэтому работают даже в `curl | bash`.
Все ответы сохраняются в `~/<бренд>-factory/.env`.

### Что делает setup.sh сам
1. Генерирует пароли БД, WP-админа, ключ шифрования n8n (в `.env`).
2. Ставит Docker (если нет), firewall (22/80/443).
3. Поднимает **WordPress-стек**, ставит WP-CLI, ставит WP, локаль `ru_RU`, permalinks `/%postname%/`.
4. Активирует тему `${BRAND}`, ставит плагины фикс-версий (Yoast, WP Fastest Cache, IndexNow, Метрика, WP GraphQL+SEO).
5. Применяет наши настройки: пропорция картинок **3/2**, права `www-data` на uploads/cache, профиль автора + соцполя, Yoast noindex автор-архивов.
6. Поднимает **n8n-стек** — пустой, готов к импорту воркфлоу.

### Дальше вручную (воркфлоу и Google — отдельно)
7. **Импортировать воркфлоу** в n8n (`${N8N_DOMAIN}` → Import from File).
8. **n8n Credentials** — завести: Google Sheets (OAuth2), OpenRouter, KIE (Header Auth `Authorization=Bearer <key>`), Perplexity, WordPress (Basic Auth: логин + пароль приложения). В нодах с красным значком выбрать эти креды.
9. **Google** — подключить таблицу (Sheets) и Drive (бэкап статей). ⚠️ Лист **ОПУБЛИКОВАНО оставить пустым** (иначе внутренние ссылки → 404).
10. **Контент** — залить темы/офферы → тест-прогон МАСТЕРА.
11. **Аватар** — граватар на почту WP-админа (тянется сам). **Бэкап сайта** — UpdraftPlus → Google Drive.

---

## Известные грабли (уже зашиты в установщик)

**Docker/SSL**
- Traefik v3 + Docker 29.x → `DOCKER_API_VERSION=1.44` (иначе «client 1.24 too old»).
- SSL продлевается сам за ~30 дней до истечения (Traefik ACME).

**WordPress**
- ⚠️ `wp-content/uploads` и `cache` — только `www-data`, **никогда `chown -R root`** (ломает загрузку картинок).
- Тема-мод `global_img_aspect_ratio=3/2` — иначе миниатюры «Читайте также» схлопываются (AVIF).
- WP-CLI теряется при рестарте контейнера — `install.sh` ставит его заново при каждом запуске.
- `kses` вырезает `<script>` без `unfiltered_html`.

**n8n**
- `N8N_RUNNERS_ENABLED=false` — иначе Code-ноды падают по таймауту 300с.
- `N8N_ENCRYPTION_KEY` **нельзя менять** после первого старта (иначе слетают креды).
- образ работает под юзером `node`; `n8n execute --id` конфликтует с живым инстансом.

---

## Обслуживание

```bash
cd wordpress && docker compose logs -f wp       # логи WP
cd n8n && docker compose logs -f n8n            # логи n8n
cd wordpress && docker compose restart          # рестарт
bash wordpress/scripts/backup.sh                # локальный бэкап (БД+uploads+.env)
```

## Структура репозитория

```
factory-installer/
├── setup.sh                       интерактивный установщик (curl | bash), WP + n8n
├── .env.example                   справочник параметров (сам заполняется вопросами)
├── wordpress/
│   ├── docker-compose.yml         Traefik + MariaDB + WP
│   ├── theme/                     тема (нейтральный бренд, задаётся в .env)
│   ├── mu-plugins/social-fields.php
│   ├── php/, plugins-zip/         PHP-лимиты, zip-и плагинов фикс-версий
│   └── scripts/                   install-plugins.sh, configure-plugins.php, wp-settings.sh, backup.sh
└── n8n/
    └── docker-compose.yml         n8n + Postgres (RUNNERS_ENABLED=false, TZ) — пустой
```

Секреты (`.env`, `db-data`, `n8n-data`, сертификаты) в `.gitignore`.
