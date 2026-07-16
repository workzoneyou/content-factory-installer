# Content Factory — установщик контент-завода

Разворачивает «под ключ» систему авто-генерации SEO-статей: **WordPress** (блог с темой,
плагинами, SEO) + **n8n** (пайплайн генерации: план → текст → картинки → публикация) за
единым **Traefik** с бесплатным SSL (Let's Encrypt). Параметризован — один репозиторий,
любой бренд/домен/клиент задаётся в `.env`.

## Архитектура

```
                 ┌── Traefik :80/:443 (SSL Let's Encrypt) ──┐
   BLOG_DOMAIN ──┤ → WordPress (Apache+PHP) + MariaDB        │
   N8N_DOMAIN  ──┤ → n8n + Postgres                          │
                 └──────────────────────────────────────────┘
   n8n ── OpenRouter / KIE / Perplexity / Google Sheets+Drive ── WP REST
```

Два docker-compose стека (`wordpress/`, `n8n/`) в общей сети `${BRAND}-network`.

## Требования

- Чистый сервер **Ubuntu 22.04+**, root-доступ, ≥ 2 ГБ RAM, ~20 ГБ диска.
- Два поддомена A-записью на IP сервера: `BLOG_DOMAIN` и `N8N_DOMAIN`.
- Аккаунты/ключи клиента: OpenRouter, KIE AI, Perplexity, Google (Sheets+Drive), Яндекс.Метрика (опц.).

---

## Установка

```bash
git clone <this-repo> factory && cd factory
cp .env.example .env
nano .env            # заполнить BRAND, домены, SSL_EMAIL, автора (пароли/ключи — пусто, сгенерятся)
sudo ./install.sh
```

`install.sh` идемпотентен — можно запускать повторно.

### Что делает install.sh сам
1. Генерирует пароли БД, WP-админа, ключ шифрования n8n (в `.env`).
2. Ставит Docker (если нет), настраивает firewall (22/80/443).
3. Поднимает **WordPress-стек** (Traefik+MariaDB+WP), ставит WP-CLI, ставит WP, локаль `ru_RU`, permalinks `/%postname%/`.
4. Активирует тему `${BRAND}`, ставит плагины фикс-версий (Yoast, WP Fastest Cache, IndexNow, Метрика, WP GraphQL+SEO).
5. **Применяет наши настройки** (`wp-settings.sh`): пропорция картинок **3/2**, права `www-data` на uploads/cache, профиль автора + соцполя, Yoast noindex автор-архивов.
6. Поднимает **n8n-стек**, при наличии `GOOGLE_SHEET_ID` — импортирует 6 воркфлоу с подстановкой домена/Sheet ID.

### Ручные шаги (после install.sh)
6. **Google-таблица** — скопировать шаблон (`google/SHEETS_TEMPLATE.md`), **очистить лист ОПУБЛИКОВАНО**, вписать `GOOGLE_SHEET_ID` в `.env`, затем `bash n8n/setup.sh`.
7. **n8n Credentials** — завести: Google Sheets (OAuth2), OpenRouter, KIE (Header Auth `Authorization=Bearer <key>`), Perplexity, WordPress (Basic Auth: логин + пароль приложения WP). Открыть 6 воркфлоу, в нодах с красным значком выбрать эти креды.
8. **Контент** — залить темы в КОНВЕЙЕР, проверить ПРОМПТЫ/ОФФЕРЫ → в n8n «Execute workflow» на МАСТЕРЕ. Активировать расписание, когда всё ок.
9. **Аватар автора** — завести граватар на почту WP-админа (тянется автоматически).
10. **Бэкап сайта** — UpdraftPlus → Google Drive (плагин ставится, OAuth вручную).

---

## Известные грабли (уже зашиты в установщик)

Это то, что ломалось при ручном разворачивании — здесь уже учтено:

**Docker/SSL**
- Traefik v3 + Docker 29.x → `DOCKER_API_VERSION=1.44` (иначе «client 1.24 too old»).
- SSL продлевается сам за ~30 дней до истечения (Traefik ACME), ничего делать не нужно.

**WordPress**
- ⚠️ `wp-content/uploads` и `cache` — только `www-data`, **никогда `chown -R root`** (ломает загрузку картинок).
- Тема-мод `global_img_aspect_ratio=3/2` — иначе миниатюры «Читайте также» схлопываются (AVIF).
- WP-CLI теряется при рестарте контейнера — `install.sh` ставит его заново при каждом запуске.
- `kses` вырезает `<script>` без `unfiltered_html`.

**n8n**
- `N8N_RUNNERS_ENABLED=false` — иначе Code-ноды падают по таймауту 300с.
- `N8N_ENCRYPTION_KEY` **нельзя менять** после первого старта (иначе слетают креды).
- образ работает под юзером `node`; `n8n execute --id` конфликтует с живым инстансом (порт task-broker).

**Пайплайн**
- Лист **ОПУБЛИКОВАНО должен быть пустым** на старте (иначе внутренние ссылки 404).
- Воркфлоу уже содержат фиксы: 2 картинки + разнесение, уникальные картинки/alt, SOFT_OFFER в середине, мета без обрыва, бюджет ресёрчера, фолбэк тегов, санитайзер битых ссылок, чистый бэкап.

---

## Обслуживание

```bash
# логи
cd wordpress && docker compose logs -f wp
cd n8n && docker compose logs -f n8n
# рестарт
cd wordpress && docker compose restart
# локальный бэкап (БД+uploads+.env)
bash wordpress/scripts/backup.sh
# обновить Sheet ID в воркфлоу — правьте в UI n8n (или пересоздайте n8n-db и re-import)
```

## Структура репозитория

```
factory-installer/
├── install.sh                     мастер-установщик (WP + n8n)
├── .env.example                   все параметры
├── wordpress/
│   ├── docker-compose.yml         Traefik + MariaDB + WP
│   ├── theme/                     тема (нейтральный бренд, задаётся в .env)
│   ├── mu-plugins/social-fields.php
│   ├── php/, plugins-zip/         PHP-лимиты, zip-и плагинов фикс-версий
│   └── scripts/                   install-plugins.sh, configure-plugins.php, wp-settings.sh, backup.sh
├── n8n/
│   ├── docker-compose.yml         n8n + Postgres (RUNNERS_ENABLED=false, TZ)
│   ├── workflows/all.json         6 воркфлоу (параметризованы: домен, Sheet ID)
│   └── setup.sh                   импорт воркфлоу
└── google/SHEETS_TEMPLATE.md      структура таблиц + OAuth
```

Секреты (`.env`, `db-data`, `n8n-data`, сертификаты) в `.gitignore` — в репозиторий не попадают.
