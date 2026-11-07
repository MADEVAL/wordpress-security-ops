# BotBlocker Abilities: полный инвентарь

Версия плагина: **1.7.10**
Файл: `includes/inc-botblocker-wp-abilities.php`
Категория: `botblocker`
Всего: **22 abilities** — 13 read-only, 9 destructive.

---

## 1. Как включить

По умолчанию **всё выключено**. Ability не регистрируется вообще, поэтому её не видно ни в REST, ни в MCP, ни в списке abilities.

Путь в админке: **BotBlocker → Integrations → WP Abilities API**.

| Уровень | Ключ настройки | Что делает |
|---|---|---|
| Мастер-переключатель | `bbcs_wp_abilities_enabled` | без него не регистрируется ничего |
| Каждая ability | `bbcs_ability_<name>` | точечное включение |

Ключи точечных переключателей:

```
bbcs_ability_get_firewall_status
bbcs_ability_check_ip
bbcs_ability_check_ips
bbcs_ability_get_ip_rules
bbcs_ability_list_ip_rules
bbcs_ability_get_blocked_ips
bbcs_ability_get_traffic_summary
bbcs_ability_get_recent_events
bbcs_ability_get_settings
bbcs_ability_get_captcha_state
bbcs_ability_run_diagnostics
bbcs_ability_run_self_test
bbcs_ability_get_scan_results
bbcs_ability_block_ip
bbcs_ability_unblock_ip
bbcs_ability_upsert_ip_rule
bbcs_ability_delete_ip_rule
bbcs_ability_set_protection
bbcs_ability_set_secure_mode
bbcs_ability_set_captcha_mode
bbcs_ability_clear_cache
bbcs_ability_run_malware_scan
```

Требования:

- WordPress 6.9+ (Abilities API).
- Для MCP - плагин [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/).
- Для `run-malware-scan` и `get-scan-results` - активный addon `bbcs-malware`.
- Право `manage_options` (переопределяется фильтром `bbcs_ability_required_capability`).

---

## 2. Модель экспозиции

Плагин регистрирует ability так:

```php
$bbcs_is_public = empty( $definition['annotations']['destructive'] );

wp_register_ability( $name, array(
    'label'               => ...,
    'description'         => ...,
    'category'            => 'botblocker',
    'execute_callback'    => ...,
    'permission_callback' => ...,
    'input_schema'        => ...,
    'output_schema'       => ...,
    'meta'                => array(
        'public'       => $bbcs_is_public,
        'show_in_rest' => true,
        'annotations'  => $definition['annotations'],
    ),
) );
```

Отсюда:

| Канал | Что видит | Почему |
|---|---|---|
| MCP Adapter (default server) | 13 read-only | `meta.public = true` |
| REST API | все 22 | `show_in_rest = true` явно |
| WP-CLI `wp ability list` | все зарегистрированные | CLI не применяет public-фильтр |
| Админка | все включённые | UI показывает обе группы |

Важно: **`public` - это видимость, а не авторизация.** Права всё равно проверяет `permission_callback` (`manage_options`).

В WordPress 7.1 REST-экспозиция резолвится так:

```php
$show_in_rest = $meta['show_in_rest'] ?? $meta['public'] ?? false;
```

Поэтому destructive-abilities с `public=false` и `show_in_rest=true` остаются REST-доступными. Это осознанное решение плагина, но его надо явно описать в политике безопасности.

---

## 3. Сводная таблица

| # | Ability | Группа | Режим | MCP | Confirm | Addon | Назначение |
|---|---|---|---|---|---|---|---|
| 1 | `botblocker/get-firewall-status` | Read-only | RO | да | - | - | режим, статус, версия |
| 2 | `botblocker/check-ip` | Read-only | RO | да | - | - | проверить IP по правилам |
| 3 | `botblocker/check-ips` | Read-only | RO | да | - | - | пакет до 20 IP |
| 4 | `botblocker/get-ip-rules` | Read-only | RO | да | - | - | правила для IP |
| 5 | `botblocker/list-ip-rules` | Read-only | RO | да | - | - | список правил с фильтрами |
| 6 | `botblocker/get-blocked-ips` | Read-only | RO | да | - | - | заблокированные IP |
| 7 | `botblocker/get-traffic-summary` | Read-only | RO | да | - | - | счётчики за период |
| 8 | `botblocker/get-recent-events` | Read-only | RO | да | - | - | агрегированные события |
| 9 | `botblocker/get-settings` | Read-only | RO | да | - | - | настройки по группам |
| 10 | `botblocker/get-captcha-state` | Read-only | RO | да | - | - | режим CAPTCHA |
| 11 | `botblocker/run-diagnostics` | Read-only | RO | да | - | - | health-check данных |
| 12 | `botblocker/run-self-test` | Read-only | RO | да | - | - | self-test firewall |
| 13 | `botblocker/get-scan-results` | Read-only | RO | да | - | malware | результаты скана |
| 14 | `botblocker/block-ip` | Manage | RW | нет | `BLOCK` | - | заблокировать IP |
| 15 | `botblocker/unblock-ip` | Manage | RW | нет | `UNBLOCK` | - | разблокировать IP |
| 16 | `botblocker/upsert-ip-rule` | Manage | RW | нет | нет | - | создать/изменить правило |
| 17 | `botblocker/delete-ip-rule` | Manage | RW | нет | `DELETE` | - | удалить правило по id |
| 18 | `botblocker/set-protection` | Protection | RW | нет | `ENABLE`/`DISABLE` | - | вкл/выкл защиту |
| 19 | `botblocker/set-secure-mode` | Protection | RW | нет | `FRONTEND`/`FULL` | - | режим frontend/full |
| 20 | `botblocker/set-captcha-mode` | Protection | RW | нет | нет | - | режим CAPTCHA |
| 21 | `botblocker/clear-cache` | Protection | RW | нет | нет | - | очистить файловый кеш |
| 22 | `botblocker/run-malware-scan` | Malware (PRO) | RW | нет | `SCAN` | malware | запустить скан |

---

## 4. Read-only abilities

### 4.1 `botblocker/get-firewall-status`

Вход: нет.
Выход:

```json
{
  "secure_mode": 2,
  "secure_mode_label": "Full",
  "enabled": true,
  "protection_enabled": true,
  "captcha_mode": 8,
  "captcha_mode_label": "Silent",
  "version": "1.7.10"
}
```

Использование: первый вызов в любом аудите. Проверка, что защита вообще включена и в каком режиме.

### 4.2 `botblocker/check-ip`

Вход: `ip` (string, обязательный).
Выход: `blocked`, `reason`, `source`, `matched_rules`, `protection_enabled`, `rules[]` (id, type, value, family, priority, expires, expired, readonly, comment).

Использование: проверить, не заблокирован ли админ, платёжный шлюз, мониторинг, поисковый бот.

### 4.3 `botblocker/check-ips`

Вход: `ips[]` (1..20 адресов).
Выход: `results[]` (ip, blocked, reason, source, matched_rules), `total`.

Использование: пакетная проверка списка IP из логов или от клиента.

### 4.4 `botblocker/get-ip-rules`

Вход: `ip`.
Выход: `rules[]` с полным набором полей.

Отличие от `check-ip`: не считает решение, а возвращает все совпавшие правила.

### 4.5 `botblocker/list-ip-rules`

Вход: `family` (ipv4/ipv6), `rule` (allow/block/gray/dark), `search`, `active_only`, `limit` 1..100 (20), `offset`.
Выход: `rules[]` (id, search, rule, family, priority, expires, readonly, comment), `total`.

Использование: инвентаризация правил, поиск временных банов, проверка, не перекрывает ли allow правило block.

### 4.6 `botblocker/get-blocked-ips`

Вход: `family`, `limit`, `offset`.
Выход: `rules[]` (id, search, family, expires, comment), `total`.

Только активные block-правила, без allow/gray/dark.

### 4.7 `botblocker/get-traffic-summary`

Вход: `period` = day/week/month.
Выход: `hits`, `blocks`, `captcha`, `suspicious`, `proxy`.

Использование: оценить масштаб атаки и эффект защиты до/после.

### 4.8 `botblocker/get-recent-events`

Вход: `period`, `type` (block/allow/captcha/proxy), `limit` 1..500 (100).
Выход: `events[]` = bucket, bucket_seconds, type, count.

Ключевое ограничение: **агрегаты, без IP и User-Agent**. Для инцидента этого мало.

### 4.9 `botblocker/get-settings`

Вход: `group` = protection/captcha/logging/integrations.
Выход: `group`, `values` (объект), `secrets` (объект, только true/false).

Секреты никогда не раскрываются. Значения - только несекретные настройки группы.

### 4.10 `botblocker/get-captcha-state`

Выход: `mode`, `mode_label`, `wait_seconds`, `image_pack`, `inline_images`, `available_modes[]` (mode, label, available, reason), `warnings[]`.

Использование: проверить, что выбранный режим реально доступен (GD, ключи reCAPTCHA), и не сломал ли он формы.

### 4.11 `botblocker/run-diagnostics`

Выход: `passed`, `items[]` (key, label, passed).

Проверяет: data directory writable, runtime settings file, database tables.

### 4.12 `botblocker/run-self-test`

Выход: `active`, `verdict`, `settings`, `bypasses[]`.

Показывает, не обходится ли firewall: причины bypass, активность.

### 4.13 `botblocker/get-scan-results`

Требует addon `bbcs-malware`.
Вход: `scan_id` (опционально).
Выход: `status`, `findings[]` (file, severity, classification).

Если addon не активен, ability не регистрируется.

---

## 5. Destructive abilities

Все требуют `manage_options`, логируются как `botblocker.ability.call`, имеют rate limit и поддерживают `dry_run` (кроме явно указанных).

### 5.1 `botblocker/block-ip`

Вход: `ip`, `reason`, `expires`, `confirm` = `"BLOCK"`, `force` (bool, default false), `dry_run`.
Выход: `added`, `id`, `changed`, `previous_rule`, `applied`, `protection_enabled`.

Защита: отказывается блокировать readonly-правила и самого себя без `force`.

### 5.2 `botblocker/unblock-ip`

Вход: `ip`, `confirm` = `"UNBLOCK"`, `dry_run`.
Выход: `removed`, `deleted`, `previous_rules[]`, `applied`, `protection_enabled`.

Удаляет только non-readonly правила.

### 5.3 `botblocker/upsert-ip-rule`

Вход: `ip` (IP или CIDR), `rule` (allow/block/gray/dark), `expires`, `expires_in` 60..31536000, `comment`, `priority` 0..100000, `force`, `dry_run`.
Выход: `id`, `created`, `previous_rule`, `changed`, `applied`, `family`, `expires`, `protection_enabled`.

**Нет confirm-токена.** Это риск: случайный вызов может изменить allow/block-правило. Нужно добавить.

### 5.4 `botblocker/delete-ip-rule`

Вход: `id`, `confirm` = `"DELETE"`, `dry_run`.
Выход: `deleted`, `id`, `rule`, `family`, `applied`, `protection_enabled`.

Readonly-правила удалить нельзя.

### 5.5 `botblocker/set-protection`

Вход: `enabled` (bool), `reason`, `confirm` = `"ENABLE"` или `"DISABLE"`, `dry_run`.
Выход: `enabled`, `previous`, `changed`, `applied`, `protection_enabled`.

Отключение защиты - самое опасное действие. Только с окном обслуживания и подтверждением.

### 5.6 `botblocker/set-secure-mode`

Вход: `mode` = frontend/full, `confirm` = `"FRONTEND"`/`"FULL"`, `dry_run`.
Выход: `mode`, `previous`, `changed`, `applied`, `warnings[]`, `protection_enabled`.

Full также блокирует админку - риск lockout.

### 5.7 `botblocker/set-captcha-mode`

Вход: `mode` (int или string), `wait_seconds` 0..300, `dry_run`.
Выход: `mode`, `mode_label`, `previous`, `changed`, `applied`, `warnings[]`, `protection_enabled`.

**Нет confirm-токена.** Недоступный режим отклоняется, а не подменяется молча.

### 5.8 `botblocker/clear-cache`

Вход: нет.
Выход: `cleared`.

**Нет confirm и dry_run.** Ломает кеш, но не критично. Всё равно лучше добавить `confirm = "CLEAR"`.

### 5.9 `botblocker/run-malware-scan`

Требует addon `bbcs-malware`.
Вход: `confirm` = `"SCAN"`.
Выход: `scan_id`, `status`.

Запускает фоновый скан. Нет `dry_run` и нет отдельного статуса прогресса.

---

## 6. Права, лимиты, аудит

| Механизм | Детали |
|---|---|
| Capability | `manage_options`, фильтр `bbcs_ability_required_capability` |
| Multisite | при network-active проверяется `BotBlockerMultisite::canManage()` |
| Видимость | `wp_get_abilities_item_include` скрывает ability, если прав нет |
| Rate limit | только destructive, ошибка `ability_rate_limited` |
| Audit | `BotBlockerAuditLogger::record( BOTBLOCKER_ABILITY_CALL, ... )` |
| Confirm | `bbcs_wp_ability_confirm( $input, $token )` |
| Self-block | `block-ip` и `upsert` защищают от блокировки своего IP без `force` |
| Readonly rules | системные правила нельзя менять и удалять |

Формат ошибок:

- `rest_forbidden` - нет прав;
- `ability_invalid_input` - плохой вход;
- `ability_readonly_rule` - попытка изменить системное правило;
- `ability_rate_limited` - слишком много destructive-вызовов.

---

## 7. Как вызывать

### REST: список

```http
GET /wp-json/wp-abilities/v1/abilities
Authorization: Basic <base64(login:application_password)>
```

### REST: запуск

```http
POST /wp-json/wp-abilities/v1/abilities/botblocker/get-firewall-status/run
Authorization: Basic <base64(login:application_password)>
Content-Type: application/json

{"input":{}}
```

С аргументами:

```http
POST /wp-json/wp-abilities/v1/abilities/botblocker/list-ip-rules/run

{"input":{"family":"ipv4","rule":"block","limit":50}}
```

Destructive:

```http
POST /wp-json/wp-abilities/v1/abilities/botblocker/block-ip/run

{"input":{"ip":"203.0.113.10","reason":"bruteforce","confirm":"BLOCK"}}
```

### MCP: мета-инструменты

```json
{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{
  "name":"mcp-adapter-execute-ability",
  "arguments":{
    "ability_name":"botblocker/get-firewall-status",
    "parameters":{}
  }
}}
```

### WP-CLI

```bash
wp ability list
wp ability run botblocker/get-firewall-status
wp ability run botblocker/check-ip --input='{"ip":"203.0.113.10"}'
```

Точный синтаксис `wp ability run` зависит от версии WP-CLI; если команды нет, использовать REST.

---

## 8. Ограничения текущего набора

1. Нет Health Score, хотя расчёт есть в `BotBlockerHealthService::calculateHealthScore()`.
2. Нет audit log, хотя таблица и репозиторий `BotBlockerAuditRepository::query()` уже есть.
3. Нет детальных событий с IP/UA/путём - только агрегаты.
4. Нет top-IP/ASN/страна/UA.
5. Нет статуса целостности (Truth Source) и modified files.
6. Нет 2FA-статуса и списка админов без 2FA.
7. Нет security headers, HTTPS, mixed content.
8. Нет DB health: размеры таблиц, autoload, transients, cron.
9. Нет экспорта/импорта настроек.
10. Нет пресетов Light/Strong/Full.
11. Нет country/ASN/UA/path-правил - только IP.
12. Нет DB maintenance и очистки visitor data.
13. Нет статуса прогресса malware scan.
14. Нет incident bundle и timeline.
15. Нет explain-decision: почему запрос заблокирован.

Полный список предложений - в `ABILITIES-GAPS.md`.
