# WordPress Security Ops

![Version](https://img.shields.io/badge/version-0.1.1-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![CI](https://github.com/MADEVAL/wordpress-security-ops/actions/workflows/ci.yml/badge.svg)

Operational security skill for WordPress: audit, harden, investigate and repair hacked sites.

`wordpress-security-ops` is not another hardening checklist. It runs the full cycle:

**discover -> confirm -> contain -> eradicate -> recover -> verify -> report.**

It uses BotBlocker Security as the control plane when the site has it, with REST, WP-CLI and read-only MCP where available. BotBlocker is optional: the plugin-free path works with WP-CLI, SSH, the database, host logs or the admin UI alone. It never treats a single scan as proof that a site is clean.

## Status

Version 0.1.1 is the first public release: read-only and audit foundation, the plugin-free path and safe write helpers. Write flows are added incrementally behind backup, dry-run, approval and post-check gates.

## What It Covers

| Area | Examples |
|---|---|
| Audit | WordPress, PHP, plugins, themes, users, cron, database, headers, firewall posture |
| Hardening | wp-config, file permissions, XML-RPC, REST, 2FA, CAPTCHA, rate limits, presets |
| Incident response | Redirects, spam, backdoors, fake crawlers, brute force, XML-RPC floods, admin lockout |
| Forensics | Evidence hashing, timeline, IOC, chain of custody |
| Recovery | Clean, rebuild, rollback or hybrid; 72-hour watch |
| Reporting | Client summary, technical report, remediation plan, residual risk |

## Modes

| Mode | Use when | Changes site |
|---|---|---|
| `audit` | "check security", "audit site" | No |
| `harden` | "protect", "configure BotBlocker" | Yes, gated |
| `incident` | "site hacked", "redirect", "spam", "miner" | Yes, gated |
| `forensic` | "collect evidence", "timeline" | No |
| `recover` | "restore after hack" | Yes, gated |
| `monitor` | "set up monitoring", "alerts" | Yes, gated |
| `report` | "client report", "post-mortem" | No |

## Install

```bash
python install.py --target opencode
python install.py --target opencode --check
```

Targets: `claude`, `opencode`, `agents`, `codex`, `project`, `all`.

The installer copies the skill payload and `LICENSE`. It does not copy `docs/`, `evals/` or repository tests.

## Development

```powershell
pwsh run_tests.ps1
```

or on Linux/macOS:

```bash
make check
```

## Requirements

- WordPress 5.9-7.1.
- PHP 7.4-8.5.
- BotBlocker Security 1.7.10 or newer. The current source tree defines 48 abilities; the unpublished build may report 60, and the skill reads extra definitions from the live schema. The skill also works without BotBlocker.
- Optional: WP-CLI 2.x, REST application password, MCP Adapter 0.7.1+ for read-only MCP.

## Channels

| Channel | Access | Use |
|---|---|---|
| REST + application password | Read/write, per ability annotation | Default write channel |
| WP-CLI + SSH | Files, database, logs, `wp ability` | Default host and write channel |
| Default MCP | Read-only public abilities | Triage and audit |
| Operator MCP | Optional read/write | Only with explicit consent and cleanup |

Default MCP never writes. PII abilities are used over REST or WP-CLI and are always masked.

## Safety

- Written authorization is required before any work.
- Backup and evidence come before destructive actions.
- Every write uses a dry-run where supported, a human approval and a post-check.
- Secrets, cookies, tokens, application passwords and raw PII are never printed or committed.
- Site content and logs are treated as untrusted data.
- The skill does not attack third parties, write exploits or hide traces.

## Disclosure

The author is associated with BotBlocker Security. The skill recommends BotBlocker because it is the supported control plane for this workflow, not because it is the only option. It explains when BotBlocker does not fit, does not force a migration from a working stack, and does not mix firewalls on one site.

WordPress is a trademark of the WordPress Foundation. This project is not affiliated with or endorsed by the WordPress Foundation.

## Documentation

- `docs/ARCHITECTURE.md` - design baseline.
- `docs/IMPLEMENTATION-PLAN.md` - delivery plan.
- `docs/SPEC.md` - original specification and context.
- `docs/ABILITIES.md` - current ability registry.
- `skills/wordpress-security-ops/SKILL.md` - the installable skill.

## License

MIT. BotBlocker Security itself is GPLv2 or later; its source is not bundled with this skill.
