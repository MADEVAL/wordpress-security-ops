# Ability Catalog

The current plugin source defines 48 abilities: 22 core, 23 extended and 3 addon abilities. This catalog documents all 48. The archived 1.7.10 zip is an older snapshot with 22; the current plugin source is the target.

The unpublished build may report 60. The extra 12 definitions are not in this source tree. Read them from discovery (`wp ability list`, `tools/list`, `GET /wp-json/wp-abilities/v1/abilities`), use their live schema and append them here. Do not mark an observed ability as nonexistent.

| Class | Count | Exposure |
|---|---:|---|
| Public read-only | 26 | Default MCP, REST, WP-CLI |
| PII / non-public read-only | 8 | REST and WP-CLI only, masked |
| Manage | 14 | REST, WP-CLI, optional operator MCP |

If a live build reports more than 48, discovery is authoritative: read the extra schemas, record them in the run manifest and append them here. Do not mark an observed ability as nonexistent.

Counts are documentation, not a promise. `wp ability list`, `tools/list` or `GET /wp-json/wp-abilities/v1/abilities` shows the live set.

The installed build is the source of truth for names, confirm tokens, `dry_run` and addon state. Read `input_schema`, `output_schema` and annotations before a call. The tokens below are expectations; take the exact token from the live schema.

Legend:

- `RO` - read-only.
- `PII` - read-only, masked, not public in MCP.
- `RW` - manage or write.
- `Danger` - high blast radius.
- `Addon` - registered only when the addon is active.
- `[V]` - verify the exact token or flag in the installed build.

## Public Read-Only

| Ability | Since | Confirm | dry_run | Addon | Use |
|---|---|---|---|---|---|
| `botblocker/get-firewall-status` | 1.7.10 | - | - | - | Version, protection state, secure mode, CAPTCHA mode |
| `botblocker/check-ip` | 1.7.10 | - | - | - | Firewall-accurate IP decision with `source` and `matched_rules` |
| `botblocker/check-ips` | 1.7.10 | - | - | - | Batch check up to 20 IPs |
| `botblocker/get-ip-rules` | 1.7.10 | - | - | - | All rules matching an IP |
| `botblocker/list-ip-rules` | 1.7.10 | - | - | - | Rule inventory with family, rule, expiry, priority |
| `botblocker/get-blocked-ips` | 1.7.10 | - | - | - | Active block rules |
| `botblocker/get-traffic-summary` | 1.7.10 | - | - | - | Hits, blocks, CAPTCHA, suspicious, proxy |
| `botblocker/get-recent-events` | 1.7.10 | - | - | - | Aggregated event buckets, no IP or UA |
| `botblocker/get-settings` | 1.7.10 | - | - | - | Four settings groups; secrets as booleans |
| `botblocker/get-captcha-state` | 1.7.10 | - | - | - | Mode availability and warnings |
| `botblocker/run-diagnostics` | 1.7.10 | - | - | - | Data directory, runtime settings, tables |
| `botblocker/run-self-test` | 1.7.10 | - | - | - | Normal bypasses for the current request |
| `botblocker/get-scan-results` | 1.7.10 | - | - | malware | Last malware scan findings |
| `botblocker/get-health-score` | 1.7.10+ | - | - | - | Posture score, categories, items |
| `botblocker/get-protection-snapshot` | 1.7.10+ | - | - | - | Settings, layers, ability toggles, addons, license |
| `botblocker/get-addons-status` | 1.7.10+ | - | - | - | Addon installed, active, version, compatibility |
| `botblocker/get-license-status` | 1.7.10+ | - | - | - | Plan, features, expiry; no keys |
| `botblocker/get-capabilities` | 1.7.10+ | - | - | - | Feature and ability availability |
| `botblocker/validate-config` | 1.7.10+ | - | - | - | Configuration lint and severity |
| `botblocker/get-cron-jobs` | 1.7.10+ | - | - | - | Cron hooks, schedule, redacted args |
| `botblocker/get-db-health` | 1.7.10+ | - | - | - | Table size, autoload, transients, cron |
| `botblocker/get-top-entities` | 1.7.10+ | - | - | - | Aggregate top country, ASN, UA, path, status |
| `botblocker/get-2fa-summary` | 1.7.10+ | - | - | - | Aggregate 2FA coverage, no logins |
| `botblocker/get-scan-status` | 1.7.10+ | - | - | malware | Scan progress, files scanned, ETA |
| `botblocker/get-integrity-status` | 1.7.10+ | - | - | malware | Truth Source modified, added and missing files |
| `botblocker/get-security-headers` | 1.7.10+ | - | - | headers | HTTPS, HSTS, CSP, frame, referrer, mixed content |

## PII Read-Only

These are masked by the plugin and are not exposed through the default MCP server. Use REST with an application password or WP-CLI. Keep raw host evidence separate.

| Ability | Since | Addon | Use | Report handling |
|---|---|---|---|---|
| `botblocker/get-audit-log` | 1.7.10+ | - | Who changed what, ability calls, admin actions | Mask actor, IP and args |
| `botblocker/get-event-details` | 1.7.10+ | - | IP, ASN, country, UA, path, action, reason | Mask IP in client report |
| `botblocker/get-top-ips` | 1.7.10+ | - | Top attacker IPs and subnets | Mask IPs; keep full in evidence |
| `botblocker/get-login-attempts` | 1.7.10+ | - | Failed and successful logins, brute force | Mask login and IP |
| `botblocker/get-visitor-log` | 1.7.10+ | - | Visitor requests, status and action | Aggregate in client report |
| `botblocker/get-user-risk` | 1.7.10+ | - | Admin risk, stale users, 2FA gaps | Pseudonymize users |
| `botblocker/get-ability-calls` | 1.7.10+ | - | Ability call audit, outcome, duration | Mask args and actor |
| `botblocker/export-settings` | 1.7.10+ | - | Settings export with secrets redacted; `pii=true`, not public | Treat as PII; never in a report |

## Manage

Every manage call needs a plan, a backup, a dry-run where supported, a human approval and a post-check. `Risk` is the plugin `risk` field: `manage` or `danger`. `update-threat-data` is the only `danger` ability.

| Ability | Since | Confirm | dry_run | Risk | Addon | Use |
|---|---|---|---|---|---|---|
| `botblocker/block-ip` | 1.7.10 | `BLOCK` | Yes | manage | - | Block a confirmed attacker |
| `botblocker/unblock-ip` | 1.7.10 | `UNBLOCK` | Yes | manage | - | Remove all non-readonly rules for an IP; preview first |
| `botblocker/upsert-ip-rule` | 1.7.10 | - | Yes | manage | - | Create or update allow, block, gray or dark rule |
| `botblocker/delete-ip-rule` | 1.7.10 | `DELETE` | Yes | manage | - | Delete one rule by id |
| `botblocker/set-protection` | 1.7.10 | `ENABLE` / `DISABLE` | Yes | manage | - | Toggle protection; never off without a window |
| `botblocker/set-secure-mode` | 1.7.10 | `FRONTEND` / `FULL` | Yes | manage | - | Frontend vs full; full can lock out admin |
| `botblocker/set-captcha-mode` | 1.7.10 | - | Yes | manage | - | Change CAPTCHA mode; reject unavailable modes |
| `botblocker/clear-cache` | 1.7.10 | - | Yes | manage | - | Clear file cache after changes |
| `botblocker/run-malware-scan` | 1.7.10 | `SCAN` | No | manage | malware | Start background scan |
| `botblocker/delete-ip-rules` | 1.7.10+ | `DELETE` | Yes | manage | - | Remove non-readonly rules for an IP or CIDR, optionally one rule type |
| `botblocker/set-country-rule` | 1.7.10+ | `COUNTRY` | Yes | manage | - | Create, update or disable a country rule |
| `botblocker/set-asn-rule` | 1.7.10+ | `ASN` | Yes | manage | - | Create, update or disable an ASN rule |
| `botblocker/update-threat-data` | 1.7.10+ | `UPDATE` | Yes | danger | - | Refresh ASN, LLM or TLS data; network call |
| `botblocker/apply-preset` | 1.7.10+ | `PRESET` | Yes | manage | - | Apply Light, Strong or Full; keep current ability toggles |

## Extended Abilities

`1.7.10+` means the extended surface in the current source tree. An unpublished build may report 60 definitions; the extra definitions are read from discovery. The archived 1.7.10 zip is older and has 22; it is not the target build.

The skill still probes at runtime because a site can run an older build, an addon can be inactive, or a capability can hide an ability. When the live build does not expose an ability:

- mark the check `not_checked` with the reason `ability_not_in_build`;
- use the fallback in `data-sources.md`;
- do not report the check as clean and do not invent a result.

When the live build reports abilities beyond the 48 documented here, use the live schema, record them in the run manifest and add them to this catalog.

The confirm tokens above match the current source. Still read the live schema: a future build can change a token.

## Special Semantics

### `check-ip`

The decision follows the firewall:

- rule priority is the same as the firewall;
- expired rules are ignored;
- service rows and hot-ban entries participate;
- `source` and `matched_rules` explain the result.

A block may come from a country, ASN or hot-ban layer, not only an IP rule. Use `check-ip` before and after containment for the operator IP, hosting, monitoring, payment gateways and crawlers. Do not use it alone to prove an attack is over.

### `delete-ip-rules` and `unblock-ip`

`delete-ip-rules` targets one IP or CIDR (`ip`), optionally filtered by `rule` (allow, block, gray, dark). It skips readonly rows and returns `previous_rules`. `unblock-ip` removes every non-readonly rule for the IP, including allow, gray and dark.

Use them this way:

1. `list-ip-rules` or `get-ip-rules` to see the target and its rule types.
2. `delete-ip-rule` by id when exactly one rule must go.
3. `delete-ip-rules` with `ip` and an optional `rule` filter when a reviewed class of rules must go.
4. `unblock-ip` only when every rule for the IP should go and `previous_rules` has been reviewed.

`delete-ip-rules` is not a bulk-by-id operation. It deletes by target address.

### `apply-preset`

The ability snapshots ability toggles and restores them after applying. Still verify:

1. `get-protection-snapshot` before.
2. Dry-run and show changed settings and toggles.
3. Approve.
4. Apply.
5. Snapshot again and compare.
6. Re-run `tools/list` or `wp ability list`; MCP sessions may need reconnect.
7. Check addon ability toggles separately. They live in the addon option, not in core settings.

### Country and ASN rules

Before applying:

- `check-ip` for office, VPN, hosting, monitoring, payment and CDN ranges;
- confirm network-wide or per-site scope on multisite;
- prefer a temporary rule with expiry;
- record the business reason and approver;
- verify a known-good IP after applying.

Never block a country or ASN that contains the payment gateway, host or operator without an explicit exception list.

### `update-threat-data`

This is a `danger` network operation with `source` of `asn`, `llm` or `tls`. Ask for consent, state the source, dry-run first, and record `previous` and `status`. It refreshes data; it does not delete local rules. Do not run it during an active incident unless the data is needed and the network path is trusted.

### `export-settings`

Read-only but `pii=true`, so it is not public in MCP. Secrets are redacted by default and cannot be returned in cleartext. Use it for diff and pre-change snapshots with `redact_secrets=true` and `mask_pii=true`. Never place the output in a report or repository.

### Addon abilities

- Core definitions gated by the malware addon: `get-scan-results`, `run-malware-scan`.
- Malware manifest abilities: `get-scan-status`, `get-integrity-status`.
- Security headers manifest ability: `get-security-headers`.
- Gates: master switch, addon active, `requires_core`, per-ability toggle, callable.
- Core wins name collisions.
- Addon toggles live in the addon option.
- A broken manifest entry is isolated and does not break the addon.

## Error Taxonomy

| Error | Meaning | Action |
|---|---|---|
| `ability_not_found` | Master switch off, toggle off, addon inactive, hidden by capability, or not in operator tools | Check all four; do not retry blindly |
| `rest_forbidden` | Missing capability | Check user, role, multisite and filters |
| `ability_invalid_permissions` | Abilities API permission wrapper | Same as `rest_forbidden` |
| `ability_confirmation_required` | Missing or wrong confirm token | Read the schema, ask the human, pass the exact token |
| `ability_rate_limited` | Destructive rate limit | Wait; do not bypass |
| `ability_readonly_rule` | System rule cannot be changed | Choose another rule; never force |
| `ability_self_block_requires_force` | Would block own or server IP | Verify intent; do not force without explicit approval |
| `ability_allow_downgrade_requires_force` | Would downgrade allow to block | Verify intent; do not force |
| `ability_mode_unavailable` | CAPTCHA mode unavailable | Choose an available mode; do not substitute silently |
| `ability_unavailable` | Addon, class or tables missing | Check install, addon and database |
| `ability_execution_failed` | Write failed | Check database and permissions; do not auto-retry |
| `ability_invalid_input` | Schema violation | Read `input_schema` and fix |
| `unsupported_version` | Feature not in this build | Use a fallback and mark it |
| `pii_unavailable` | Default MCP cannot see PII | Use REST, WP-CLI or host logs |
| `operator_required` | Write attempted on default MCP | Stop; use REST or WP-CLI |
| `session_required` | Missing MCP session | Re-initialize; retry read-only only |

## Exposure Policy

| Class | Default MCP | Operator MCP | REST | Report |
|---|---|---|---|---|
| Public read-only | Yes | Yes | Yes | Quote |
| PII read-only | No | Yes, if listed | Yes, masked | Aggregate or mask |
| Manage | No | Yes | Yes | Diff only |
| Danger | No | Yes, with human gate | Yes | Diff and approval |
| Addon | Depends on class | Yes | Yes | Same as class |

Never set a destructive ability to public. Never use the default MCP for a write. Never treat confirm tokens or rate limits as authorization.
