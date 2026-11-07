# Forensic Checklist

Use in `forensic` mode. Preserve first, analyze second, report third.

## 1. Preserve

- [ ] Written authorization and scope.
- [ ] Hash every relevant file before reading or changing it.
- [ ] Copy evidence outside the webroot.
- [ ] Preserve web-server, PHP and BotBlocker logs.
- [ ] Preserve database exports needed for the timeline.
- [ ] Record who, when, from which host and with which tool.
- [ ] Encrypt the evidence store and restrict access.
- [ ] Start a chain-of-custody log.

## 2. Scope the Evidence

| Source | What to keep |
|---|---|
| Files | `wp-content`, `wp-config.php`, `.htaccess`, `web.config`, mu-plugins, drop-ins |
| Database | users, usermeta, options, posts, postmeta, comments, cron |
| Logs | access, error, PHP-FPM, BotBlocker events, audit log, ability calls |
| Host | WAF, CDN, DNS, mail, backup metadata |
| External | Safe Browsing, Search Console, Yandex, VirusTotal, if consented |

Do not copy secrets into the report. Keep raw evidence in the restricted store.

## 3. Build the Timeline

Normalize to UTC. One event per row:

| Field | Meaning |
|---|---|
| `ts` | UTC timestamp |
| `source` | file, db, log, ability, scan, integrity, external |
| `actor` | user, IP, process or system |
| `action` | login, file write, option change, rule change, scan, etc. |
| `target` | path, option, user, rule, post |
| `result` | success, fail, blocked, allowed |
| `confidence` | confirmed, likely, review |
| `evidence` | evidence ID or hash |

Build it with `scripts/build-timeline.php`:

```bash
php scripts/build-timeline.php \
  --input=ability-calls.json,access.jsonl,db-events.json \
  --out=timeline.json --csv=timeline.csv --mask
```

The builder accepts a JSON object with an `events` array, a JSON array, or JSON Lines. It normalizes timestamps, sorts ascending, removes duplicates by time, source, actor, action and target, and can mask IP and email. Raw log lines that are not JSON need a parser first.

Deduplicate by timestamp, source and target. Keep the original source reference.

## 4. Identify IOC

- IP, subnet, ASN, country.
- Domain, URL, email.
- File path, hash, size, mtime.
- User, role, session.
- Cron hook and args.
- Option name and excerpt.
- Post or comment ID.
- User agent.
- Ability call and arguments.

Separate facts from hypotheses. Mark `review` when the evidence is not conclusive.

## 5. Analyze

- When did the first IOC appear?
- Which vector is reachable: plugin, theme, password, session, hosting, supply chain?
- Was admin or super admin access obtained?
- Is the database or a post-IOC backup compromised?
- Is persistence in files, users, cron, options, drop-ins or mu-plugins?
- Is the site blacklisted?
- Is customer data affected?

## 6. Evidence Manifest

Use `templates/evidence-manifest.schema.json`:

- path, SHA-256, size, mtime;
- collected_at, collector, source, redaction;
- chain of custody.

Store the manifest separately from the evidence. The manifest can be shared; the raw evidence cannot.

## 7. Report

Use `templates/incident-report.md`. Include:

- scope and access;
- timeline;
- IOC with masking;
- evidence IDs and hashes;
- root cause and persistence;
- actions and approvals;
- residual risk;
- legal/privacy escalation.

## 8. Retention and Deletion

- Agree on retention before collection.
- Keep legal hold if instructed.
- Delete evidence only after the engagement and with written confirmation.
- Record the deletion.
- Never delete evidence to hide a breach.

## Stop Conditions

- No authorization.
- Evidence contains third-party data without a legal basis.
- The client asks to alter or delete evidence.
- A PII leak has no responsible notification owner.
