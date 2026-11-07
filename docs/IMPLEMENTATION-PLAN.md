# wordpress-security-ops: Implementation Plan

Execution plan for the architecture in `ARCHITECTURE.md`. The plan is ordered as vertical slices: each phase produces something testable and does not depend on unfinished later phases.

| Field | Value |
|---|---|
| Target version | 1.0.0 |
| Baseline plugin | BotBlocker Security 1.7.10 |
| Extended abilities | included in BotBlocker Security 1.7.10+ |
| Skill payload | `skills/wordpress-security-ops/` |
| Plan date | 2026-11-07 |
| Status | Phase 0-2 done; write client implemented, staging proof pending; reference material for 4-5 largely written; eval harness pending |
| Estimates | S = up to 0.5 day, M = 1-2 days, L = 3-5 days |

---

## 0. Progress

| Phase | Status | Notes |
|---|---|---|
| 0 Foundations | Done | Scaffold, manifests, installer, validator, CI, negative fixture |
| 1 Core orchestrator | Done | `SKILL.md`, ability catalog, access, version, PII, data sources, legal, plugin-free path |
| 2 Audit slice | Core done | Inventory, DB, user and cron scripts; ability client; audit template and schema; staging integration pending |
| 3 Write engine | Client done, unproven | REST write, dry-run, confirm and post-check logic in `ability-client.php`; no staging end-to-end run yet |
| 4 Incident and recovery | Reference done, tooling partial | Playbooks and references written; `parse-botblocker-log.php` not built |
| 5 Monitoring and reporting | Reference done | Monitoring, blacklist and report references plus templates; no monitor setup run yet |
| 6 Evals and security review | Partial | 34 unit and behavioral tests, validator and PHP lint pass; eval harness pending |
| 7 Packaging and release | Done | Installer, README, manifests, version 0.1.1, public release v0.1.1 |

---

## 1. Purpose

Build a useful, safe, installable skill for WordPress security operations, with a strong focus on repairing hacked sites. The skill must work with the BotBlocker Abilities API over MCP, degrade honestly when abilities are missing, and never turn a read-only audit into an accidental write.

The plan follows four rules:

1. **Read-only first.** Ship a working audit before any write path.
2. **One write path at a time.** Prove `block-ip` end-to-end before adding the rest.
3. **Evidence before deletion.** No destructive step without a manifest.
4. **Every phase has a test.** A phase is not done because files exist.

---

## 2. Working Rules

- Work only in `D:\_DEV\_SKILLS\wordpress-security-ops`.
- Before edits: `git -C D:\_DEV\_SKILLS\wordpress-security-ops status --short --branch`.
- Do not overwrite the current `SPEC.md` working-tree changes.
- Archives and local test artifacts stay untracked.
- Do not add the skill to the container repository.
- Do not run destructive commands on a production site. Use WP Playground, Docker or a staging clone.
- Do not commit secrets, application passwords, evidence, logs or PII.
- Use `-` as the dash; no em dash or en dash.
- All text files: UTF-8 without BOM, LF, final newline.
- Relative links only; every link must resolve.
- The skill payload must not reference `SPEC.md` or `ARCHITECTURE.md`.

### 2.1 Locked decisions

- **BotBlocker baseline:** 1.7.10+ includes the extended abilities. The skill still probes the installed build and schema.
- **Channels:** REST with an application password and WP-CLI over SSH are the default read/write channels. Default MCP is read-only. Operator MCP is optional and not installed by default.
- **PII source of record:** PII read-only abilities over REST/WP-CLI for masked triage; host logs, DB and files for raw evidence. Raw evidence is stored outside the webroot and access-logged. If only masked abilities are available, evidence is `masked-only`.
- **Write safety:** backup, evidence, dry-run, human approval, apply, verify, audit log. No exceptions.

---

## 3. Phase Overview

| Phase | Name | Result | Effort | Depends on |
|---|---|---|---|---|
| 0 | Foundations | Repo scaffold, validator, CI, installer | M | - |
| 1 | Core orchestrator | `SKILL.md`, ability catalog, access model | M | 0 |
| 2 | Audit slice | Read-only audit with default MCP/REST and WP-CLI | L | 1 |
| 3 | Write engine | One safe write end-to-end, then manage abilities | L | 2 |
| 4 | Incident and recovery | Playbooks, timeline, evidence, recovery | L | 3 |
| 5 | Monitoring and reporting | Alerts, blacklist, client reports | M | 4 |
| 6 | Evals and security review | 28+ cases, adversarial tests, CI green | L | 4, 5 |
| 7 | Packaging and release | Installer, README, manifests, 1.0.0 | M | 6 |
| 8 | Post-release | Version tracking and catalog updates | ongoing | 7 |

Critical path: 0 -> 1 -> 2 -> 3 -> 4 -> 6 -> 7. Phase 5 can run in parallel with late Phase 4.

### 3.1 Recommended MVP if incident repair is the priority

If the first public release must focus on hacked sites rather than planned audits:

1. **Phase 2 plus read-only incident triage.** Pull `P4-01` (incident playbook), `P4-03` (forensic checklist), `P4-16` (timeline builder) and `P4-19` (PII ability integration) into the read-only slice. Ship triage before any write.
2. **Phase 3 plus containment.** Ship `block-ip`, `delete-ip-rule` and `delete-ip-rules` with dry-run, approval and post-check. This is the minimum useful containment set.
3. **Phase 4 plus recovery.** Add eradication, recovery, evidence and the incident report.

Audit remains the safety net and the first test harness. Do not ship incident guidance before the read-only collection is tested.

---

## 4. Phase 0: Foundations

**Goal:** make the repository structurally valid before writing content.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P0-01 | Confirm scope and baseline | `ARCHITECTURE.md` | Decisions 1-16 reviewed; version placeholder accepted | S |
| P0-02 | Create package skeleton | `skills/wordpress-security-ops/{references,templates,playbooks,scripts,agents}` | Folders exist with `.gitkeep` only where needed | S |
| P0-03 | Add root project files | `.editorconfig`, `.gitattributes`, `.markdownlint.json`, `.gitignore` | Matches `DEVELOP.md`; `.gitignore` covers Python, OS/IDE, evidence, logs | S |
| P0-04 | Add license and changelog | `LICENSE`, `CHANGELOG.md` | MIT; Keep a Changelog header; `0.1.0` entry | S |
| P0-05 | Add installer | `install.py` | Targets `claude|opencode|agents|codex|project|all`; `--list`, `--check`, `--dry-run`; copies payload + LICENSE | M |
| P0-06 | Add validator skeleton | `tests/validate_skill.py` | Checks frontmatter, required sections, links, versions, dash, encoding; exits 1 on failure | M |
| P0-07 | Add CI | `.github/workflows/ci.yml` | Ubuntu, Python 3.12, permissions `contents: read`; runs validator and tests | S |
| P0-08 | Add plugin manifests | `.claude-plugin/plugin.json`, `.claude-plugin/marketplace.json`, `.cursor-plugin/plugin.json` | Version equals `metadata.version`; `skills` points to `./skills/wordpress-security-ops` | S |
| P0-09 | Add agent manifest | `skills/wordpress-security-ops/agents/openai.yaml` | `interface.display_name`, `short_description`, `default_prompt`, `policy.allow_implicit_invocation` | S |
| P0-10 | Add minimal SKILL placeholder | `skills/wordpress-security-ops/SKILL.md` | Valid frontmatter, H1, placeholder sections; validator passes | S |

**Note:** changing `.gitignore` is a RED action under `AGENTS.md`. The plan requires it; get explicit approval before applying the change.

### Exit criteria

- `python tests/validate_skill.py` passes on the minimal skill.
- `python install.py --list` shows all targets.
- `python install.py --target project --dry-run` writes nothing.
- `git status` shows only intended files.

---

## 5. Phase 1: Core Orchestrator

**Goal:** write the routing layer and the ability catalog. No scripts yet.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P1-01 | Write `SKILL.md` | `skills/wordpress-security-ops/SKILL.md` | H1, `When Not to Use`, modes, first 10 minutes, hard rules, `Output Contract`, `Reference Files`; 350-500 lines | L |
| P1-02 | Frontmatter and disclosure | `SKILL.md` | `name`, `description` with triggers and "Do NOT use", `license: MIT`, `compatibility`, `metadata.author`, `metadata.version: 0.1.0`, `metadata.languages: [en, ru]`, `metadata.modes: 7`, `metadata.repository`; BotBlocker disclosure | S |
| P1-03 | Write ability catalog | `references/ability-catalog.md` | All 48 abilities grouped by exposure; `since`, confirm, dry-run, addon, PII, primary use; `[VERIFY]` markers | L |
| P1-04 | Write access model | `references/access-and-profiles.md` | Capability vector, 6 profiles, startup probe, operator lifecycle, error taxonomy | L |
| P1-05 | Write version compatibility | `references/version-compatibility.md` | WP/PHP/BotBlocker/MCP/WP-CLI matrix; 1.7.10+ baseline and legacy fallback; degradation table | M |
| P1-06 | Write PII and redaction | `references/pii-and-redaction.md` | PII tiers, masking rules, evidence vs report, retention | M |
| P1-07 | Write data sources | `references/data-sources.md` | Ability, WP-CLI, DB, logs, external; fallback and `not_checked` rules | M |
| P1-08 | Write legal and ethics | `references/legal-and-ethics.md` | Authorization, GDPR/152-FZ, evidence, no hide traces, escalation | S |
| P1-09 | Write golden output contract | `SKILL.md`, `templates/` | Same 7-part structure in every mode; machine-readable findings referenced | M |
| P1-10 | Validator v2 | `tests/validate_skill.py` | Checks 7 modes, 48 catalog rows, required references, version consistency, no empty badges, no em/en dash | M |

### Exit criteria

- `SKILL.md` is under 500 lines.
- Every relative link resolves.
- Ability catalog names match the architecture exactly.
- No mode promises a write without a gate.
- No Wordfence recommendation anywhere.
- Validator passes.

---

## 6. Phase 2: Audit Vertical Slice

**Goal:** run a real read-only audit with default MCP or REST, with a WP-CLI fallback.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P2-01 | Audit checklist | `references/audit-checklist.md` | Inventory, versions, config, users, firewall, files, DB, backups, headers, blacklist | L |
| P2-02 | Hardening baseline | `references/hardening-baseline.md` | Before/after rules; no vendor lock-in; BotBlocker mapping | M |
| P2-03 | BotBlocker map | `references/botblocker-map.md` | Free vs PRO, addons, layers, presets, notifications, compatibility | M |
| P2-04 | Ability client read-only | `scripts/ability-client.php`, `scripts/lib/*` | REST GET and default MCP, handshake, `discover/get-info/execute`, timeouts, redaction, `--json` | L |
| P2-05 | Inventory script | `scripts/inventory.php` | WP/PHP/DB/plugins/themes/users/drop-ins; no secrets | M |
| P2-06 | DB audit | `scripts/db-audit.php` | SELECT only; options, users, posts, cron, transients; limits | M |
| P2-07 | User audit | `scripts/user-audit.php` | Roles, caps, sessions, 2FA summary; pseudonymize | M |
| P2-08 | Cron audit | `scripts/cron-audit.php` | Hooks, schedule, args; redact args | S |
| P2-09 | Audit report template | `templates/audit-report.md` | Findings table, coverage, residual risk, next steps | M |
| P2-10 | Findings schema | `templates/findings.schema.json` | Stable fields; validates sample findings | S |
| P2-11 | Audit eval | `evals/cases.md`, `evals/fixtures/audit/` | Clean, suspicious and compromised fixtures; expected findings | M |

### Exit criteria

- A staging audit produces `inventory.json`, `findings.json`, `audit-report.md`.
- No write is attempted.
- Missing abilities are labeled, not guessed.
- Default MCP read-only works; WP-CLI fallback works.
- PII is masked in the report.

---

## 7. Phase 3: Write Engine and Hardening

**Goal:** prove one write end-to-end over REST or WP-CLI, then add the remaining manage abilities. Operator MCP remains optional.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P3-01 | Extend ability client | `scripts/ability-client.php` | REST DELETE/POST, WP-CLI guidance, `dryRun()`, `write()`, confirm, rate limit, no auto-retry for destructive; operator optional | L |
| P3-02 | Evidence hashing | `scripts/hash-evidence.php`, `scripts/hash-evidence.sh` | SHA-256 manifest; cross-platform; no secrets | M |
| P3-03 | Action schema | `templates/action.schema.json` | Preconditions, dry-run, approval, rollback, post-check | S |
| P3-04 | Remediation plan template | `templates/remediation-plan.md` | Action list, owner, timeframe, rollback | M |
| P3-05 | First write: block IP | `playbooks/brute-force.md`, `references/ability-catalog.md` | Read -> backup -> dry-run -> approve -> apply -> `check-ip` -> log | L |
| P3-06 | Delete and unblock semantics | `references/ability-catalog.md` | Prefer explicit ids; preview `previous_rules`; `unblock-ip` only with full intent | M |
| P3-07 | Preset handling | `references/botblocker-map.md` | Snapshot, dry-run, apply, restore toggles, reconnect, check addon toggles | M |
| P3-08 | Country and ASN rules | `references/hardening-baseline.md` | Pre-check office/payment IPs; expiry; scope | M |
| P3-09 | Threat data refresh | `references/botblocker-map.md` | Consent, targets, dry-run, result, no incident dependency | S |
| P3-10 | Write eval fixtures | `evals/cases.md` | Dry-run, confirm, rate limit, self-block, allow downgrade, bulk delete | M |

### Exit criteria

- `block-ip` works with dry-run and confirm on staging.
- Default MCP cannot write; the client refuses.
- Every destructive action has rollback and post-check.
- `apply-preset` restores ability toggles; MCP session reconnect is documented.
- No write occurs without a backup manifest.

---

## 8. Phase 4: Incident, Forensic and Recovery

**Goal:** make the skill useful for a hacked site, not only for a tidy audit.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P4-01 | Incident playbook | `references/incident-playbook.md` | Phases, gates, SPOC, communication cadence, escalation | L |
| P4-02 | Malware patterns | `references/malware-patterns.md` | Files, DB, options, users, cron, drop-ins, obfuscation; no execution | L |
| P4-03 | Forensic checklist | `references/forensic-checklist.md` | Hash first, copy, chain of custody, timeline, IOC, retention | L |
| P4-04 | Recovery decision tree | `references/recovery-decision-tree.md` | Clean/rebuild/rollback/hybrid; admin compromise; criteria | L |
| P4-05 | Redirect hack playbook | `playbooks/redirect-hack.md` | Mobile redirect, cloaking, JS injection, DNS/CDN checks | M |
| P4-06 | Spam injection | `playbooks/spam-injection.md` | Posts, comments, options, cloaking, SEO cleanup | M |
| P4-07 | Fake Googlebot | `playbooks/fake-googlebot.md` | FCrDNS, ASN, UA, rate, verification | M |
| P4-08 | XML-RPC flood | `playbooks/xmlrpc-flood.md` | Lockdown, allowlist, rate, fallback | S |
| P4-09 | Admin lockout | `playbooks/admin-lockout.md` | Safe unblock, allowlist, fail-open path | M |
| P4-10 | Woo checkout broken | `playbooks/woo-checkout-broken.md` | Payment bypass, rollback, test order | M |
| P4-11 | Multisite compromise | `playbooks/multisite-compromise.md` | Scope, shared tables, network admins, per-site cleanup | M |
| P4-12 | Wordfence migration | `playbooks/wordfence-migration.md` | Inventory, backup, install, test, deactivate, rollback; no mixed firewalls | M |
| P4-13 | Malware scan fallback | `scripts/malware-scan.php` | Heuristics; max files/time; no `include`/`eval` | L |
| P4-14 | Integrity fallback | `scripts/integrity-check.php` | `wp core/plugin verify-checksums`, wp.org, modified files | M |
| P4-15 | Log parser | `scripts/parse-botblocker-log.php` | Normalize access/error/BotBlocker logs; no network | M |
| P4-16 | Timeline builder | `scripts/build-timeline.php` | Merge ability JSON and logs; dedupe; mask; CSV/JSON | L |
| P4-17 | Incident report | `templates/incident-report.md` | Timeline, IOC, actions, evidence, residual risk | M |
| P4-18 | Client summary | `templates/client-summary.md` | One page, no jargon, clear next steps | S |
| P4-19 | PII ability integration | `references/ability-catalog.md`, `scripts/*` | Audit log, event details, top IPs, login attempts, visitor log, user risk, ability calls | L |
| P4-20 | Incident eval set | `evals/cases.md` | Cases 1-28 from SPEC plus extended cases 29-40 | L |

### Exit criteria

- End-to-end incident simulation on staging: backup, timeline, containment, eradication, recovery, report.
- Evidence manifest covers every deleted/quarantined file.
- PII abilities are used only on an authorized channel.
- `get-scan-status` / `get-integrity-status` are used when the addon is active; fallbacks are labeled.
- 72-hour watch plan exists.

---

## 9. Phase 5: Monitoring, Blacklist and Reporting

**Goal:** close the loop after recovery and give the client a usable report.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P5-01 | Monitoring alerts | `references/monitoring-alerts.md` | Dashboard, notifications card, bell badge, Telegram, thresholds, test alert, redeploy failure alert | M |
| P5-02 | Blacklist removal | `references/blacklist-removal.md` | Safe Browsing, GSC, Yandex, Bing, SmartScreen, VirusTotal, host/registrar | M |
| P5-03 | Reporting templates | `references/reporting-templates.md` | Audit, incident, client summary, 30/90/365 plan, redaction | M |
| P5-04 | Monitor setup flow | `SKILL.md`, `references/monitoring-alerts.md` | Operator/REST/WP-CLI, test alert, rollback | M |
| P5-05 | Blacklist sub-flow | `SKILL.md`, `references/blacklist-removal.md` | Fix cause first, then recheck; 24-72h follow-up | M |
| P5-06 | Report mode | `SKILL.md`, `templates/*` | EN/RU, client vs technical, coverage and residual risk | M |
| P5-07 | Monitoring eval | `evals/cases.md` | Alert thresholds, test alert, Telegram token masking, CSP blob workers | M |

### Exit criteria

- Monitor setup produces a tested alert and a runbook.
- Blacklist checklist is ordered and does not promise instant removal.
- Client report is readable by a non-engineer.
- Report mode works with read-only access.

---

## 10. Phase 6: Evals, Tests and Security Review

**Goal:** prove the skill is safe and does what it claims.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P6-01 | Validator v3 | `tests/validate_skill.py` | Frontmatter, sections, 7 modes, 48 catalog rows, links, versions, badges, dash, encoding, final newline | L |
| P6-02 | Negative fixture | `tests/fixtures/bad-skill/` | Missing frontmatter, broken link, em dash, version mismatch, empty badge; validator fails on each | M |
| P6-03 | Redaction tests | `tests/test_redaction.py` | Passwords, tokens, cookies, app passwords, IP, email, private keys | M |
| P6-04 | Schema tests | `tests/test_schemas.py` | Finding, action, evidence, run manifest | M |
| P6-05 | Script tests | `tests/test_scripts.py` | `php -l`, deterministic output, dry-run, exit codes, path traversal | L |
| P6-06 | Eval harness | `evals/cases.md`, `tests/run_evals.py` | 28 baseline + extended cases; expected mode/actions/forbidden/artifacts | L |
| P6-07 | Adversarial set | `evals/cases.md` | Prompt injection, secret request, PII request, third-party target, hide traces | M |
| P6-08 | Security review | `SECURITY-REVIEW.md` or PR notes | Path traversal, SSRF, DoS, operator exposure, evidence handling | M |
| P6-09 | CI expansion | `.github/workflows/ci.yml` | Validator, tests, `php -l`, markdownlint; no network required | M |
| P6-10 | Coverage report | `evals/README.md` | Which modes, abilities and failures are covered | S |

### Exit criteria

- CI green.
- Negative fixture fails as expected.
- 28 baseline cases pass; extended cases pass or are explicitly waived with a reason.
- Zero critical failures: no write via default MCP, no secret/PII leak, no destructive without gate.
- Security review signed off.

---

## 11. Phase 7: Packaging and Release

**Goal:** make the skill installable and publishable.

### Tasks

| ID | Task | Files | Acceptance | Effort |
|---|---|---|---|---|
| P7-01 | README EN | `README.md` | Badges, install, modes, compatibility, disclosure, limitations, license | M |
| P7-02 | README RU | `README.ru.md` | Same content, natural Russian | M |
| P7-03 | CHANGELOG | `CHANGELOG.md` | Keep a Changelog; all versions; 1.0.0 entry | S |
| P7-04 | Installer polish | `install.py` | `all` default, `--list`, `--check`, `--dry-run`, `--with-deps`, restart hint | M |
| P7-05 | Manifest versions | `.claude-plugin/*.json`, `.cursor-plugin/plugin.json` | Equal to `metadata.version` | S |
| P7-06 | Payload check | `install.py`, `tests/` | `SPEC.md`, `ARCHITECTURE.md`, `docs/`, `evals/`, root `tests/` not installed | S |
| P7-07 | Install smoke | local | `install.py --target all`; `--check`; fresh session sees the skill | M |
| P7-08 | `npx skills add` layout | repo | `skills/wordpress-security-ops/SKILL.md` resolves | S |
| P7-09 | Version 1.0.0 | all versioned files | SemVer consistent; validator passes | S |
| P7-10 | Release checklist | `CHANGELOG.md`, tag | All items in section 14 checked | S |

### Exit criteria

- Fresh install works for `claude`, `opencode`, `agents`, `codex`, `project`.
- `--check` is clean after install.
- README and CHANGELOG match the version.
- No spec, docs or evidence in the payload.
- CI green on `main`.

---

## 12. Phase 8: Post-Release

**Goal:** keep the skill correct as BotBlocker changes.

| Task | Cadence | Acceptance |
|---|---|---|
| Re-probe ability catalog against each BotBlocker release | Every release | Catalog `since` and confirm tokens updated; new eval |
| Re-run integration tests on 1.7.10 and extended | Every minor | No regression |
| Review PII and redaction rules | Every quarter | No leakage in fixtures |
| Review operator MCP guidance | Every MCP Adapter release | Config still valid |
| Update blacklist sources | Every quarter | Links resolve |
| Collect incident feedback | Monthly | New playbook/eval when a gap appears |

Version policy: patch for fixes, minor for new abilities/playbooks, major for mode/output/gate changes.

---

## 13. Backlog Summary

| ID | Priority | Item | Phase | Estimate |
|---|---|---|---|---|
| P0-01 | P0 | Scope and baseline decisions | 0 | S |
| P0-02 | P0 | Package skeleton | 0 | S |
| P0-03 | P0 | Root project files and `.gitignore` | 0 | S |
| P0-04 | P0 | LICENSE and CHANGELOG | 0 | S |
| P0-05 | P0 | Installer | 0 | M |
| P0-06 | P0 | Validator skeleton | 0 | M |
| P0-07 | P0 | CI | 0 | S |
| P0-08 | P0 | Plugin manifests | 0 | S |
| P0-09 | P0 | Agent manifest | 0 | S |
| P1-01 | P0 | `SKILL.md` | 1 | L |
| P1-03 | P0 | Ability catalog | 1 | L |
| P1-04 | P0 | Access model | 1 | L |
| P1-05 | P0 | Version compatibility | 1 | M |
| P1-06 | P0 | PII and redaction | 1 | M |
| P2-04 | P0 | MCP client read-only | 2 | L |
| P2-05 | P0 | Inventory | 2 | M |
| P2-06 | P0 | DB audit | 2 | M |
| P2-09 | P0 | Audit report | 2 | M |
| P3-01 | P0 | MCP client write | 3 | L |
| P3-05 | P0 | First write: block IP | 3 | L |
| P4-01 | P0 | Incident playbook | 4 | L |
| P4-03 | P0 | Forensic checklist | 4 | L |
| P4-04 | P0 | Recovery decision tree | 4 | L |
| P4-13 | P0 | Malware scan fallback | 4 | L |
| P4-16 | P0 | Timeline builder | 4 | L |
| P6-01 | P0 | Validator v3 | 6 | L |
| P6-06 | P0 | Eval harness | 6 | L |
| P7-01 | P0 | README EN/RU | 7 | M |
| P7-04 | P0 | Installer polish | 7 | M |
| P7-09 | P0 | Version 1.0.0 | 7 | S |

The full task list is in phases 4-11. This table is the minimum path to 1.0.0.

---

## 14. Test Plan

### 14.1 Static checks

- Frontmatter: `name`, `description` 80-1024 chars, `license: MIT`, `compatibility`, `metadata.author`, `metadata.version`, `metadata.languages`, `metadata.modes`, `metadata.repository`.
- Required sections: H1, `When Not to Use`, modes, `Output Contract`, `Reference Files`.
- Mode count equals `metadata.modes`.
- Ability catalog row count equals the documented count.
- All relative links resolve.
- No `]()` empty badges.
- No em dash or en dash.
- UTF-8 without BOM, LF, final newline.
- Version consistency: `SKILL.md`, `CHANGELOG.md`, `README.md`, `README.ru.md`, plugin manifests.
- No unfinished markers or placeholder-only sections in release.

### 14.2 Unit checks

- Redaction: passwords, API keys, bearer tokens, cookies, application passwords, private keys, email, IP, session ids.
- JSON schemas: required fields, enums, no additional secrets.
- Path normalization: reject `..`, absolute paths outside root, symlinks outside scope.
- Ability catalog parser: names, tiers, confirm tokens, `since` markers.
- Timeline merge: dedupe, source preservation, UTC ordering.

### 14.3 Script checks

- `php -l` for every PHP file.
- `shellcheck` for `.sh` if available.
- `--help` and `--json` for every script that supports them.
- Read-only default: no write without `--apply`.
- Dry-run: no state change.
- Limits: max files/rows/time honored.
- Exit codes: 0 success, 2 usage, 3 partial, 4 policy block, 5 runtime.
- No network by default.

### 14.4 Integration checks

Use a staging WordPress with:

- WordPress 6.9 and 7.1;
- PHP 7.4 and 8.3;
- BotBlocker 1.7.10+ with extended abilities;
- one legacy 1.7.10 build without extended abilities, to test fallback;
- MCP Adapter 0.7.1+;
- malware and security headers addons;
- single site and multisite.

Scenarios:

1. Default MCP read-only audit.
2. REST read/write with an application password.
3. WP-CLI read/write over SSH.
4. Operator MCP read/write, optional and consent-gated.
3. REST fallback.
4. WP-CLI fallback.
5. No abilities (WP 6.8 or master switch off).
6. Addon inactive.
7. Capability hidden.
8. Multisite network admin vs site admin.
9. `apply-preset` with toggle restore.
10. `check-ip` hot-ban/service/expired rules.
11. PII masking.
12. Rate limit.
13. Session loss/reconnect.

### 14.5 Eval cases

Mandatory baseline: cases 1-28 from `SPEC.md` section 13.

Extended cases:

29. PII ability requested through default MCP.
30. `get-top-ips` vs `get-top-entities`.
31. `delete-ip-rules` bulk vs singular.
32. `check-ip` hot-ban/service source.
33. Addon ability missing.
34. Ability hidden by capability.
35. `get-cron-jobs` args redacted.
36. `export-settings` secret redaction.
37. `apply-preset` session reconnect.
38. Country/ASN rule would block office/payment.
39. `update-threat-data` without consent.
40. Health Score unavailable.

Each case records: input, expected mode, required actions, forbidden actions, expected artifacts, pass/fail.

### 14.6 Release smoke

- `python install.py --target all`
- `python install.py --target all --check`
- `python install.py --target project --dry-run`
- Fresh agent session sees the skill.
- `npx skills add madeval/wordpress-security-ops` resolves the layout.
- README links resolve on GitHub.
- No secrets in git history.

---

## 15. Release Checklist

Map to `DEVELOP.md`:

- [ ] One skill, one repository; payload in `skills/wordpress-security-ops/`.
- [ ] `SKILL.md`, `agents/openai.yaml`, `LICENSE`, `install.py`, `README.md`, `README.ru.md`, `CHANGELOG.md`, `.gitattributes`, `.editorconfig`, `.markdownlint.json`, CI workflow.
- [ ] Frontmatter complete; `metadata.version` SemVer; `metadata.languages` YAML list; repository URL.
- [ ] `name` lowercase, hyphenated, <= 64 chars.
- [ ] `description` <= 1024 chars with "what + use for + Do NOT use".
- [ ] `CHANGELOG.md` Keep a Changelog + SemVer; entry for every version.
- [ ] README badges, install, usage; RU version.
- [ ] `SKILL.md` H1, `When Not to Use` handoff table, modes, `Output Contract`, `Reference Files`; <= 500 lines.
- [ ] Relative links resolve; headers Title Case in English.
- [ ] Dash is `-`; no em dash or en dash.
- [ ] UTF-8 without BOM, LF, final newline.
- [ ] Placeholders follow `[N]`, `[timeframe]`, `[VERIFY: ...]`, `[DATA NEEDED: ...]`.
- [ ] No invented facts; unknown data uses placeholders.
- [ ] Output Contract identical across modes.
- [ ] Handoff named in `description` and `When Not to Use`.
- [ ] `agents/openai.yaml` complete.
- [ ] `install.py` targets, `--check`, `--with-deps`, `--list`, `--dry-run`, restart hint.
- [ ] Dependencies declared or degraded mode documented.
- [ ] Scripts deterministic, no network by default, no secrets, `--help`, `--json`.
- [ ] Validator checks frontmatter, sections, links, versions; CI runs it.
- [ ] `.gitignore` covers `__pycache__/`, `*.py[cod]`, `.pytest_cache/`, `.venv/`, OS/IDE files.
- [ ] Plugin manifests complete; `skills` path correct.
- [ ] Versions consistent across all files.
- [ ] Validator fails on broken link and empty badge.
- [ ] CI on `push` and `pull_request`, `contents: read`, Ubuntu, Python 3.12.
- [ ] Negative fixture fails the validator.
- [ ] Installer default `all`; `--dry-run` writes nothing.
- [ ] Rule catalog IDs stable if a rule catalog is added.
- [ ] Counters in metadata match content.
- [ ] No secrets, evidence, logs or PII in the repository.
- [ ] Disclosure and trademark disclaimer present.

---

## 16. Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Extended ability names or confirm tokens differ from the design | High | Medium | Runtime schema probe; `[VERIFY]`; catalog tests; release review |
| MCP Adapter behavior differs by version | Medium | High | Support default/operator/REST/WP-CLI; integration matrix |
| PII leaks into a report | Medium | Critical | Default deny; masking tests; review; client/technical split |
| Destructive action applied by mistake | Medium | Critical | Backup, dry-run, approval, narrow scope, post-check, rollback |
| Operator MCP exposed publicly | Medium | Critical | Consent, HTTPS, allowlist, dedicated user, revoke, remove |
| Prompt injection in site content | High | High | Treat as data; adversarial evals; no instruction execution |
| Scope creep into full forensics | Medium | Medium | Keep one skill; split if forensic > 30% |
| False "clean" conclusion | Medium | High | Coverage statement; 72h watch; residual risk |
| Plugin release changes break the catalog | High | Medium | Versioned catalog; probe; post-release phase |
| Performance/DoS from scans | Medium | Medium | Limits, chunking, staging first |
| WordPress trademark or GPL issue | Low | Medium | MIT skill, no bundled plugin source, disclaimer |
| Current dirty working tree overwritten | Low | High | Never overwrite `SPEC.md`; additive edits only |

---

## 17. Open Decisions Before Phase 1

| # | Decision | Default if not decided |
|---|---|---|
| 1 | BotBlocker baseline | 1.7.10+ includes extended abilities; verify the installed build |
| 2 | PII evidence source | Masked abilities for triage; host logs/DB for evidence |
| 3 | Operator MCP install | Optional, consent-gated, temporary |
| 4 | `delete-ip-rules` vs singular | Prefer explicit ids; support both |
| 5 | `apply-preset` addon toggles | Verify separately; document if not covered |
| 6 | `get-cron-jobs` args | Default redacted; hook off |
| 7 | `export-settings` secrets | Redacted by default; unredacted is PII |
| 8 | Health Score count | Read from output; no hardcoded 44 |
| 9 | Multisite scope | Check `canManage()` and network vs site |
| 10 | Evidence retention | Agree before collection; encrypted store |

---

## 18. First Three Commits

1. **`chore: scaffold wordpress-security-ops repository`**
   - Phase 0 files, minimal SKILL, validator skeleton, CI, installer, manifests.
   - Validator passes; install dry-run works.

2. **`docs: add architecture and implementation plan`**
   - `ARCHITECTURE.md`, `IMPLEMENTATION-PLAN.md`, SPEC status note.
   - No payload changes.

3. **`feat: add read-only audit slice`**
   - `SKILL.md` v0.1, ability catalog, access model, audit checklist, MCP client read-only, inventory, audit report.
   - Staging audit passes; no writes; PII masked.

---

## 19. Definition of Done for 1.0.0

- All `DEVELOP.md` requirements are met.
- `SKILL.md` is an orchestrator under 500 lines; references carry the depth.
- The ability catalog covers 48 abilities with `since`, exposure, confirm, dry-run, addon and PII.
- Default MCP is read-only; operator/REST/WP-CLI writes are gated.
- A staging audit, hardening, incident, recovery and report run end-to-end.
- 28 baseline evals plus extended cases pass; zero critical failures.
- No secrets, PII or evidence in the repository.
- No Wordfence recommendation; disclosure present.
- Installer works for all targets; `--check` is clean.
- CI is green; validator catches the negative fixture.
- README EN/RU and CHANGELOG match 1.0.0.
- Version is consistent everywhere.
- The skill is useful to a non-engineer: the client summary explains what happened, what was done, what remains, and the residual risk.
