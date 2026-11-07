# BotBlocker Abilities

Ability registry for the current plugin source. Counts are documentation; discovery is authoritative at runtime.

| Class | Count | MCP exposure |
|---|---:|---|
| Public read-only | 26 | Default MCP, REST, WP-CLI |
| PII / non-public read-only | 8 | REST and WP-CLI only, masked |
| Manage | 14 | REST, WP-CLI, optional operator MCP |
| **Total** | **48** | |

Source of truth:

- core: `includes/inc-botblocker-wp-abilities.php` (22 definitions);
- extended: `includes/inc-botblocker-wp-abilities-extended.php` (23 definitions);
- addon manifests: `addons/bbcs-malware/bbcs-addon.json` (2), `addons/bbcs-security-headers/bbcs-addon.json` (1).

An unpublished build may report 60. The extra 12 definitions are not in this source tree. Read them from `wp ability list`, `tools/list` or `GET /wp-json/wp-abilities/v1/abilities`, and add them here with their live schema.

## Public Read-Only

| Ability | Since | Addon | Purpose |
|---|---|---|---|
| `botblocker/get-firewall-status` | 1.7.10 | - | Protection state, secure mode, CAPTCHA mode, version |
| `botblocker/check-ip` | 1.7.10 | - | Firewall-accurate IP decision with `source` and `matched_rules` |
| `botblocker/check-ips` | 1.7.10 | - | Batch check up to 20 IPs |
| `botblocker/get-ip-rules` | 1.7.10 | - | All rules matching an IP |
| `botblocker/list-ip-rules` | 1.7.10 | - | Rule inventory with family, type, expiry and priority |
| `botblocker/get-blocked-ips` | 1.7.10 | - | Active block rules only |
| `botblocker/get-traffic-summary` | 1.7.10 | - | Hits, blocks, CAPTCHA, suspicious and proxy counters |
| `botblocker/get-recent-events` | 1.7.10 | - | Aggregated event buckets, no IP or user agent |
| `botblocker/get-settings` | 1.7.10 | - | Four settings groups; secrets as booleans |
| `botblocker/get-captcha-state` | 1.7.10 | - | Active mode, wait time and mode availability |
| `botblocker/run-diagnostics` | 1.7.10 | - | Data directory, runtime settings and tables |
| `botblocker/run-self-test` | 1.7.10 | - | Self-test summary and bypass reasons |
| `botblocker/get-scan-results` | 1.7.10 | malware | Latest malware scan findings |
| `botblocker/get-health-score` | 1.7.10+ | - | Health score, categories and scored items |
| `botblocker/get-protection-snapshot` | 1.7.10+ | - | Protection state, preset, layers and ability toggles |
| `botblocker/get-addons-status` | 1.7.10+ | - | Addon activation, version, compatibility and abilities |
| `botblocker/get-license-status` | 1.7.10+ | - | License plan, tier, expiry and hits; no key |
| `botblocker/get-capabilities` | 1.7.10+ | - | PRO features, addon features and protection gates |
| `botblocker/validate-config` | 1.7.10+ | - | Read-only configuration checks |
| `botblocker/get-cron-jobs` | 1.7.10+ | - | BotBlocker cron events; arguments hidden by default |
| `botblocker/get-db-health` | 1.7.10+ | - | Table presence, size and approximate row counts |
| `botblocker/get-top-entities` | 1.7.10+ | - | Aggregates by country, ASN, user agent, status or path |
| `botblocker/get-2fa-summary` | 1.7.10+ | - | Administrator 2FA counters; no secrets |
| `botblocker/get-scan-status` | 1.7.10+ | malware | Scan progress, files scanned and ETA |
| `botblocker/get-integrity-status` | 1.7.10+ | malware | Modified, added and missing files from Truth Source |
| `botblocker/get-security-headers` | 1.7.10+ | headers | HTTPS, HSTS, CSP, frame, referrer and mixed content |

## PII / Non-Public Read-Only

These are masked by the plugin and are not exposed through the default MCP server. Use REST with an application password or WP-CLI.

| Ability | Since | Addon | Purpose |
|---|---|---|---|
| `botblocker/get-audit-log` | 1.7.10+ | - | Audit rows with event, severity, actor and date filters |
| `botblocker/get-event-details` | 1.7.10+ | - | One visitor event: IP, ASN, country, UA, path, action, reason |
| `botblocker/get-top-ips` | 1.7.10+ | - | Busiest IP addresses and subnets |
| `botblocker/get-login-attempts` | 1.7.10+ | - | Successful and failed login events |
| `botblocker/get-visitor-log` | 1.7.10+ | - | Recent visitor events with IP, UA and path |
| `botblocker/get-user-risk` | 1.7.10+ | - | User role, 2FA state and failed login counts |
| `botblocker/get-ability-calls` | 1.7.10+ | - | Ability calls with outcome, error and duration |
| `botblocker/export-settings` | 1.7.10+ | - | Settings export; secrets always redacted, `pii=true` |

## Manage

| Ability | Since | Confirm | dry_run | Risk | Addon | Purpose |
|---|---|---|---|---|---|---|
| `botblocker/block-ip` | 1.7.10 | `BLOCK` | Yes | manage | - | Add a block rule and rebuild ban files |
| `botblocker/unblock-ip` | 1.7.10 | `UNBLOCK` | Yes | manage | - | Remove all non-readonly rules for an IP |
| `botblocker/upsert-ip-rule` | 1.7.10 | - | Yes | manage | - | Create or update allow, block, gray or dark rule |
| `botblocker/delete-ip-rule` | 1.7.10 | `DELETE` | Yes | manage | - | Delete one rule by id |
| `botblocker/set-protection` | 1.7.10 | `ENABLE` / `DISABLE` | Yes | manage | - | Toggle protection |
| `botblocker/set-secure-mode` | 1.7.10 | `FRONTEND` / `FULL` | Yes | manage | - | Frontend vs full secure mode |
| `botblocker/set-captcha-mode` | 1.7.10 | - | Yes | manage | - | Switch CAPTCHA mode and wait time |
| `botblocker/clear-cache` | 1.7.10 | - | Yes | manage | - | Clear the plugin file cache |
| `botblocker/run-malware-scan` | 1.7.10 | `SCAN` | No | manage | malware | Start a background malware scan |
| `botblocker/delete-ip-rules` | 1.7.10+ | `DELETE` | Yes | manage | - | Remove non-readonly rules for an IP/CIDR, optional type |
| `botblocker/set-country-rule` | 1.7.10+ | `COUNTRY` | Yes | manage | - | Create, update or disable a country rule |
| `botblocker/set-asn-rule` | 1.7.10+ | `ASN` | Yes | manage | - | Create, update or disable an ASN rule |
| `botblocker/update-threat-data` | 1.7.10+ | `UPDATE` | Yes | danger | - | Refresh ASN, LLM or TLS data |
| `botblocker/apply-preset` | 1.7.10+ | `PRESET` | Yes | manage | - | Apply Light, Strong or Full and restore ability toggles |

## Notes

- `update-threat-data` is the only ability with `risk=danger`.
- `export-settings` is read-only but `pii=true`, so it is not public in MCP.
- `delete-ip-rules` deletes by target address, not by a list of ids. Use `delete-ip-rule` for one id.
- Addon abilities are registered only when the master switch, the addon, `requires_core`, the per-ability toggle and the callable all pass.
- The operational catalog with semantics, error codes and gates is `../skills/wordpress-security-ops/references/ability-catalog.md`.
