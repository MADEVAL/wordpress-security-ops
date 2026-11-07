# Changelog

All notable changes to this project are documented here.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [0.1.0] - 2026-11-08

### Added

- Initial architecture and implementation plan.
- Skill scaffold for `wordpress-security-ops`.
- Ability catalog for BotBlocker Security 1.7.10+: 48 definitions (22 core, 23 extended, 3 addon), with confirm, dry_run, risk and addon gates.
- Access model for REST, WP-CLI, default MCP and optional operator MCP.
- Plugin-free path: `references/without-botblocker.md` and the host-only channel in `SKILL.md`.
- PII and evidence handling policy.
- Audit, hardening and incident reference material.
- Report templates and machine-readable schemas.
- Read-only inventory, database, user, cron, evidence and malware helpers.
- `scripts/build-timeline.php` for a deduplicated UTC timeline.
- `tests/test_scripts.py` for the scanner, evidence roundtrip, timeline and option parsing.
- `docs/ABILITIES.md` as a standalone ability registry.
- Design documents moved to `docs/`.

### Fixed

- Ability catalog matches the source tree: 22 core, 23 extended and 3 addon abilities (48 total), with exact confirm tokens.
- `malware-scan.php` no longer flags stock WordPress `.htaccess` rewrite rules, `preg_replace` without `/e`, or `filesystem()` as `system()`.
- WP-CLI helpers now parse their flags after `wp eval-file`, where `getopt()` stops at the first non-option.
- `integrity-check.php` reports a manifest root mismatch and truncation instead of a silent partial result.
- `ability-client.php` decodes SSE MCP responses and marks JSON-RPC errors as failures.
- `db-audit.php` classifies suspicious options and posts by signal instead of a flat list.
- PII key list and ability fallbacks match the executed scripts and the 1.7.10 build.
