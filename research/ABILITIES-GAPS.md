# Каких abilities не хватает BotBlocker Security

Предложения по расширению Abilities API плагина под полный цикл `wordpress-security-ops`.

Версия-база: **1.7.10**.
Текущий набор: 22 abilities (13 read-only, 9 destructive).

---

## 1. Принципы

1. **Read-only по умолчанию публичны.** Их можно отдавать в MCP.
2. **Любая запись - только REST, только с confirm, только с dry_run.**
3. **PII - отдельный уровень.** Детальные логи с IP/UA не публиковать в MCP по умолчанию.
4. **Никаких ability, которые удаляют файлы или выполняют код.** Карантин - максимум.
5. **Сначала экспозиция существующего backend, потом новый код.** Большая часть данных уже есть в плагине.
6. **Имена - в стиле `botblocker/verb-noun`,** как у существующих.

---

## 2. P0 - блокируют аудит и инцидент

Без этих 12 abilities скил не может закрыть базовый аудит и разбор инцидента.

| # | Ability | Режим | MCP | Backend уже есть |
|---|---|---|---|---|
| 1 | `botblocker/get-health-score` | RO | да | да, `BotBlockerHealthService` |
| 2 | `botblocker/get-audit-log` | RO | да | да, `BotBlockerAuditRepository::query()` |
| 3 | `botblocker/get-event-details` | RO | нет | да, таблица hits/events |
| 4 | `botblocker/get-top-entities` | RO | да | да, агрегаты |
| 5 | `botblocker/get-protection-snapshot` | RO | да | да, настройки + пресеты |
| 6 | `botblocker/get-addons-status` | RO | да | да, addon manager |
| 7 | `botblocker/get-license-status` | RO | да | да, Freemius |
| 8 | `botblocker/get-2fa-status` | RO | да | да, 2FA |
| 9 | `botblocker/get-security-headers` | RO | да | да, addon |
| 10 | `botblocker/get-db-health` | RO | да | да, tools |
| 11 | `botblocker/get-scan-status` | RO | да | частично |
| 12 | `botblocker/get-integrity-status` | RO | да | да, Truth Source |

### 2.1 `botblocker/get-health-score`

Зачем: аудит без оценки posture - это список фактов без приоритетов.

```json
input:  { "include": ["items", "recommendations"] }
output: {
  "score": 72,
  "level": "strong",
  "categories": [
    {"key":"core","label":"Core protection","score":60,"max":75},
    {"key":"cloud","label":"Cloud extended","score":12,"max":25}
  ],
  "items": [
    {"key":"block_empty_ua","label":"Block empty UA","passed":true,"weight":7.5,"category":"core"}
  ],
  "recommendations": [
    {"key":"recaptcha_check","impact":7.5,"action":"Enable reCAPTCHA check"}
  ]
}
```

Правила: `readonly: true`, `public: true`, без секретов.

### 2.2 `botblocker/get-audit-log`

Зачем: кто и когда менял защиту, кто вызывал abilities, что было до инцидента.

```json
input: {
  "period": "day|week|month|custom",
  "since": 1730000000,
  "until": 1730086400,
  "event": "botblocker.ability.call",
  "severity": "info|medium|critical",
  "actor": "login|id",
  "object_type": "ability|setting|user|post",
  "limit": 100,
  "offset": 0
}
output: {
  "events": [
    {"id":1,"created_at":1730000000,"event":"botblocker.ability.call",
     "severity":"medium","actor":"admin","ip":"203.0.113.5",
     "message":"BotBlocker ability block-ip: success.",
     "data":{"ability":"botblocker/block-ip","outcome":"success","duration_ms":42}}
  ],
  "total": 128
}
```

Правила: `readonly: true`; PII маскировать по тем же правилам, что `BotBlockerAuditLogger::redactData()`; по умолчанию `public: true`, но с флагом `include_ip=false`.

### 2.3 `botblocker/get-event-details`

Зачем: `get-recent-events` отдаёт только агрегаты. Для инцидента нужны IP, ASN, UA, путь, причина, действие.

```json
input: {
  "period": "day|week|month",
  "action": "block|allow|captcha|proxy",
  "ip": "203.0.113.10",
  "reason": "string",
  "limit": 100,
  "cursor": "string"
}
output: {
  "events": [
    {"id":1,"ts":1730000000,"ip":"203.0.113.10","country":"RU","asn":12345,
     "ua":"curl/8.0","method":"POST","path":"/wp-login.php",
     "action":"block","reason":"bruteforce","rule_id":42,
     "status":403,"host":"example.com"}
  ],
  "next_cursor": "..."
}
```

Правила: `readonly: true`, **`public: false`** (PII), REST only или отдельный operator-MCP. Маскировать IP в отчётах.

### 2.4 `botblocker/get-top-entities`

Зачем: быстро понять источник атаки, не выгружая миллион строк.

```json
input: {
  "period": "day|week|month",
  "dimension": "ip|subnet|asn|country|ua|path|reason|status",
  "action": "block|allow|captcha|proxy",
  "limit": 20
}
output: {
  "dimension": "country",
  "items": [{"value":"RU","count":1200,"blocked":900,"share":0.42}]
}
```

Правила: `readonly: true`, `public: true` для агрегатов; для `dimension=ip` - `public: false`.

### 2.5 `botblocker/get-protection-snapshot`

Зачем: один вызов вместо четырёх `get-settings`, плюс пресет, версии, addons, license.

```json
input:  { "include_secrets": false }
output: {
  "version": "1.7.10",
  "preset": "strong",
  "protection": {...},
  "captcha": {...},
  "logging": {...},
  "integrations": {...},
  "abilities_enabled": true,
  "addons": [{"slug":"bbcs-malware","active":true,"version":"1.2.0"}],
  "license": {"plan":"premium","expires":1735000000},
  "secrets": {"recaptcha_site_key":true,"recaptcha_secret_key":true}
}
```

Правила: `readonly: true`, `public: true`; секреты только как boolean.

### 2.6 `botblocker/get-addons-status`

```json
output: {
  "addons": [
    {"slug":"bbcs-malware","label":"Malware Scanner","installed":true,"active":true,
     "version":"1.2.0","requires_pro":true,"compatible":true}
  ]
}
```

Зачем: понять, доступны ли `run-malware-scan`, `get-scan-results`, headers, hide-login.

### 2.7 `botblocker/get-license-status`

```json
output: {
  "plan": "free|premium|pro|ultimate",
  "active": true,
  "expires": 1735000000,
  "cloud_checks_left": 24310,
  "features": {"malware":true,"headers":false,"early_init":true}
}
```

Без ключей и токенов.

### 2.8 `botblocker/get-2fa-status`

```json
input: { "role": "administrator" }
output: {
  "enforced": false,
  "users": [
    {"id":1,"login":"admin","roles":["administrator"],"enabled":true,
     "backup_codes_left":8,"last_verified":1730000000}
  ],
  "summary": {"total":5,"enabled":2,"missing":3}
}
```

Зачем: аудит доступа. Без секретов и кодов.

### 2.9 `botblocker/get-security-headers`

```json
output: {
  "https": true,
  "hsts": {"enabled":true,"max_age":31536000,"include_subdomains":true},
  "csp": {"enabled":false,"directives":{}},
  "x_frame_options": "SAMEORIGIN",
  "x_content_type_options": "nosniff",
  "referrer_policy": "strict-origin-when-cross-origin",
  "permissions_policy": "...",
  "mixed_content": [],
  "source": "addon|server|none"
}
```

### 2.10 `botblocker/get-db-health`

```json
output: {
  "tables": [{"name":"wp_options","rows":1200,"size_mb":2.4,"overhead_mb":0.1}],
  "autoload_mb": 0.8,
  "transients": 320,
  "expired_transients": 40,
  "cron_events": 18,
  "orphan_options": 12,
  "recommendations": ["Clean expired transients"]
}
```

### 2.11 `botblocker/get-scan-status`

Отличие от `get-scan-results`: статус, прогресс, ETA, текущий файл.

```json
input:  { "scan_id": "..." }
output: {
  "scan_id": "...",
  "status": "queued|running|done|failed",
  "progress": 0.42,
  "files_scanned": 4200,
  "files_total": 10000,
  "current": "wp-content/plugins/foo/bar.php",
  "started_at": 1730000000,
  "eta": 1730000600
}
```

### 2.12 `botblocker/get-integrity-status`

Truth Source: сравнение с эталоном ядра/плагинов/тем.

```json
input:  { "scope": "core|plugins|themes|all", "limit": 100, "offset": 0 }
output: {
  "last_scan": 1730000000,
  "summary": {"modified":2,"added":5,"missing":1},
  "files": [
    {"path":"wp-content/plugins/foo/foo.php","status":"modified",
     "expected_hash":"...","actual_hash":"...","severity":"high"}
  ]
}
```

---

## 3. P1 - нужны для hardening и восстановления

| # | Ability | Режим | MCP | Confirm | Dry run |
|---|---|---|---|---|---|
| 13 | `botblocker/apply-preset` | RW | нет | `PRESET` | да |
| 14 | `botblocker/set-country-rule` | RW | нет | `COUNTRY` | да |
| 15 | `botblocker/set-asn-rule` | RW | нет | `ASN` | да |
| 16 | `botblocker/set-ua-rule` | RW | нет | `UA` | да |
| 17 | `botblocker/set-path-rule` | RW | нет | `PATH` | да |
| 18 | `botblocker/set-2fa-policy` | RW | нет | `2FA` | да |
| 19 | `botblocker/update-threat-data` | RW | нет | `UPDATE` | да |
| 20 | `botblocker/maintain-database` | RW | нет | `MAINTAIN` | да |
| 21 | `botblocker/clear-visitor-data` | RW | нет | `PURGE` | да |
| 22 | `botblocker/export-settings` | RO | да | - | - |
| 23 | `botblocker/import-settings` | RW | нет | `IMPORT` | да |
| 24 | `botblocker/pause-protection` | RW | нет | `PAUSE` | да |
| 25 | `botblocker/reset-rewrite` | RW | нет | `RESET` | нет |
| 26 | `botblocker/send-test-alert` | RW | нет | `SEND` | нет |
| 27 | `botblocker/run-integrity-scan` | RW | нет | `SCAN` | нет |

### 13. `apply-preset`

```json
input:  {"preset":"light|strong|full","dry_run":true,"confirm":"PRESET"}
output: {"previous":"custom","preset":"strong","changed":18,"would_change":18,"applied":false}
```

Зачем: hardening одной командой, а не 100 тумблеров.

### 14-17. Правила по стране, ASN, UA, пути

```json
input: {
  "value": "RU|AS12345|curl|/wp-login.php",
  "rule": "allow|block|gray|dark",
  "expires_in": 86400,
  "comment": "incident 2026-11-07",
  "dry_run": true
}
output: {"id":1,"created":true,"changed":true,"applied":false}
```

Общий формат, разные namespace. Для страны - ISO-2, для ASN - целое, для UA - строка/regex с лимитом длины, для пути - glob/regex с защитой от ReDoS.

### 18. `set-2fa-policy`

```json
input:  {"roles":["administrator","editor"],"enforce":true,"grace_days":3,"dry_run":true}
output: {"previous":{"roles":["administrator"],"enforce":false},"changed":true,"users_affected":4}
```

### 19. `update-threat-data`

```json
input:  {"targets":["asn","rkn","llm"],"dry_run":true}
output: {"updated":["asn"],"failed":[],"versions":{"asn":"2026-11-07"}}
```

### 20. `maintain-database`

```json
input:  {"actions":["repair","optimize","clear_transients","remove_orphans"],"dry_run":true}
output: {"actions":[{"key":"optimize","ok":true,"freed_mb":1.2}]}
```

### 21. `clear-visitor-data`

```json
input:  {"scope":"hits|stats|all","before":1730000000,"confirm":"PURGE"}
output: {"deleted":120000,"freed_mb":42.5}
```

### 22. `export-settings`

```json
input:  {"include_rules":true,"redact_secrets":true}
output: {"format":"json","version":"1.7.10","settings":{...},"rules":[...],"checksum":"sha256:..."}
```

Read-only, можно в MCP.

### 23. `import-settings`

```json
input:  {"payload":{...},"mode":"merge|replace","dry_run":true,"confirm":"IMPORT"}
output: {"valid":true,"changes":[...],"applied":false,"backup_id":"..."}
```

Обязательно создавать бэкап перед применением.

### 24. `pause-protection`

```json
input:  {"minutes":15,"reason":"debug checkout","confirm":"PAUSE"}
output: {"paused":true,"until":1730000900,"auto_resume":true}
```

TTL-пауза вместо полного отключения. `set-protection(false)` остаётся, но это отдельное опасное действие.

### 25. `reset-rewrite`

```json
input:  {"confirm":"RESET"}
output: {"flushed":true,"rules_count":42}
```

### 26. `send-test-alert`

```json
input:  {"channel":"email|telegram","confirm":"SEND"}
output: {"sent":true,"channel":"telegram","latency_ms":320}
```

### 27. `run-integrity-scan`

```json
input:  {"scope":"core|plugins|themes|all","confirm":"SCAN"}
output: {"scan_id":"...","status":"queued"}
```

---

## 4. P2 - форензика и отчётность

| # | Ability | Режим | MCP | Confirm | Комментарий |
|---|---|---|---|---|---|
| 28 | `botblocker/get-incident-bundle` | RO | нет | - | PII, только REST |
| 29 | `botblocker/get-timeline` | RO | да | - | нормализованный таймлайн |
| 30 | `botblocker/list-modified-files` | RO | да | - | из Truth Source |
| 31 | `botblocker/get-cron-jobs` | RO | да | - | cron-хуки |
| 32 | `botblocker/get-users-risk` | RO | да | - | админы без 2FA |
| 33 | `botblocker/get-abilities-audit` | RO | нет | - | вызовы abilities |
| 34 | `botblocker/verify-file-hash` | RO | да | - | один файл |
| 35 | `botblocker/quarantine-file` | RW | **нет** | `QUARANTINE` | никогда не публиковать |
| 36 | `botblocker/get-login-attempts` | RO | нет | - | PII |
| 37 | `botblocker/get-visitor-log` | RO | нет | - | PII |

### 28. `get-incident-bundle`

Один вызов для отчёта и передачи в форензику.

```json
input:  {"since":1730000000,"until":1730086400,"include":["settings","events","top","audit","scan","integrity","headers","versions"]}
output: {
  "bundle_id":"...",
  "created_at":1730086500,
  "files":{"settings.json":"...","events.csv":"...","audit.json":"...","manifest.sha256":"..."},
  "summary":{"blocks":12000,"top_country":"RU","modified_files":2}
}
```

`public: false`, PII, только для оператора.

### 29. `get-timeline`

```json
output: {"events":[
  {"ts":1730000000,"type":"login_failed","actor":"admin","ip":"...","detail":"..."},
  {"ts":1730000300,"type":"file_modified","path":"...","detail":"..."},
  {"ts":1730000600,"type":"ability_call","detail":"block-ip"}
]}
```

### 30. `list-modified-files`

```json
input:  {"since":1730000000,"status":"modified|added|missing","limit":100}
output: {"files":[{"path":"...","status":"modified","detected_at":1730000000}]}
```

### 31. `get-cron-jobs`

```json
output: {"jobs":[{"hook":"bbcs_cleanup","next_run":1730000000,"schedule":"hourly","args":[]}]}
```

### 32. `get-users-risk`

```json
output: {"users":[
  {"id":1,"login":"admin","role":"administrator","2fa":false,"last_login":1730000000,
   "stale_days":120,"risk":"high"}
]}
```

### 33. `get-abilities-audit`

```json
input:  {"period":"week","outcome":"success|error|denied","limit":100}
output: {"calls":[{"ability":"botblocker/block-ip","outcome":"success","duration_ms":42,"at":1730000000}]}
```

### 34. `verify-file-hash`

```json
input:  {"path":"wp-content/plugins/foo/foo.php","expected_hash":"sha256:..."}
output: {"path":"...","actual_hash":"...","match":false}
```

### 35. `quarantine-file`

```json
input:  {"path":"wp-content/uploads/2026/11/x.php","reason":"webshell","confirm":"QUARANTINE"}
output: {"quarantined":true,"id":"...","sha256":"...","restore_token":"..."}
```

Ограничения:

- только внутри `WP_CONTENT_DIR`;
- allowlist расширений;
- не трогать ядро;
- не выполнять и не исполнять файл;
- MCP-public **всегда false**.

### 36. `get-login-attempts`

```json
input:  {"period":"day","result":"fail|success","ip":"...","limit":100}
output: {"attempts":[{"ts":1730000000,"login":"admin","ip":"...","result":"fail","reason":"bad_password"}]}
```

### 37. `get-visitor-log`

```json
input:  {"period":"day","action":"allow","limit":100,"cursor":"..."}
output: {"visitors":[{"ts":1730000000,"ip":"...","asn":1,"country":"DE","ua":"...","path":"/","status":200}]}
```

---

## 5. P3 - качество и интеграция

| # | Ability | Режим | MCP | Зачем |
|---|---|---|---|---|
| 38 | `botblocker/get-capabilities` | RO | да | матрица free/PRO/addon для агента |
| 39 | `botblocker/explain-decision` | RO | да | почему запрос заблокирован |
| 40 | `botblocker/validate-config` | RO | да | линтер настроек |
| 41 | `botblocker/test-payment-bypass` | RO | да | проверить whitelist шлюза |
| 42 | `botblocker/get-docs` | RO | да | ссылки на документацию |

### 38. `get-capabilities`

```json
output: {
  "version":"1.7.10",
  "plan":"premium",
  "features":{"firewall":true,"captcha":true,"2fa":true,"malware":true,"headers":false},
  "abilities":{"botblocker/block-ip":true,"botblocker/get-health-score":true}
}
```

### 39. `explain-decision`

```json
input:  {"ip":"203.0.113.10","ua":"curl/8.0","path":"/wp-login.php","method":"POST"}
output: {"decision":"block","rule_id":42,"rule":"ua:curl","layer":"early_init","reason_code":"UA_BLOCK"}
```

Это killer-feature для разбора false positive: не нужно гадать, какой слой сработал.

### 40. `validate-config`

```json
output: {"score":72,"issues":[
  {"key":"2fa","severity":"high","message":"2FA not enforced for administrators"},
  {"key":"xmlrpc","severity":"medium","message":"XML-RPC open"}
]}
```

### 41. `test-payment-bypass`

```json
input:  {"gateway":"yookassa","path":"/?wc-api=wc_yookassa"}
output: {"bypassed":true,"detected_by":"path","would_block":false}
```

### 42. `get-docs`

```json
input:  {"topic":"abilities|malware|headers|cache"}
output: {"url":"https://docs.botblocker.top/..."}
```

---

## 6. Исправления существующих abilities

| Проблема | Что сделать |
|---|---|
| `upsert-ip-rule` без confirm | добавить `confirm: "UPSERT"` |
| `set-captcha-mode` без confirm | добавить `confirm: "CAPTCHA"` |
| `clear-cache` без confirm/dry_run | добавить `confirm: "CLEAR"` и `dry_run` |
| `run-malware-scan` без статуса | добавить `get-scan-status` и `paths`/`max_files` |
| Destructive `meta.public=false` неявный | явно ставить `meta.mcp.public=false` |
| Read-only не имеют `meta.mcp.public` | добавить `meta.mcp.public=true` для совместимости со старыми MCP Adapter |
| Схемы без описаний | добавить `title` и `description` каждому полю (WP 7.1) |
| Нет примеров | добавить `examples` в input_schema |
| `idempotent=false` у upsert | поставить `true` для одинакового входа |
| `block-ip` не идемпотентен | вернуть `changed=false`, если уже заблокирован, и `idempotent=true` |
| `get-settings` только 4 группы | добавить `group=all` или `get-protection-snapshot` |
| Нет кодов ошибок для addon | добавить `addon_inactive`, `ability_disabled` |
| Нет пагинации событий | добавить `cursor`/`next_cursor` |
| Нет `since/until` | добавить во все логовые abilities |
| Нет выбора полей | добавить `fields` для тяжёлых ответов |
| Нет `request_id` | добавить в output destructive-операций |
| Нет метаданных rate limit | добавить `rate_limit` в output |
| Confirm не отражается | добавить `confirm_echo` в output |
| PII в `check-ip` | добавить `mask_pii` |

---

## 7. Политика MCP-экспозиции

| Класс | `meta.public` | `meta.mcp.public` | Канал |
|---|---|---|---|
| RO без PII | `true` | `true` | MCP + REST |
| RO с PII | `false` | `false` | REST / operator MCP |
| RW с confirm | `false` | `false` | REST only |
| Опасные RW | `false` | `false` | REST + человек |
| `quarantine-file`, `import-settings`, `pause-protection`, `set-protection` | `false` | `false` | никогда MCP |

Исключение: `export-settings` - RO, можно в MCP, если `redact_secrets=true`.

---

## 8. Порядок внедрения

1. **P0 read-only** (12 abilities). Это даёт полный аудит через MCP.
2. **Исправления существующих** (confirm, схемы, ошибки).
3. **P1 write** (hardening). Только REST, с dry_run.
4. **P2 forensic** (bundle, timeline, PII). Отдельный operator-сервер.
5. **P3 quality** (explain, validate, capabilities).
6. **Тесты и документация**: каждая ability - минимум 3 кейса (успех, отказ, dry_run).

---

## 9. Как это закрывает режимы wp-bulwark

| Режим | Новые abilities |
|---|---|
| `audit` | health-score, protection-snapshot, addons, license, 2fa, headers, db-health, integrity, audit-log, capabilities, validate-config |
| `harden` | apply-preset, set-country/asn/ua/path, set-2fa-policy, update-threat-data, export/import, pause-protection |
| `incident` | event-details, top-entities, timeline, scan-status, integrity, users-risk, login-attempts, explain-decision |
| `forensic` | incident-bundle, timeline, modified-files, verify-hash, abilities-audit, visitor-log, quarantine-file |
| `recover` | maintain-database, clear-visitor-data, import-settings, reset-rewrite, run-integrity-scan |
| `report` | export-settings, incident-bundle, timeline, health-score |

---

## 10. Критерии приёмки

- [ ] Ability не существует, пока не включён мастер-переключатель.
- [ ] Ability не существует, пока не включён её тумблер.
- [ ] MCP видит только `public=true`.
- [ ] REST видит все, но проверяет `manage_options`.
- [ ] Destructive требует confirm и возвращает ошибку без него.
- [ ] `dry_run=true` не меняет состояние.
- [ ] Каждый вызов пишется в audit log.
- [ ] В output нет секретов и паролей.
- [ ] PII маскируется по флагу.
- [ ] На multisite проверяются network-права.
- [ ] Rate limit работает и возвращает `ability_rate_limited`.
- [ ] Схемы содержат `title`, `description`, `examples`.
- [ ] Есть тесты на успех, отказ и dry_run.
