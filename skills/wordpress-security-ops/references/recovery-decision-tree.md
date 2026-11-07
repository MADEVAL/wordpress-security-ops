# Recovery Decision Tree

Use in `recover` mode. The goal is a site the owner can trust, not just a site that loads.

## 1. Determine Scope

| Question | If yes |
|---|---|
| Core files modified? | Rebuild or reinstall core |
| Multiple plugins or themes modified? | Rebuild from trusted sources |
| Database has unknown admins, options or cron? | Clean or rebuild the database |
| Admin or super admin access obtained? | Treat database and post-IOC backups as compromised |
| Unknown vector? | Rebuild is safer than clean |
| One site in a multisite network? | Check shared users, options and uploads |

## 2. Check the Backup

- Is there a backup before the first IOC?
- Was it stored offsite and immutable?
- Has a restore been tested?
- Does it include files, database and uploads?
- Is the backup itself clean?

If the only backup is after the first IOC, do not trust it without a full review.

## 3. Choose the Path

| Path | Use when | Risk |
|---|---|---|
| Clean | Limited scope, clean pre-IOC backup, core intact, no admin compromise | Missed persistence |
| Rebuild | Core, database or many files affected; unknown vector; admin compromise | Downtime and data loss |
| Rollback | Verified pre-IOC backup and acceptable data loss | Lost content or orders |
| Hybrid | Clean core and plugins plus checked content from the database | Hidden database persistence |

## 4. Rebuild

1. Preserve evidence.
2. Provision a clean WordPress core from a trusted source.
3. Reinstall plugins and themes from official or vendor sources.
4. Import only checked content.
5. Create new salts and keys.
6. Create a new admin account, then remove unknown users.
7. Configure BotBlocker, 2FA, rate limits and alerts.
8. Run a malware scan and integrity check.
9. Test login, checkout, forms, REST and XML-RPC.
10. Watch for 72 hours.

## 5. Clean

1. Preserve evidence.
2. Remove known malware and persistence.
3. Reinstall modified core, plugin and theme files.
4. Remove unknown users, cron, options and drop-ins.
5. Rotate keys and passwords.
6. Scan and verify checksums.
7. Test user journeys.
8. Watch for 72 hours.

If new IOC appears during the watch, escalate to rebuild.

## 6. Database

- Remove unknown admins and sessions.
- Remove unknown cron hooks.
- Remove malicious options and transients.
- Check posts, postmeta and comments for injected content.
- Check `siteurl`, `home`, `active_plugins`, `template`, `stylesheet`.
- Use `search-replace` only after a backup and with a dry run.
- Do not run `DROP` or `TRUNCATE` without a backup and approval.

## 7. Verification

- [ ] Core and plugin checksums clean.
- [ ] No unknown admins, cron, mu-plugins or drop-ins.
- [ ] No PHP in uploads or suspicious files.
- [ ] Database has no known IOC.
- [ ] Login, checkout and forms work.
- [ ] Blacklist and SEO checked.
- [ ] No repeat IOC for 72 hours.
- [ ] Client received the report and residual risk.

## 8. Watch

- 72 hours of enhanced monitoring.
- Check new admins, file changes, cron, blocked spikes, 5xx and traffic.
- Re-scan at 24 and 72 hours.
- Close only when the exit criteria pass.

## Stop Conditions

- No clean backup and no rebuild window.
- Admin compromise with no key rotation.
- PII leak without a notification owner.
- The client refuses the minimum recovery steps.
