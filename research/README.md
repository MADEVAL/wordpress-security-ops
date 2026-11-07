# Research: BotBlocker Security Abilities

Исследование исходников плагина для скила `wordpress-security-ops`.

| Поле | Значение |
|---|---|
| Плагин | BotBlocker Security - Complete security platform |
| Версия | 1.7.10 |
| Источник | https://downloads.wordpress.org/plugin/botblocker-security.1.7.10.zip |
| Страница | https://wordpress.org/plugins/botblocker-security/ |
| Автор | Yevhen Leonidov / GLOBUS.studio |
| Активных установок | 3 000+ (на 2026-11-07) |
| Лицензия | GPLv2 or later |
| Файл abilities | `includes/inc-botblocker-wp-abilities.php` |
| UI | `admin/templates/integrations/wp-abilities.php` |

## Что лежит в папке

| Путь | Что |
|---|---|
| `plugin/botblocker-security/` | распакованный плагин 1.7.10 |
| `botblocker-security.1.7.10.zip` | исходный архив, SHA-256 `E0BF842425B2BDA16D9D123F9B711BE9C64DD912C959BDB7BB5DC355672A5D19` |
| `ABILITIES.md` | полный инвентарь 22 abilities: входы, выходы, права, ограничения |
| `MCP.md` | как abilities выходят в MCP, что доступно сейчас, что нет |
| `ABILITIES-GAPS.md` | каких abilities не хватает и что добавить в плагин |
| `.gitignore` | исключает `plugin/` и `*.zip` из будущего git-репозитория |

## Ключевые выводы

1. Плагин регистрирует **22 abilities** в категории `botblocker`.
2. **13 read-only** и **9 destructive**.
3. Read-only имеют `meta.public = true` и видны через MCP Adapter.
4. Destructive имеют `meta.public = false` и **не видны через MCP**, но остаются доступны через REST, потому что плагин явно ставит `show_in_rest = true`.
5. Всё выключено по умолчанию: мастер-переключатель `bbcs_wp_abilities_enabled` и отдельный тумблер на каждую ability.
6. Права: `manage_options` (можно переопределить фильтром `bbcs_ability_required_capability`).
7. Destructive-вызовы требуют confirm-токен, поддерживают `dry_run` и rate limit. Но у трёх abilities confirm-токена нет - это надо исправить.
8. Для полного цикла аудита и инцидента не хватает ~40 abilities: Health Score, audit log, детальные события, целостность файлов, 2FA-статус, security headers, top-entities, экспорт/импорт, пресеты, country/ASN/UA-правила, DB maintenance, incident bundle и др.

## Как воспроизвести

```powershell
$dir = "D:\_DEV\_SKILLS\wordpress-security-ops\research"
Invoke-WebRequest "https://downloads.wordpress.org/plugin/botblocker-security.1.7.10.zip" -OutFile "$dir\botblocker-security.1.7.10.zip"
Expand-Archive "$dir\botblocker-security.1.7.10.zip" -DestinationPath "$dir\plugin" -Force
```

Проверить количество abilities:

```powershell
Select-String -Path "$dir\plugin\botblocker-security\includes\inc-botblocker-wp-abilities.php" -Pattern "'name'\s*=>\s*'botblocker/"
```

## Ограничения

- Исследование по исходникам 1.7.10; в следующих версиях набор может измениться.
- Поведение MCP зависит от версии WordPress и MCP Adapter:
  - WP 6.9+ - Abilities API;
  - WP 7.1 - единый `meta.public`;
  - MCP Adapter - отдельный плагин, default server.
- `meta.public` управляет видимостью, но не авторизацией. Права проверяет `permission_callback`.
- Плагин ставит `meta.public`, но не `meta.mcp.public`. Старые версии MCP Adapter могут не видеть abilities; проверять через `discover-abilities` и при необходимости обновлять адаптер.
- Названия REST-маршрутов и MCP-инструментов могут отличаться между версиями адаптера.
