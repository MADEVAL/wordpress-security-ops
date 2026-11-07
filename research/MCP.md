# MCP-доступ к BotBlocker

Как abilities плагина выходят в Model Context Protocol и что из этого можно использовать в `wordpress-security-ops`.

---

## 1. Схема

```
AI client (Claude Code / Cursor / Codex)
        │  MCP (Streamable HTTP или STDIO)
        ▼
MCP Adapter (плагин WordPress)
        │  meta-tools: discover → get-info → execute
        ▼
WordPress Abilities API (WP 6.9+)
        │  wp_register_ability()
        ▼
BotBlocker Security 1.7.10 (22 abilities)
```

Компоненты:

| Компонент | Роль |
|---|---|
| WordPress 6.9+ | реестр abilities, REST-маршруты |
| WordPress 7.1+ | единый `meta.public`, приоритет `show_in_rest` |
| MCP Adapter | превращает abilities в MCP tools |
| BotBlocker | регистрирует 22 abilities в категории `botblocker` |

---

## 2. Что нужно включить

1. **BotBlocker → Integrations → WP Abilities API** → мастер-переключатель.
2. Точечные тумблеры нужных abilities.
3. Для read-only аудита достаточно:
   - `get-firewall-status`
   - `get-settings`
   - `get-captcha-state`
   - `run-diagnostics`
   - `run-self-test`
   - `get-traffic-summary`
   - `get-recent-events`
   - `list-ip-rules`
   - `get-blocked-ips`
   - `check-ip`
   - `check-ips`
   - `get-ip-rules`
4. Для malware:
   - активный addon `bbcs-malware`;
   - `get-scan-results`.

Без мастер-переключателя abilities не существуют: MCP вернёт `ability_not_found`.

---

## 3. Экспозиция: что видно в MCP

Плагин ставит `meta.public = !destructive`.

| Группа | Кол-во | `meta.public` | MCP | REST |
|---|---:|---|---|---|
| Read-only | 13 | `true` | да | да |
| Destructive | 9 | `false` | нет | да (`show_in_rest=true`) |

Важно:

- **MCP видит только read-only.** Destructive-abilities не попадают в `discover-abilities` и не исполняются через `execute-ability` - адаптер отвечает `ability_not_found`.
- **REST видит все 22**, потому что плагин явно ставит `show_in_rest = true`. Это не ошибка, но это надо учитывать в модели угроз.
- **`public` не даёт прав.** Даже публичная ability требует `manage_options` (по умолчанию).

---

## 4. Endpoint и авторизация

Default server:

```
POST /wp-json/mcp/mcp-adapter-default-server
```

Авторизация:

- HTTP Basic с application password;
- либо cookie + nonce для залогиненного пользователя;
- для CLI - `wp mcp-adapter serve --user=admin`.

Требования MCP Adapter по умолчанию: пользователь залогинен и имеет `read`. Но BotBlocker-ability всё равно требует `manage_options`, поэтому нужен администратор или пользователь с этим правом.

---

## 5. Handshake

1. `initialize` без заголовка сессии.
2. Получить `Mcp-Session-Id` из ответа.
3. Отправить `notifications/initialized` с этим заголовком.
4. Вызывать `tools/list` / `tools/call`.
5. `DELETE` с заголовком для завершения.

Пример:

```bash
SESSION_ID=$(curl -s -D - -X POST \
  "https://site.com/wp-json/mcp/mcp-adapter-default-server" \
  --user "admin:APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"wp-bulwark","version":"0.1"}}}' \
  | grep -i 'mcp-session-id' | awk '{print $2}' | tr -d '\r')
```

---

## 6. Мета-инструменты

Default server отдаёт три инструмента (имена в MCP - через дефис):

| MCP tool | Что делает |
|---|---|
| `mcp-adapter-discover-abilities` | список публичных abilities |
| `mcp-adapter-get-ability-info` | схема конкретной ability |
| `mcp-adapter-execute-ability` | выполнить ability |

Внутренние имена abilities - через слэш: `mcp-adapter/discover-abilities`.

### Discover

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{
  "name":"mcp-adapter-discover-abilities",
  "arguments":{}
}}
```

Ответ: `abilities[]` с `name`, `label`, `description`.

### Get info

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{
  "name":"mcp-adapter-get-ability-info",
  "arguments":{"ability_name":"botblocker/get-firewall-status"}
}}
```

Ответ: `input_schema`, `output_schema`, `meta`.

### Execute

```json
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{
  "name":"mcp-adapter-execute-ability",
  "arguments":{
    "ability_name":"botblocker/check-ip",
    "parameters":{"ip":"203.0.113.10"}
  }
}}
```

Ответ:

```json
{"success":true,"data":{"blocked":false,"reason":"","source":"","matched_rules":0,"protection_enabled":true,"rules":[]}}
```

---

## 7. Что доступно через MCP сейчас

### 13 read-only abilities

| Ability | Зачем скилу |
|---|---|
| `botblocker/get-firewall-status` | базовый статус защиты |
| `botblocker/check-ip` | проверить админский/платёжный IP |
| `botblocker/check-ips` | пакетная проверка |
| `botblocker/get-ip-rules` | правила для IP |
| `botblocker/list-ip-rules` | инвентарь правил |
| `botblocker/get-blocked-ips` | активные баны |
| `botblocker/get-traffic-summary` | масштаб атаки |
| `botblocker/get-recent-events` | динамика блокировок |
| `botblocker/get-settings` | конфигурация |
| `botblocker/get-captcha-state` | доступность CAPTCHA |
| `botblocker/run-diagnostics` | здоровье данных |
| `botblocker/run-self-test` | обходы firewall |
| `botblocker/get-scan-results` | результаты malware-скана (PRO) |

### Чего через MCP нет

| Ability | Почему |
|---|---|
| `block-ip` | destructive |
| `unblock-ip` | destructive |
| `upsert-ip-rule` | destructive |
| `delete-ip-rule` | destructive |
| `set-protection` | destructive |
| `set-secure-mode` | destructive |
| `set-captcha-mode` | destructive |
| `clear-cache` | destructive |
| `run-malware-scan` | destructive |

Это правильная политика: MCP-клиент читает и диагностирует, но не меняет защиту без отдельного канала и подтверждения.

---

## 8. Рекомендуемая политика для скила

### Read через MCP

Разрешено:

- статус, настройки, CAPTCHA;
- диагностика и self-test;
- трафик и агрегированные события;
- IP-правила и проверка IP;
- результаты скана.

Запрещено:

- считать `get-recent-events` доказательством по конкретному IP: там нет IP и UA;
- делать вывод «атаки нет» только по агрегатам;
- использовать `get-settings` как полный аудит конфигурации: там только 4 группы.

### Write через REST

Только:

- с письменным подтверждением человека;
- после бэкапа;
- с `dry_run=true` и просмотром результата;
- с confirm-токеном;
- с записью в отчёт: кто, когда, что, зачем.

Последовательность:

1. `dry_run=true` → получить `would_change`.
2. Показать человеку.
3. Повторить без `dry_run`, с `confirm`.
4. Проверить `applied` и `protection_enabled`.
5. Записать в audit trail.

### Никогда

- не включать `meta.public=true` для destructive-abilities;
- не повышать права MCP-пользователя до `manage_options` без необходимости;
- не использовать одного пользователя для чтения и записи;
- не вызывать `set-protection(false)` без окна;
- не блокировать IP, который является шлюзом платежей или мониторинга.

---

## 9. Клиентские конфиги

### HTTP через mcp-remote

```json
{
  "mcpServers": {
    "wordpress-security": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://site.com/wp-json/mcp/mcp-adapter-default-server",
        "--header",
        "Authorization: Basic BASE64_LOGIN_APP_PASSWORD"
      ]
    }
  }
}
```

### STDIO через WP-CLI

```bash
wp mcp-adapter serve --user=admin --server=mcp-adapter-default-server
```

Подходит для локального аудита и CI.

---

## 10. Подводные камни

1. **`meta.public` vs `show_in_rest`.** В WP 7.1 `show_in_rest` перекрывает `public`. Плагин ставит `show_in_rest=true` всем, поэтому REST видит destructive, а MCP - нет.
2. **Master switch.** Без `bbcs_wp_abilities_enabled` ability не существует, а не «запрещена».
3. **Per-ability toggles.** Можно включить мастер, но забыть тумблер - получите `ability_not_found`.
4. **Addon gating.** `get-scan-results` и `run-malware-scan` не регистрируются без `bbcs-malware`.
5. **Capability.** MCP Adapter пускает с `read`, но BotBlocker требует `manage_options`. Ошибка будет `rest_forbidden`.
6. **Сессия.** Без `Mcp-Session-Id` после initialize - `-32600`.
7. **PII.** `get-recent-events` агрегирован, но `check-ip` возвращает IP-правила. Не тащить их в публичный отчёт без маскировки.
8. **Rate limit.** Destructive-вызовы лимитированы; при серии операций делать паузы и проверять `ability_rate_limited`.
9. **Версии.** Набор abilities и схемы могут меняться; перед вызовом делать `get-ability-info`.
10. **Версия MCP Adapter.** Плагин ставит только `meta.public`, без `meta.mcp.public`. Старые версии адаптера смотрят именно на `meta.mcp.public`, поэтому `discover-abilities` может вернуть пустой список. Проверять на практике; при необходимости обновить адаптер или добавить `meta.mcp.public` в плагин.
11. **Логи.** Каждый вызов пишется в audit log как `botblocker.ability.call`; это использовать в отчёте.

---

## 11. Чек-лист перед подключением MCP

- [ ] WordPress 6.9+.
- [ ] MCP Adapter установлен и активен.
- [ ] BotBlocker 1.7.10+.
- [ ] Master switch включён.
- [ ] Включены только нужные read-only abilities.
- [ ] Создан отдельный пользователь для MCP с `manage_options` (или фильтром понижено право).
- [ ] Application password, не основной пароль.
- [ ] IP MCP-пользователя в allowlist.
- [ ] Проверены `discover-abilities` и `get-ability-info`.
- [ ] Проверен `execute-ability` на `get-firewall-status`.
- [ ] Записано, какие abilities включены и зачем.
