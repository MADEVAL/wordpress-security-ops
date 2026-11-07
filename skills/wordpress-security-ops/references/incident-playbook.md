# Incident Playbook

Use in `incident` mode. The goal is to stop the attack, preserve evidence, remove persistence, restore trust and explain the result.

## 0. Entry

- [ ] Written authorization and owner.
- [ ] Symptoms: redirect, spam, malware warning, miner, defacement, login failure, traffic drop.
- [ ] Scope: one site or network, one domain or several.
- [ ] SPOC and escalation contact.
- [ ] Maintenance window and communication cadence.
- [ ] Stop if the site is not the client's.

## 1. Stabilize

- [ ] Backup files and database; verify restore.
- [ ] Snapshot the current state.
- [ ] Preserve web-server, PHP and BotBlocker logs.
- [ ] Hash evidence before changes.
- [ ] Restrict admin access if needed.
- [ ] Do not clean yet.

Gate: backup and evidence manifest exist.

## 2. Identify

Build a timeline in UTC. Merge the sources below with `scripts/build-timeline.php`; keep raw log lines as evidence and normalize only the working copy.

Sources:

- `get-event-details` for IP, ASN, country, UA, path, action and reason.
- `get-top-ips` for attacker concentration.
- `get-login-attempts` for brute force and successful logins.
- `get-visitor-log` for request patterns.
- `get-user-risk` for new, stale or risky admins.
- `get-audit-log` for settings and ability changes.
- `get-ability-calls` for ability use and refusals.
- `get-integrity-status` for modified, added and missing files.
- `get-scan-status` and `get-scan-results` for malware.
- `get-cron-jobs` for unknown hooks.
- `get-db-health` for database anomalies.
- Host logs for raw request lines.

Questions:

- When did it start?
- How was it detected?
- Which files, posts, options, users or cron hooks changed?
- Was admin or super admin access obtained?
- Is the database or a backup after the first IOC compromised?
- Is the site blacklisted?
- Is customer data affected?

Output: `iocs.md`, `timeline.csv`, initial findings.

## 3. Contain

Choose the narrowest action.

| Situation | Action |
|---|---|
| Single attacker IP | `block-ip` with reason and expiry |
| Subnet or ASN | `upsert-ip-rule` or `set-asn-rule` after checking good IPs |
| Country-based attack | `set-country-rule` with office and payment exceptions |
| Vulnerable plugin | Deactivate it; do not delete before evidence |
| Compromised admin | Reset password, revoke sessions, force 2FA |
| Active checkout fraud | Add payment gateway rules and monitor |
| Site defaced | Maintenance mode after backup |

Before any write:

- [ ] Dry-run.
- [ ] Show diff and previous rules.
- [ ] Human approval.
- [ ] Apply with the exact confirm token.
- [ ] `check-ip` the good and bad IPs.
- [ ] Verify login, checkout and forms.

Do not disable protection permanently. Use a time-limited pause only if the product supports it.

## 4. Eradicate

Remove persistence, not just symptoms.

- [ ] Malware files, with evidence copies.
- [ ] Unknown admin users and sessions.
- [ ] Unknown cron hooks.
- [ ] Malicious options and transients.
- [ ] Malicious mu-plugins and drop-ins.
- [ ] Modified core, plugin and theme files; reinstall from trusted sources.
- [ ] Unknown application passwords.
- [ ] Weak or reused passwords.
- [ ] Unused plugins and themes.

If admin or super admin was compromised:

- Treat the database and post-IOC backups as compromised.
- Rebuild or restore from a pre-IOC clean backup.
- Rotate salts, keys, API tokens and application passwords.
- Reissue 2FA backup codes.
- Review all users, roles and sessions.

Gate: evidence preserved before each class of deletion.

## 5. Recover

Choose one:

| Path | When |
|---|---|
| Clean | Limited scope, clean pre-IOC backup, core intact, no admin compromise |
| Rebuild | Core, database or many files affected; unknown vector; admin compromise |
| Rollback | Verified backup before the incident and acceptable data loss |
| Hybrid | Clean core and plugins plus content imported from a checked database |

After recovery:

- [ ] New salts and keys.
- [ ] 2FA for all admins.
- [ ] Remove unknown users and sessions.
- [ ] Verify cron and mu-plugins.
- [ ] Malware scan.
- [ ] Integrity check.
- [ ] Checkout, forms and login.
- [ ] Blacklist check.
- [ ] 72-hour watch.

## 6. Verify

- [ ] Core and plugin checksums clean.
- [ ] No unknown admins, cron, mu-plugins or drop-ins.
- [ ] No PHP in uploads or suspicious files.
- [ ] Database has no known IOC.
- [ ] Login, checkout and forms work.
- [ ] Blacklist and SEO checked.
- [ ] No repeat IOC for 72 hours.
- [ ] Client received the report and residual risk.

## 7. Report

Use `templates/incident-report.md` and `templates/client-summary.md`.

Include:

- What happened, in plain language.
- Timeline and IOC.
- What was affected, including data.
- What was done, with approval and evidence.
- What remains and the residual risk.
- 30/90/365 plan.
- Legal or privacy escalation, if needed.

## Communication

- One SPOC on each side.
- Updates every 2-4 hours during active response.
- Escalate immediately for PII leak, checkout outage, blacklist or regulator involvement.
- Do not notify a regulator or third party without the responsible owner.
- Final status in writing.

## Stop Conditions

- No authorization.
- Request to hide traces or bypass detection.
- Request to attack a third party.
- PII leak without a notification owner.
- Evidence with third-party data and no legal basis.
- Request to disable protection permanently without a plan.
