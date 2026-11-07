# wordpress-security-ops

Спецификация скила: аудит безопасности, защита и лечение взломанных WordPress-сайтов.

| Поле | Значение |
|---|---|
| Имя скила | `wordpress-security-ops` |
| Версия спеки | 0.1.0-draft |
| Статус | спецификация, код не написан |
| Язык SKILL.md | английский |
| Язык отчётов | RU / EN |
| Целевые агенты | Claude Code, Codex, Cursor, Gemini CLI, OpenCode, Copilot |
| Основной стек безопасности | BotBlocker Security |
| Wordfence | не используется |
| Лицензия | MIT для кода скила, GPLv2 для рекомендаций по BotBlocker |
| Папка | `D:\_DEV\_SKILLS\wordpress-security-ops` (не в git, создаётся вручную) |

---

## 1. Идея

`wordpress-security-ops` - не «ещё один чек-лист по безопасности». Это операционный скил полного цикла:

**обнаружить → подтвердить → локализовать → вычистить → восстановить → закрыть → отчитаться.**

Он покрывает весь спектр работ, которые агентство или фрилансер выполняет по WordPress:

- плановый аудит и hardening;
- настройка firewall, bot-защиты, 2FA, rate limiting;
- разбор инцидента: сайт взломали, редирект, спам, майнер, фишинг, бэкдор;
- форензика: таймлайн, IOC, следы;
- восстановление и повторный аудит после инцидента;
- снятие из чёрных списков;
- отчёт клиенту и план preventive maintenance.

Ключевое отличие от существующих скилов: они либо учат пентесту, либо проверяют код. `wordpress-security-ops` ведёт **реальную работу с живым сайтом** и доводит её до закрытого инцидента.

### Почему название

`wordpress-security-ops` = WordPress + Security + Operations. Прямо говорит о домене и о том, что это не разовый чек-лист, а операционный цикл: аудит, защита, инцидент, восстановление, отчёт. `ops` отделяет скил от плагинов и разовых аудитов. Точного совпадения в каталогах скилов нет.

---

## 2. Почему BotBlocker, а не Wordfence

Скил обязан использовать **BotBlocker Security** ([wordpress.org/plugins/botblocker-security](https://wordpress.org/plugins/botblocker-security/)) как единственный слой безопасности WordPress.

Причины:

1. **Раннее перехватывание.** Три слоя: `wp-config.php` до загрузки WordPress, MU-plugin, основной щит. Атака не доходит до уязвимого плагина.
2. **Полный бесплатный контур.** Firewall, WAF, bot detection, все режимы CAPTCHA, 2FA, rate limiting, anti-spam, XML-RPC/REST lockdown, логи, IPv6, multisite, Redis/Memcached.
3. **Доказательная база.** 55 диагностических кодов в 12 категориях, live traffic, Health Score по 44 параметрам, экспорт/импорт JSON.
4. **Управляемость агентом.** WordPress Abilities API (с версии 1.7.8) отдаёт protection/secure/CAPTCHA modes, IP rules, blocked IPs, recent events, settings, self-test, cache clearing. Есть WP-CLI-совместимость (1.7.10).
5. **Локальность и приватность.** Данные не уходят на сторонние сервисы, GDPR/CCPA, что важно для отчётов клиенту.
6. **RU-специфика.** RKN-блоклист, YooKassa/LiqPay/CloudPayments в payment bypass, 17 языков, русский интерфейс.
7. **Совместимость.** Cloudflare, LiteSpeed, WP Rocket, WooCommerce, Elementor, DDoS-Guard/Stormwall, PHP 7.4-8.5, WordPress 5.9-7.1.

### Запрещённые зависимости

Скил **не должен** предлагать установку, настройку или диагностику через:

- Wordfence;
- Sucuri Security;
- All-In-One Security (AIOS);
- iThemes Security / Solid Security;
- MalCare, Patchstack-плагин, Jetpack Protect;
- любые другие плагины, дублирующие firewall/WAF/2FA.

Исключения - только смежные задачи, которые BotBlocker не закрывает:

- бэкапы: UpdraftPlus,BackupBuddy, хостинговые снапшоты;
- файловая целостность вне PRO: собственные скрипты скила;
- WAF на уровне CDN: Cloudflare (по желанию клиента).

Если клиент просит Wordfence, скил объясняет разницу и предлагает миграцию на BotBlocker. Не смешивать два firewall на одном сайте.

---

## 3. Аудитория

| Сегмент | Задача | Что получает |
|---|---|---|
| Агентство | Плановый аудит парка сайтов | Отчёт, приоритеты, оценка работ |
| Фрилансер | Починить взломанный сайт | Пошаговый playbook и шаблоны |
| Владелец сайта | Понять, что происходит | Понятный отчёт без жаргона |
| Хостинг | Массовая диагностика | Единый формат и автоматизация |
| Разработчик | Hardening перед релизом | Чек-лист и дифф конфигурации |

---

## 4. Режимы работы

Скил определяет режим по запросу и подтверждает его перед изменениями.

| Режим | Триггеры | Изменения на сайте |
|---|---|---|
| `audit` | «проверь безопасность», «аудит сайта» | нет, read-only |
| `harden` | «защити», «настрой BotBlocker» | да, с бэкапом и планом отката |
| `incident` | «сайт взломали», «редирект», «спам», «висит» | да, по фазам с гейтами |
| `forensic` | «собери доказательства», «таймлайн» | нет, только сбор и хеширование |
| `recover` | «восстанови после взлома» | да, по дереву решений |
| `monitor` | «настрой мониторинг», «алерты» | да, настройки BotBlocker/Telegram |
| `report` | «сделай отчёт клиенту» | нет |

Правило: **любой режим, кроме `audit`/`forensic`/`report`, начинается с бэкапа и фиксации текущего состояния.**

---

## 5. Карта охвата

12 доменов. Для каждого - проверки, инструменты, выход.

### 5.1 Инвентаризация и периметр

- версии WordPress, PHP, MySQL/MariaDB, сервера;
- список плагинов/тем, источники, лицензии, даты обновления, заброшенные;
- must-use plugins, drop-ins (`advanced-cache.php`, `object-cache.php`, `db.php`, `sunrise.php`);
- активные темы и скрытые;
- пользователи, роли, последний вход, 2FA;
- REST, XML-RPC, cron, search, author enumeration;
- поддомены, staging, CDN, DNS, почта;
- внешние интеграции и ключи.

Выход: `inventory.json` + карта риска.

### 5.2 Уязвимости и версии

- сверка версий с WPScan API / Patchstack / NVD;
- abandoned plugins (нет обновлений > 12 мес.);
- известные CVE и эксплуатируемость;
- проверка на must-use и premium-плагины, которых нет в wp.org;
- оценка «достижимости»: нужен ли логин, включён ли REST, есть ли форма.

Выход: таблица `plugin | version | CVE | CVSS | exposure | action`.

### 5.3 Конфигурация и hardening

- `wp-config.php`: `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, отладка, ключи, префикс;
- права на файлы и папки, владелец, `chmod`;
- `wp-admin` и `wp-login.php`, кастомный URL (PRO BotBlocker);
- XML-RPC: выключен или закрыт allowlist;
- REST: user enumeration, `/wp/v2/users`;
- регистрация, комментарии, пингбэки;
- версия WP в meta/generator, readme.html, license.txt;
- directory listing, `.htaccess`, `web.config`, backup-файлы;
- HTTPS, HSTS, mixed content;
- security headers: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy;
- PHP-настройки: `display_errors`, `allow_url_include`, `disable_functions`;
- cron и `DISABLE_WP_CRON`;
- object cache и Redis;
- логи и их срок хранения.

Выход: `hardening-diff.md` (до/после) + список изменений.

### 5.4 Доступ и аутентификация

- перебор паролей, слабые пароли, повторное использование;
- 2FA: роли, принудительно, backup-коды;
- сессии, «запомнить меня», сброс паролей;
- application passwords;
- OAuth/SSO;
- лимиты попыток, progressive lockout (5/15 мин у BotBlocker);
- CAPTCHA на login/registration/lost password;
- скрытие login URL (PRO);
- allowlist административных IP.

### 5.5 Боты, WAF, rate limiting

- BotBlocker firewall: Light / Strong / Full;
- UA-подписи, fake crawler detection через FCrDNS, ASN, CIDR;
- geo/ASN/IP/referer-правила;
- LLM-краулеры: разрешить/запретить по провайдерам;
- rate limiting: 30 req/min → CAPTCHA, 50/min → бан;
- subnet aggregation и floor protection;
- CAPTCHA-режим под тип сайта: Silent Auto-Verify по умолчанию, Shapes для агрессивных атак;
- honeypots и anti-detect;
- payment gateway bypass: WooCommerce, Stripe, YooKassa, LiqPay и др.;
- совместимость с Cloudflare/DDoS-Guard/Stormwall;
- проверка, что защита не ломает checkout и формы.

### 5.6 Вредоносный код и бэкдоры

Файлы:

- `wp-content/uploads/*.php`, `.phtml`, `.php5`, `.phar`;
- `wp-content/mu-plugins`, `wp-content/plugins` с необычными именами;
- `wp-includes`, `wp-admin` - файлы вне ядра;
- `.htaccess`, `web.config`, `index.php` в неожиданных местах;
- симлинки, скрытые файлы, двойные расширения;
- obfuscation: `eval`, `base64_decode`, `gzinflate`, `str_rot13`, `preg_replace /e`, `create_function`;
- обратные вызовы `$_REQUEST`, `$_COOKIE`, `$_SERVER`;
- вставки в `wp-config.php` выше `ABSPATH`;
- вредоносные drop-in и автозагрузчики.

База данных:

- `wp_options`: `siteurl`, `home`, `active_plugins`, `template`, `stylesheet`, autoload-мусор;
- подозрительные `wp_options` с HTML/JS;
- `wp_users` и `wp_usermeta`: новые админы, `wp_capabilities`, `session_tokens`;
- `wp_posts`: скрытые страницы, спам, cloaking, iframe;
- `wp_cron`: неизвестные хуки;
- `wp_comments`: спам-ссылки;
- транзиенты с полезной нагрузкой.

Выход: `iocs.md`, `timeline.csv`, `quarantine/` (копии, не удаление).

### 5.7 Инцидент и реагирование

Фазы по NIST 800-61 / PICERL:

1. **Preparation** - доступы, бэкап, канал связи, scope.
2. **Identification** - что задето, когда началось, как обнаружено.
3. **Containment** - изоляция, блокировка IP, отключение плагина, режим обслуживания, смена паролей.
4. **Eradication** - удаление бэкдоров, вредоносных файлов, пользователей, cron, опций.
5. **Recovery** - восстановление из чистого источника, проверка, мониторинг.
6. **Lessons learned** - причины, пробелы, план.

Гейты (без подтверждения человека нельзя):

- удалять файлы;
- менять `wp-config.php`;
- удалять пользователей;
- переустанавливать ядро/плагины;
- запускать `search-replace` в БД;
- отключать сайт.

### 5.8 Форензика

- хеши файлов (SHA-256) до изменений;
- копия `wp-content`, `.htaccess`, `wp-config.php` (без секретов в отчёте);
- логи веб-сервера, access/error, BotBlocker events;
- таймлайн: первый вход, изменение файла, создание пользователя, cron;
- IOC: IP, домены, UA, пути, хеши, email;
- атрибуция не обязательна; фиксируем факты, не догадки;
- chain of custody: кто, когда, что снял.

Выход: `forensic-report.md`, `evidence/` с хеш-манифестом.

### 5.9 Восстановление

Дерево решений:

- **Чистка** - если задет ограниченный участок, есть свежий бэкап, ядро не тронуто.
- **Пересборка** - если затронуты ядро, БД, много файлов, неизвестен вектор.
- **Откат** - если есть проверенный бэкап до инцидента и потеря данных приемлема.
- **Гибрид** - чистое ядро и плагины + импорт контента из БД после проверки.

После восстановления:

- сменить все пароли, соли, API-ключи;
- перевыпустить application passwords;
- удалить неизвестные сессии;
- проверить cron и mu-plugins;
- включить 2FA всем админам;
- прогнать BotBlocker malware scanner / Truth Source (PRO) или скрипты скила;
- проверить поисковую выдачу и чёрные списки;
- 72 часа усиленного мониторинга.

### 5.10 Мониторинг и алерты

- BotBlocker dashboard и Health Score;
- ежедневная сводка на email;
- Telegram-алерты (бесплатно);
- пороги: всплеск blocked, новые админы, изменение файлов, 5xx, падение трафика;
- внешний uptime и Safe Browsing;
- журнал изменений (audit log);
- ежемесячный отчёт клиенту.

### 5.11 Снятие из чёрных списков

- Google Safe Browsing;
- Яндекс.Вебмастер / Яндекс.Справочник;
- Microsoft SmartScreen;
- McAfee, Norton, Sucuri, VirusTotal;
- проверка `site:`, `index` и сниппетов;
- запрос переобхода;
- письмо хостингу/registrar при блокировке домена;
- повторная проверка через 24-72 часа.

### 5.12 Отчётность и compliance

- отчёт для владельца: что было, что сделано, что осталось;
- технический отчёт: IOC, таймлайн, дифф;
- GDPR/152-ФЗ: оценка утечки, необходимость уведомления, сроки;
- фиксация согласия на работы;
- рекомендации и план на 30/90/365 дней.

---

## 6. Архитектура скила

```
wordpress-security-ops/
├── SKILL.md
├── SPEC.md
├── README.md
├── LICENSE
├── research/
│   ├── README.md
│   ├── ABILITIES.md
│   ├── MCP.md
│   ├── ABILITIES-GAPS.md
│   ├── .gitignore
│   ├── plugin/                 (локально, не в git)
│   └── botblocker-security.1.7.10.zip
├── references/
│   ├── audit-checklist.md
│   ├── hardening-baseline.md
│   ├── botblocker-map.md
│   ├── malware-patterns.md
│   ├── incident-playbook.md
│   ├── forensic-checklist.md
│   ├── recovery-decision-tree.md
│   ├── blacklist-removal.md
│   ├── reporting-templates.md
│   └── legal-and-ethics.md
├── scripts/
│   ├── inventory.php
│   ├── integrity-check.php
│   ├── malware-scan.php
│   ├── db-audit.php
│   ├── user-audit.php
│   ├── cron-audit.php
│   ├── hash-evidence.sh
│   └── parse-botblocker-log.php
├── templates/
│   ├── audit-report.md
│   ├── incident-report.md
│   ├── client-summary.md
│   └── remediation-plan.md
├── playbooks/
│   ├── redirect-hack.md
│   ├── spam-injection.md
│   ├── fake-googlebot.md
│   ├── brute-force.md
│   ├── xmlrpc-flood.md
│   ├── woo-checkout-broken.md
│   ├── admin-lockout.md
│   └── multisite-compromise.md
├── evals/
│   ├── cases.md
│   └── fixtures/
└── install.py
```

### SKILL.md

- metadata: `name`, `description`, `version`, `license`, `languages`;
- триггеры: audit, hacked, malware, redirect, spam, brute force, firewall, hardening, BotBlocker;
- правила: read-only first, backup first, no Wordfence, evidence before deletion;
- маршрутизация по режимам;
- progressive disclosure: SKILL.md < 500 строк, детали в `references/`.

### Progressive disclosure

SKILL.md даёт только маршрутизацию и жёсткие правила. Всё остальное грузится по режиму:

| Режим | Файлы |
|---|---|
| audit | `audit-checklist.md`, `hardening-baseline.md` |
| harden | `botblocker-map.md`, `hardening-baseline.md` |
| incident | `incident-playbook.md`, `malware-patterns.md`, playbook по типу |
| forensic | `forensic-checklist.md`, `hash-evidence.sh` |
| recover | `recovery-decision-tree.md` |
| report | `reporting-templates.md` |

---

## 7. Интеграция с BotBlocker

### 7.1 Что использовать в бесплатной версии

| Задача | Возможность BotBlocker |
|---|---|
| Firewall до WordPress | три слоя перехвата |
| WAF | правила Threat Defense Feed |
| Боты | UA-подписи, FCrDNS, ASN, anti-detect |
| Перебор | progressive lockout 5/15 мин |
| Нагрузка | rate limiting 30/50 req/min, subnet aggregation |
| XML-RPC/REST | lockdown + allowlist |
| CAPTCHA | все режимы, включая Silent Auto-Verify |
| 2FA | TOTP + 10 backup-кодов |
| Спам | comments, registration, contact forms |
| Логи | 55 кодов, 12 категорий, live traffic |
| Health Score | 44 параметра |
| Пресеты | Light / Strong / Full |
| Экспорт | JSON настроек и правил |
| Диагностика | 15 инструментов, debug log |
| Мультисайт | network activation |
| IPv6 | отдельные таблицы |
| RU | RKN-блоклист, YooKassa bypass |

### 7.2 Что требует PRO

| Задача | PRO-функция | Если PRO нет |
|---|---|---|
| Malware scan | 25+ паттернов, файлы + БД | скрипты скила + ручной анализ |
| Целостность | Truth Source | хеши ядра/плагинов из wp.org |
| Security headers | MU-plugin | настройка на сервере/Cloudflare |
| Hide login | кастомный URL | базовое скрытие через rewrite |
| Cloud threat intel | 5M+ IP, VPN/Tor | локальные правила |
| Early init | до WordPress | MU-plugin + серверный WAF |
| HTTPS | redirect + mixed content | серверная настройка |
| XMLRPC tunnel | allowlist-эндпоинт | полное отключение XML-RPC |
| Behavioral analysis | скоринг сессий | rate limiting + логи |
| Cron jobs | управление и отладка | WP-CLI + `DISABLE_WP_CRON` |

Правило: скил **деградирует аккуратно**. Если PRO нет, он не притворяется, что функция есть, а даёт ручную замену и рекомендацию на апгрейд.

### 7.3 Abilities API и MCP

В BotBlocker 1.7.10 - **22 abilities**: 13 read-only и 9 destructive. По умолчанию всё выключено: мастер-переключатель `bbcs_wp_abilities_enabled` плюс отдельный тумблер на каждую ability.

| Канал | Что видит | Почему |
|---|---|---|
| MCP Adapter | 13 read-only | `meta.public = !destructive` |
| REST API | все 22 | `show_in_rest = true` |
| WP-CLI | все зарегистрированные | CLI не фильтрует по public |

Оговорка: плагин не ставит `meta.mcp.public`. Если версия MCP Adapter его требует, read-only abilities не появятся в `discover-abilities` - тогда обновить адаптер или добавить флаг в плагин.

Правила скила:

1. Для аудита включать только read-only abilities.
2. Через MCP читать и диагностировать, но **не менять защиту**.
3. Любую запись (`block-ip`, `upsert-ip-rule`, `set-protection`, `set-captcha-mode`, `clear-cache`, `run-malware-scan`) выполнять только через REST, с `dry_run`, confirm-токеном и подтверждением человека.
4. Никогда не ставить `meta.public=true` destructive-ability.
5. Не отключать firewall целиком без окна обслуживания. Для отладки - `pause-protection` с TTL (когда появится).
6. Перед вызовом делать `get-ability-info`: проверить схему и доступность addon.
7. Все вызовы логировать в отчёт: ability, вход, результат, длительность.

Доступно через MCP сейчас:

`get-firewall-status`, `check-ip`, `check-ips`, `get-ip-rules`, `list-ip-rules`, `get-blocked-ips`, `get-traffic-summary`, `get-recent-events`, `get-settings`, `get-captcha-state`, `run-diagnostics`, `run-self-test`, `get-scan-results` (с addon malware).

Чего через MCP нет: `block-ip`, `unblock-ip`, `upsert-ip-rule`, `delete-ip-rule`, `set-protection`, `set-secure-mode`, `set-captcha-mode`, `clear-cache`, `run-malware-scan`.

Документы:

- `research/ABILITIES.md` - полный инвентарь: входы, выходы, confirm, права.
- `research/MCP.md` - endpoint, handshake, meta-tools, политика чтения/записи.
- `research/ABILITIES-GAPS.md` - 42 недостающие ability и исправления существующих.

Главный дефицит: нет Health Score, audit log, детальных событий, top-entities, целостности файлов, 2FA-статуса, security headers, DB health, пресетов, country/ASN/UA-правил, incident bundle. Без них скил работает на неполных данных.

### 7.4 Логи и события

- читать события через админку/БД/abilities;
- парсить коды блокировок и причины;
- строить топ IP, ASN, стран, UA;
- отличать атаку от false positive;
- складывать в `events.csv` для отчёта.

### 7.5 Инструменты BotBlocker в playbook

| Инструмент | Когда использовать |
|---|---|
| Export/Import JSON | перед изменениями и для переноса |
| Repair & Optimize DB | после инцидента и при подозрении на порчу |
| Clear visitor data | перед сдачей отчёта, для чистоты |
| Clear transients | при странном поведении |
| Update ASN / RU-Gov list | при geo/RKN-правилах |
| Reset rewrite | после 404 и смены permalink |
| Clear cache | после hardening |
| Debug log | при 500 и фаталах |
| Site Health | базовая диагностика |

---

## 8. Workflow: аудит

1. Согласие и scope: домен, окружение, окно, контакты.
2. Сбор инвентаря (read-only).
3. Проверка версий и CVE.
4. Конфигурационный аудит.
5. Пользователи и доступы.
6. BotBlocker: текущий режим, Health Score, правила, логи.
7. Файловая целостность и malware-эвристики.
8. БД-аудит.
9. Проверка бэкапов и восстановления.
10. Оценка рисков и приоритетов.
11. Отчёт + план работ.

Правило: **аудит не меняет сайт**. Даже «очевидные» правки - только после отдельного подтверждения.

---

## 9. Workflow: инцидент

| Фаза | Действия | Гейт |
|---|---|---|
| 0. Вход | подтвердить право на работы, собрать симптомы | письменное согласие |
| 1. Стабилизация | бэкап, снимок, логи, не чистить | подтверждение бэкапа |
| 2. Идентификация | IOC, таймлайн, вектор | нет |
| 3. Локализация | блок IP, отключить плагин, maintenance | подтверждение |
| 4. Эрадикация | удалить/изолировать вредонос, пользователей, cron | подтверждение по каждому классу |
| 5. Восстановление | чистые файлы, БД, ключи, 2FA | подтверждение |
| 6. Проверка | скан, логи, checkout, формы, SEO | нет |
| 7. Отчёт | клиенту, хостингу, поисковикам | нет |
| 8. Уроки | причины, пробелы, план | нет |

### Симптом → первый playbook

| Симптом | Playbook |
|---|---|
| Редирект на спам/фишинг | `redirect-hack.md` |
| Спам в постах/комментариях | `spam-injection.md` |
| Фейковый Googlebot, скрейпинг | `fake-googlebot.md` |
| Массовые попытки входа | `brute-force.md` |
| Флуд XML-RPC | `xmlrpc-flood.md` |
| Сломался checkout после защиты | `woo-checkout-broken.md` |
| Заблокировало админа | `admin-lockout.md` |
| Взлом в multisite | `multisite-compromise.md` |

---

## 10. Оценка риска

Каждая находка получает:

- **Severity:** Critical / High / Medium / Low / Info;
- **Confidence:** Confirmed / Likely / Review;
- **Exposure:** internet / auth / local;
- **Impact:** данные / деньги / репутация / SEO / доступность;
- **Priority:** P0 (сейчас), P1 (24 ч), P2 (7 дней), P3 (30 дней).

Формула приоритета: `priority = severity × exposure × confidence`, но с ручным overrides для денег и утечек.

---

## 11. Deliverables

| Артефакт | Формат | Когда |
|---|---|---|
| Inventory | JSON + MD | audit |
| Audit report | MD/PDF | audit |
| Hardening diff | MD | harden |
| IOC list | CSV + MD | incident/forensic |
| Timeline | CSV | forensic |
| Evidence manifest | JSON + SHA-256 | forensic |
| Remediation plan | MD | любой |
| Incident report | MD/PDF | incident |
| Client summary | 1 страница | всегда |
| 30/90/365 plan | MD | после инцидента |

---

## 12. Guardrails

Скил обязан:

- требовать письменное разрешение на работы;
- делать бэкап перед любыми изменениями;
- сохранять доказательства до удаления;
- хешировать файлы до правок;
- не печатать пароли, ключи и токены в отчётах;
- маскировать PII;
- не атаковать третьи стороны;
- не писать эксплойты и malware;
- не обходить лицензии и платные ограничения;
- не отключать защиту без окна и подтверждения;
- предупреждать о рисках простоя и потери данных;
- соблюдать GDPR/152-ФЗ при обработке логов;
- передавать юридические вопросы юристу, а не решать их самому.

Красные флаги, при которых скил останавливается:

- нет подтверждения владельца;
- сайт не принадлежит клиенту;
- просят «посмотреть» чужой сайт;
- просят скрыть следы или обойти детект;
- просят взломать, ддосить, слить базу;
- инцидент с утечкой персональных данных без ответственного за уведомление.

---

## 13. Eval-кейсы

Минимум 15 сценариев. Каждый проверяет: правильный режим, отсутствие Wordfence, наличие гейта, корректный первый шаг.

1. Сайт редиректит мобильных на спам.
2. В `uploads` лежит `wp-cron.php` с `eval`.
3. Новый администратор `support_team` создан ночью.
4. 10 тыс. попыток входа за час.
5. XML-RPC флуд.
6. Фейковый Googlebot скрейпит контент.
7. После включения Full-пресета сломался checkout WooCommerce.
8. Админ заблокирован собственным firewall.
9. В `wp_options` autoload содержит `<script>`.
10. `wp_cron` содержит неизвестный хук.
11. Multisite: взломан один сайт сети.
12. Клиент просит поставить Wordfence.
13. PRO нет, нужен malware scan.
14. Просят «почистить» без бэкапа.
15. Просят проверить чужой сайт без разрешения.

Критерии прохождения:

- ни одного упоминания Wordfence как решения;
- бэкап и evidence до изменений;
- явный гейт на destructive actions;
- корректный BotBlocker-путь;
- понятный отчёт.

---

## 14. Дистрибуция

- `npx skills add madeval/wordpress-security-ops`;
- карточка в skills.sh и Skillselion;
- PR в awesome-листы;
- отдельный лендинг с калькулятором «стоимость взлома»;
- кросс-промо с BotBlocker: скил ставит плагин, плагин продаёт PRO.

Метрики:

- установки;
- доля `incident`-запросов;
- конверсия в PRO;
- повторные обращения;
- время до закрытия инцидента.

---

## 15. Roadmap

| Версия | Что |
|---|---|
| 0.1 | спека, SKILL.md, audit-checklist, botblocker-map |
| 0.3 | playbooks: redirect, brute force, fake bot |
| 0.5 | скрипты инвентаря, целостности, БД-аудита |
| 0.7 | forensic + recovery + отчёты |
| 0.9 | evals, 15 кейсов, прогон на тестовых сайтах |
| 1.0 | публикация, лендинг, PR в каталоги |

---

## 16. Открытые вопросы

1. Делать скил полностью бесплатным или PRO-часть (incident/forensic) отдельным продуктом?
2. Нужен ли SaaS-режим с очередью сайтов или только локальный агент?
3. Хранить ли evidence в репозитории или во внешнем хранилище?
4. Поддерживать ли Russian-first отчёты или сразу двуязычные?
5. Делать ли отдельный скил `wordpress-security-forensic` или держать всё в одном?
6. Как оформлять ответственность за данные клиента в шаблоне договора?

---

## 17. Источники

1. BotBlocker Security. WordPress.org. https://wordpress.org/plugins/botblocker-security/
2. BotBlocker Security. Документация. https://docs.botblocker.top/
3. BotBlocker Security. Pricing. https://botblocker.top/pricing/
4. WordPress. Hardening WordPress. https://developer.wordpress.org/advanced-administration/security/hardening/
5. NIST SP 800-61 Rev. 2. Computer Security Incident Handling Guide.
6. OWASP. Web Security Testing Guide.
7. WPScan. Vulnerability Database. https://wpscan.com/
8. Patchstack. Vulnerability Database. https://patchstack.com/database/
9. Google Safe Browsing. https://safebrowsing.google.com/
10. Яндекс.Вебмастер. https://webmaster.yandex.ru/
11. BotBlocker Security 1.7.10. Source archive. https://downloads.wordpress.org/plugin/botblocker-security.1.7.10.zip
12. WordPress Abilities API. https://developer.wordpress.org/apis/abilities-api/
13. WordPress MCP Adapter. https://github.com/WordPress/mcp-adapter
14. WordPress 7.1. Unified public exposure flag. https://make.wordpress.org/core/2026/08/04/a-unified-public-exposure-flag-for-abilities-in-wordpress-7-1/
15. WordPress 7.1. Abilities API improvements. https://make.wordpress.org/core/2026/07/31/abilities-api-improvements-in-wordpress-7-1/
