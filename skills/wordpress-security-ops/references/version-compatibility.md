# Version Compatibility

The skill supports a range, not a single pinned build. It probes the live site and degrades honestly.

## Matrix

| Component | Supported | Constraint |
|---|---|---|
| WordPress | 5.9-7.1 | Abilities API 6.9+; unified public flag 7.1+ |
| PHP | 7.4-8.5 | Scripts stay 7.4-compatible unless version-gated |
| BotBlocker Security | 1.7.10+ | Current source tree has 48; unpublished build may report 60; archived 1.7.10 has 22 |
| MCP Adapter | 0.7.1+ for operator | Older versions may require `meta.mcp.public` |
| WP-CLI | 2.x | `wp ability` may be nightly; REST fallback |
| MCP protocol | From `initialize` | Never hardcode the protocol version |
| Agents | Claude Code, Codex, Cursor, Gemini CLI, OpenCode, Copilot | MCP config differs; URL and auth are shared |

If a version is outside the matrix, run in `best effort`, label `unsupported_version` and do not claim a full result.

## Ability Sets

| Build | Ability set | Detection | Behavior |
|---|---|---|---|
| Unpublished build | Up to 60 reported; 48 in this source tree | `get-capabilities`, `tools/list`, version | Documented catalog plus runtime extras |
| Archived 1.7.10 | 22 definitions; 20 without the malware addon | `get-firewall-status.version`, `tools/list` | Core catalog and manual fallbacks |
| Addon manifest v2 | Core plus manifest abilities | `get-addons-status`, `get-capabilities`, `tools/list` | Gate by master, addon, `requires_core`, toggle, callable |
| Future | Unknown | Probe only | Use observed names; mark unknown |

Counts are observations, not guarantees. A capability, toggle or addon can reduce them. Never treat the documented count as the live count: discovery decides what exists. An absent ability is `not_checked` with `ability_not_in_build`, and the fallback in `data-sources.md` applies.

## What Changed in the Extended Baseline

- Health Score, protection snapshot, addon and license status.
- Capability map, configuration validation, cron and database health.
- Top entities, 2FA summary, settings export.
- Audit log, event details, top IPs, login attempts, visitor log, user risk, ability calls.
- Bulk rule deletion, country and ASN rules, threat data refresh, preset application.
- Malware scan status, integrity status, security headers.
- Firewall-accurate `check-ip`, guardrails on rule changes, confirmation and dry-run, rate limits, audit of calls and refusals.
- Preset snapshot and restore of ability toggles.
- `canManage()` for network-active multisite.
- Capability overrides and capability-based hiding.
- Addon ability registry with core priority.
- Addon toggles in the addon option.
- New hooks for capability by risk, cron args, strict confirm and addon sections.

## Degradation

| Missing | Fallback | Label |
|---|---|---|
| `get-health-score` | `validate-config`, manual checklist | `health_score_unavailable` |
| `get-protection-snapshot` | `get-settings` x4, `get-captcha-state`, `get-firewall-status` | `snapshot_partial` |
| `get-audit-log` | Host logs, database audit table, ability calls | `audit_log_unavailable` |
| `get-event-details` | Server logs, aggregated events | `pii_unavailable` |
| `get-top-ips` | Server logs, top entities | `top_ips_unavailable` |
| `get-integrity-status` | WP-CLI checksums, wp.org, local manifest | `integrity_partial` |
| `get-scan-status` | `run-malware-scan`, `get-scan-results`, manual scan | `scan_status_unavailable` |
| `get-security-headers` | `curl -I`, SSL Labs, server config | `headers_external` |
| `apply-preset` | Manual diff from the hardening baseline | `preset_unavailable` |
| `delete-ip-rules` | `delete-ip-rule` per id | `bulk_delete_unavailable` |
| Country or ASN rule | IP/CIDR rules, server WAF | `geo_rule_unavailable` |
| REST | WP-CLI or UI | `write_channel_degraded` |
| WP-CLI | REST and UI; no raw DB or files | `host_plane_unavailable` |
| Any external API | Manual check only | `external_not_checked` |

Never fill a missing value with a guess. Use `not_checked`, `unavailable` or `[DATA NEEDED: ...]`.

## Version Policy

- Patch: wording, bug fix, eval, no behavior change.
- Minor: new ability support, playbook or script, backward-compatible.
- Major: mode change, output contract change, removed ability support, new hard gate.

Every BotBlocker release triggers a catalog review and a compatibility test.

## Checksums and Marketing Numbers

- `readme.md` numbers such as diagnostic code counts or Health Score parameter counts are not facts until verified against the installed build.
- Use the ability output and the loaded definitions.
- Do not hardcode "44" or "55" in a report.
- If the number matters, cite the observed count and the version.

## Multisite

- Determine network-active vs per-site before any write.
- `manage_options` on one site may not be enough; check `canManage()`.
- Abilities without capability are hidden from the collection and MCP.
- Rules may be network-wide or per-site.
- If one site is compromised, assume shared users, options and uploads may be affected.
- Confirm whether a preset is network-wide; snapshot before and verify after.
