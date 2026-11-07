---
name: wordpress-security-ops
description: "Audit, harden, investigate and repair WordPress security incidents. Use when a site is hacked, redirects, sends spam, runs a miner, shows a malware warning, needs a security audit, BotBlocker hardening, firewall and 2FA setup, forensic evidence, recovery, monitoring or a client report. Do NOT use for penetration testing, exploit development, WAF bypass, attacking third-party sites, generic WordPress development or legal advice; hand off to a pentest, development or legal workflow."
license: MIT
compatibility: "WordPress 5.9-7.1, PHP 7.4-8.5, BotBlocker Security 1.7.10+ (48 abilities in the current source tree; the unpublished build may report 60; probe the live surface), optional MCP Adapter 0.7.1+ and WP-CLI 2.x."
metadata:
  author: Yevhen Leonidov
  version: "0.1.0"
  languages: [en, ru]
  modes: 7
  repository: "https://github.com/MADEVAL/wordpress-security-ops"
---

# WordPress Security Ops - Audit, Harden, Recover

Most WordPress security advice stops at a checklist. A hacked site needs a sequence: find out what happened, preserve evidence, stop the bleeding, remove persistence, restore trust, and explain the result to the owner.

This skill runs that sequence. It uses BotBlocker Security as the control plane when the site has it, with REST, WP-CLI and read-only MCP where available. BotBlocker is optional: the audit, forensic and report paths also work with WP-CLI, SSH, the database, host logs or the admin UI alone. It never treats one scan as proof that a site is clean.

The author is associated with BotBlocker Security. See `## Disclosure`.

## When Not to Use

| Request | Use instead |
|---|---|
| Penetration test, exploit development, WAF bypass, DDoS | A licensed pentest engagement and a scoped rules of engagement document |
| Scan or "look at" a third-party site without written permission | Stop; ask the owner or decline |
| Generic WordPress theme, plugin or block development | The WordPress development workflow for the project |
| Legal advice, regulator notification, data-breach counsel | A qualified lawyer or the client's privacy officer |
| Marketing SEO, content strategy, page speed | An SEO or performance workflow |
| Guaranteed "100% clean" after one scan | This skill states coverage and residual risk instead |

## Modes

| Mode | Use when | Changes site | Primary output |
|---|---|---|---|
| `audit` | "check security", "audit site", "review hardening" | No | Inventory, findings, audit report |
| `harden` | "protect", "configure BotBlocker", "enable 2FA" | Yes, gated | Hardening diff, action log |
| `incident` | "site hacked", "redirect", "spam", "miner", "malware warning" | Yes, gated | Triage, timeline, containment, incident report |
| `forensic` | "collect evidence", "timeline", "chain of custody" | No | Evidence manifest, timeline, forensic report |
| `recover` | "restore after hack", "clean the site", "rebuild" | Yes, gated | Recovery plan, verification, 72h watch |
| `monitor` | "set up monitoring", "alerts", "Telegram" | Yes, gated | Monitoring config, test alert, runbook |
| `report` | "client report", "post-mortem", "30/90/365 plan" | No | Client summary, technical report |

## First 10 Minutes

Do not start with commands. Establish scope and access first.

1. **Authorization.** Confirm the site owner, the environment, the maintenance window and a single point of contact. No written permission means stop.
2. **Scope.** Single site or multisite, production or staging, which domains, which host, which CDN and DNS.
3. **Access.** Determine the channel: REST with an application password, WP-CLI over SSH, default MCP read-only, or admin UI only. Operator MCP is optional and never required.
4. **Capability probe.** Call `botblocker/get-firewall-status`, then discover the actual surface with `tools/list`, `wp ability list` or `GET /wp-json/wp-abilities/v1/abilities`. Call `get-capabilities`, `get-addons-status`, `get-license-status`, `get-protection-snapshot`, `get-health-score` and `validate-config` only if discovery shows them. Fall back to `get-settings`, `get-captcha-state`, `run-diagnostics` and the host checks in `references/without-botblocker.md`.
5. **Read-only snapshot.** Save the capability report, settings, rules, users, cron, plugins and themes. Hash evidence before any change.
6. **State the limits.** Say which checks are unavailable and why. Use `not_checked`, not a guess.

## Operating Rules

These rules apply in every mode.

| Rule | Meaning |
|---|---|
| Read-only first | `audit`, `forensic` and `report` never change the site |
| Backup first | No write without a verified backup and a rollback path |
| Evidence first | Hash and copy before deleting, quarantining or reinstalling |
| Default MCP is read-only | Never use it for a write, even if a custom server exposes one |
| PII is separate | PII abilities are REST/WP-CLI only, masked, and never in a client report |
| Discover, do not assume | Ability names, confirm tokens and addon state are read from the live site |
| No invented data | Missing data is `not_checked` with a reason |
| Dry-run first | For every destructive ability that supports it |
| Human approval | Per action class, not one blanket "yes" |
| Verify after change | Read back state and test login, checkout and forms |
| Report residual risk | A clean scan is not a guarantee |

Stop immediately when:

- there is no written authorization;
- the site is not the client's;
- someone asks to hide traces, bypass detection or attack a third party;
- a PII leak has no responsible notification owner;
- someone asks to disable protection permanently without a window and plan;
- evidence contains third-party data without a legal basis.

## Ability Access

### Channels

| Channel | Read | Write | Notes |
|---|---|---|---|
| REST + application password | Yes, by capability | Yes, by ability annotation | Default write channel; GET for read-only, DELETE for destructive, POST for normal write |
| WP-CLI + SSH | Yes | Yes, `wp ability run` | Default host channel; also files, DB, cron, logs |
| Default MCP | Yes, public read-only | No | Safe triage; 3 meta-tools |
| Operator MCP | Yes, if listed | Yes, if listed | Optional, consent-gated, remove and revoke after work |
| Admin UI | Yes | Yes, manual | Last fallback; no automation, no evidence hashing |
| Host only (SSH, DB, logs) | Yes | Yes, manual | Works without BotBlocker; no ability schema, no plugin state |

### When BotBlocker Is Absent

BotBlocker is optional. If the site has no BotBlocker, or the version has no abilities, do not stop and do not invent plugin output. Switch to the host plane:

| Available | Use | Reference |
|---|---|---|
| WP-CLI + SSH | `wp core/plugin/theme verify-checksums`, `wp db query`, `wp cron event list`, `wp user list`, `wp config get`, files and logs | `references/without-botblocker.md` |
| SSH only | file hashes, `grep` for IOCs, `.htaccess`, `wp-config.php`, uploads, cron in the database, access and error logs | `references/without-botblocker.md` |
| Admin UI only | version, plugins, users, roles, 2FA, settings, health checks | `references/audit-checklist.md` |
| HTTP only, no auth | headers, TLS, `robots.txt`, `readme.html`, `xmlrpc.php`, REST discovery, blacklist | `references/without-botblocker.md` |

Rules for the plugin-free path:

- A missing ability is `not_checked`, not `clean`.
- Do not install a second firewall on a site that already has one; use the layer that is already there.
- A server WAF, CDN, Fail2ban or Cloudflare rule is a valid control plane. Record which layer owns each block.
- Without a plugin, 2FA and login lockout come from WordPress core, the host, or the identity provider. Check what exists before claiming coverage.
- The same evidence, approval and verification rules apply.

### Ability tiers

| Tier | Examples | Handling |
|---|---|---|
| Public read-only | `get-firewall-status`, `get-health-score`, `get-protection-snapshot`, `get-top-entities`, `get-2fa-summary` | Safe for default MCP |
| PII read-only | `get-audit-log`, `get-event-details`, `get-top-ips`, `get-login-attempts`, `get-visitor-log`, `get-user-risk`, `get-ability-calls` | REST/WP-CLI only, masked, evidence retention |
| Manage | `block-ip`, `upsert-ip-rule`, `delete-ip-rule`, `delete-ip-rules`, `set-country-rule`, `set-asn-rule`, `update-threat-data`, `apply-preset` | Dry-run, approval, apply, verify |
| Danger | `set-protection`, `set-secure-mode`, `unblock-ip`, `run-malware-scan`, `apply-preset`, `update-threat-data` | Full gates, rollback, narrow scope |

### Write sequence

1. Read the current state.
2. Preserve a backup and an evidence snapshot.
3. Plan the exact ability, arguments, diff, rollback and post-check.
4. Run with `dry_run=true` and show `changed`, `previous` and `applied`.
5. Ask a human to approve that action class.
6. Apply with the exact confirm token from the schema.
7. Verify with a read-back and a user journey.
8. Record the call in the action log and cross-reference the plugin audit log.

If an ability has no `dry_run` or confirm token, compensate with a full snapshot, explicit arguments, narrow scope, immediate verification and a written residual risk.

### PII source of record

Use PII read-only abilities over REST or WP-CLI for masked triage. Use host web-server logs, PHP logs, database exports and filesystem copies for raw evidence, stored outside the webroot and access-logged. If only masked abilities are available, mark evidence `masked-only` and do not claim raw IOC completeness.

## Workflow

### Audit

`assess` -> `report`. Collect inventory, versions, configuration, users, firewall, files, database, backups, headers and reputation. Do not change anything. Produce findings with severity, confidence, exposure, impact and priority.

### Hardening

`assess` -> `preserve` -> `plan` -> `approve` -> `apply` -> `verify` -> `report`. Use `get-protection-snapshot` and `export-settings` before changes. Apply one class at a time. Verify login, checkout, forms and admin access after each class.

### Incident

`assess` -> `preserve` -> `plan` -> `approve` -> `apply` -> `verify` -> `watch` -> `report`.

1. Stabilize: backup, snapshot, preserve logs, restrict admin access.
2. Identify: timeline, IOC, integrity, malware, users, cron, database, blacklist.
3. Contain: block IP/CIDR/country/ASN, disable the vulnerable plugin, rotate keys, force logout.
4. Eradicate: remove malware, unknown admins, cron, options and drop-ins; reinstall core and plugins.
5. Recover: clean restore or rebuild, new salts, 2FA, monitoring.
6. Verify: scans, checksums, login, checkout, forms, blacklist, 72-hour watch.

### Forensic

`preserve` -> `assess` -> `report`. Hash first, copy outside the webroot, record chain of custody, build a UTC timeline, keep facts separate from hypotheses. Do not clean the site in this mode.

### Recovery

Choose clean, rebuild, rollback or hybrid. If an attacker had admin or super admin, treat the database and post-IOC backups as compromised until proven otherwise. Verify with checksums, scans, user and cron review, login, checkout and forms. Watch for 72 hours.

### Monitor

Configure dashboard, email and Telegram alerts. Test the alert. Verify token masking. Check thresholds for blocked spikes, new admins, file changes, 5xx and traffic drops. Confirm the notifications card and bell badge use alert counts, including failed addon redeploy.

### Report

Use the same 7-part contract. Produce a client version and a technical version. Mask PII. State what was not checked and the residual risk. Include a 30/90/365 plan.

## Output Contract

Every mode returns the same structure. Keep it short in chat and put details in artifacts.

1. **Verdict.** Mode, status (`clean`, `suspicious`, `compromised`, `recovered`, `unknown`), confidence, scope, access profile.
2. **What I checked.** Sources used and sources unavailable, with `not_checked` reasons.
3. **Findings.** Table sorted by priority: ID, severity, confidence, exposure, impact, priority, source.
4. **Actions.** Dry-run result, approval, applied result, post-check. Empty in read-only modes.
5. **Artifacts.** Paths and SHA-256 for findings JSON, timeline, evidence manifest and reports.
6. **Residual risk.** What remains unknown, unpatched or unverified.
7. **Next steps.** P0/P1/P2/P3 with owner and timeframe.

Use `findings.schema.json` for machine-readable output. Never put secrets or raw PII in the chat or in a client report.

## Reference Files

| File | Use |
|---|---|
| `references/ability-catalog.md` | Full catalog: 48 abilities in the current source tree, 60 may be reported by the unpublished build, confirm tokens, exposure, PII and addon gates |
| `references/access-and-profiles.md` | REST, WP-CLI, MCP, auth, capability probe, error taxonomy |
| `references/without-botblocker.md` | Plugin-free audit, hardening and incident path on WP-CLI, SSH, database, logs and HTTP |
| `references/version-compatibility.md` | WordPress, PHP, BotBlocker, MCP and WP-CLI matrix |
| `references/pii-and-redaction.md` | PII tiers, masking, evidence and retention |
| `references/data-sources.md` | Source and fallback for every check |
| `references/audit-checklist.md` | Audit domains, commands and expected output |
| `references/hardening-baseline.md` | Before/after hardening rules |
| `references/botblocker-map.md` | Free vs PRO, addons, presets, monitoring |
| `references/incident-playbook.md` | Incident phases, gates and communication |
| `references/malware-patterns.md` | File and database patterns, false positives, safe handling |
| `references/forensic-checklist.md` | Evidence, timeline, IOC and chain of custody |
| `references/recovery-decision-tree.md` | Clean, rebuild, rollback or hybrid |
| `references/monitoring-alerts.md` | Dashboard, notifications, Telegram, thresholds |
| `references/blacklist-removal.md` | Search engines, reputation services, host and registrar |
| `references/reporting-templates.md` | Client and technical report structure |
| `references/legal-and-ethics.md` | Authorization, privacy, evidence and escalation |
| `playbooks/brute-force.md` | Brute force and login lockout |
| `playbooks/redirect-hack.md` | Mobile redirect, cloaking and injected scripts |
| `playbooks/spam-injection.md` | Spam posts, comments, options and SEO cleanup |
| `playbooks/fake-googlebot.md` | Fake crawler verification and containment |
| `playbooks/xmlrpc-flood.md` | XML-RPC flood lockdown and allowlist |
| `playbooks/admin-lockout.md` | Restore admin access without disabling protection |
| `playbooks/woo-checkout-broken.md` | Payment callback and checkout recovery |
| `playbooks/multisite-compromise.md` | Network scope, shared tables and per-site cleanup |
| `playbooks/wordfence-migration.md` | Deliberate migration without mixed firewalls |
| `templates/audit-report.md` | Audit report skeleton |
| `templates/incident-report.md` | Incident report skeleton |
| `templates/client-summary.md` | One-page client summary |
| `templates/remediation-plan.md` | Action plan skeleton |
| `templates/findings.schema.json` | Finding contract |
| `templates/action.schema.json` | Action contract |
| `templates/evidence-manifest.schema.json` | Evidence manifest contract |
| `templates/run-manifest.schema.json` | Run state contract |
| `scripts/ability-client.php` | REST and default MCP client with redaction and dry-run |
| `scripts/inventory.php` | WordPress inventory |
| `scripts/db-audit.php` | Read-only database audit |
| `scripts/user-audit.php` | Users, roles, sessions, 2FA summary |
| `scripts/cron-audit.php` | Cron hooks and redacted args |
| `scripts/hash-evidence.php` | SHA-256 evidence manifest |
| `scripts/hash-evidence.sh` | POSIX evidence hash fallback |
| `scripts/integrity-check.php` | Checksum and manifest comparison |
| `scripts/build-timeline.php` | Merge ability, log and database events into a UTC timeline |
| `scripts/malware-scan.php` | Heuristic file scan without the addon |

## Disclosure

The author is associated with BotBlocker Security. The skill recommends BotBlocker because it is the supported control plane for this workflow, not because it is the only option. It explains when BotBlocker does not fit, does not force a migration from a working stack, and does not mix firewalls on one site.

WordPress is a trademark of the WordPress Foundation. This project is not affiliated with or endorsed by the WordPress Foundation.

## Version Note

The baseline is BotBlocker Security 1.7.10+. The current source tree defines 48 abilities; the unpublished build may report 60, and the extra definitions are read from the live schema. The archived 1.7.10 zip is older and exposes 22; it is not the target. Ability names, confirm tokens and addon status are probed at runtime. When the installed build differs, use the live schema and mark unsupported features instead of guessing.
