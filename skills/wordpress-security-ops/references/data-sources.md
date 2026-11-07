# Data Sources

Every finding needs a source. If the source is unavailable, mark `not_checked` and explain why.

## Priority

1. WordPress API or WP-CLI for authoritative state.
2. BotBlocker ability for plugin state and decisions.
3. Direct database read for data the ability does not expose.
4. Host logs for request-level evidence.
5. External services only with consent.
6. Admin UI for manual confirmation.

Do not replace a missing source with a guess. Do not use a marketing number when the code or ability output is available.

## Source Matrix

| Check | Primary | Fallback | Notes |
|---|---|---|---|
| WordPress version | `wp core version` | REST `/wp-json`, database | Record exact version |
| PHP version | `wp --info`, `php -v` | phpinfo, hosting panel | Do not expose phpinfo publicly |
| Database version | `wp db query "SELECT VERSION()"` | hosting panel | Read-only |
| Plugins and themes | `wp plugin list`, `wp theme list` | filesystem, admin UI | Include version, status, update source |
| Must-use plugins | filesystem | `get_option('mu_plugins')` equivalent | Check for unexpected files |
| Drop-ins | filesystem | database | `advanced-cache.php`, `object-cache.php`, `db.php`, `sunrise.php` |
| Users and roles | `wp user list`, `wp user meta` | database, admin UI | Pseudonymize in reports |
| 2FA coverage | `get-2fa-summary` | user meta, security plugin, admin UI | Aggregate first; PII only if needed |
| Sessions | `user-audit.php` | database | Count only; never print tokens |
| REST exposure | HTTP request, `wp option get` | config review | Check user enumeration |
| XML-RPC | HTTP request, settings | server config | Check methods and allowlist |
| Cron | `get-cron-jobs` | `cron-audit.php`, `wp cron event list` | Args redacted by default |
| Firewall state | `get-firewall-status` | settings, admin UI | First call in every mode |
| Protection snapshot | `get-protection-snapshot` | `get-settings` x4 | Partial if unavailable |
| Health Score | `get-health-score` | `validate-config`, manual checklist | Do not hardcode parameter count |
| IP rules | `list-ip-rules`, `get-ip-rules` | database, admin UI | Check expiry and priority |
| IP decision | `check-ip` | manual rule review | Interpret `source` and `matched_rules` |
| Events | `get-recent-events` | database, logs | Aggregates only; no IP or UA |
| Event details | `get-event-details` | web-server logs | PII; REST or WP-CLI only |
| Top entities | `get-top-entities` | logs, database | Aggregate |
| Top IPs | `get-top-ips` | web-server logs | PII; mask in reports |
| Login attempts | `get-login-attempts` | auth logs | PII |
| Visitor log | `get-visitor-log` | access logs | PII |
| User risk | `get-user-risk` | user audit, 2FA summary | PII |
| Ability calls | `get-ability-calls` | audit log, plugin log | PII; mask args |
| Audit log | `get-audit-log` | database audit table, host logs | PII |
| Plugin integrity | `get-integrity-status` | `wp core/plugin verify-checksums`, local manifest | Network for wp.org checksums |
| Malware scan | `get-scan-status`, `get-scan-results` | `malware-scan.php`, manual review | No execution of scanned code |
| Database health | `get-db-health` | `db-audit.php`, phpMyAdmin | Read-only |
| Security headers | `get-security-headers` | `curl -I`, SSL Labs, server config | Check CSP blob workers |
| HTTPS | `get-security-headers` | `curl -I`, browser | Check mixed content |
| Backups | hosting panel, plugin | file listing | Test restore, not just existence |
| Blacklist | Safe Browsing, Search Console, Yandex, Bing | VirusTotal, Sucuri, URLVoid | Consent and rate limits |
| Vulnerabilities | WPScan, Patchstack, NVD | manual review | Consent; cache results |
| DNS and mail | DNS lookup, registrar | hosting panel | SPF, DKIM, DMARC |

## Ability-First Rules

- Use `get-protection-snapshot` instead of four separate settings calls when available.
- Use `get-health-score` as an input, not as the only truth.
- Use `get-top-entities` for aggregates; use `get-top-ips` only when identities are needed.
- Use `get-2fa-summary` first; use `get-user-risk` only for named follow-up.
- Use `get-audit-log` and `get-ability-calls` to reconstruct changes; cross-reference the skill action log.
- Use `get-integrity-status` and `get-scan-status` when the addon is active; fall back to checksums and a local scan.
- The current source tree exposes these abilities; the archived 1.7.10 zip may not. Probe the live build; an absent ability is `not_checked`, so use the fallback column and `without-botblocker.md`.

## Not Checked

Use this format:

```text
not_checked: <check>
reason: <missing access, version, addon, consent, time window>
fallback: <what was used instead, if anything>
impact: <what conclusion cannot be drawn>
```

Examples:

- `not_checked: event_details` because the default MCP cannot see PII and no REST/WP-CLI access was granted.
- `not_checked: integrity_status` because the malware addon is inactive; used `wp core verify-checksums` only.
- `not_checked: external_blacklist` because the client did not consent to external requests.

## External Requests

External APIs are optional. Before calling one:

1. Get consent.
2. State the endpoint and the data sent.
3. Respect rate limits.
4. Cache the result for the engagement.
5. Do not send secrets, PII or full logs.
6. Record the source and timestamp.

## Log Sources

- Web server access and error logs.
- PHP-FPM and PHP error logs.
- BotBlocker events and audit log.
- WordPress debug log, if enabled.
- Host WAF and CDN logs.
- Database audit tables.

Normalize timestamps to UTC. Keep the original line or a hash reference. Do not rewrite evidence.
