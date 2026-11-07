# Monitoring and Alerts

Use in `monitor` mode. A monitoring setup is done when an alert actually arrives.

## 1. Baseline

- [ ] BotBlocker dashboard and Health Score.
- [ ] Firewall mode and rule count.
- [ ] Normal traffic, blocks and CAPTCHA rate.
- [ ] Admin users and 2FA coverage.
- [ ] Cron and integrity status.
- [ ] Backup schedule and last restore test.

## 2. Alerts

| Signal | Starting threshold |
|---|---|
| Blocked requests | Spike over the daily baseline |
| New administrator | Any |
| File change | Any unexpected core, plugin or theme change |
| Integrity change | Any modified or added file |
| Malware finding | Any high or critical |
| Failed login | Spike by IP, ASN or country |
| 5xx | Sustained increase |
| Traffic drop | Sudden drop with no release |
| Redeploy failure | Any |
| Blacklist | Any |

Tune after one week to avoid fatigue. Keep a record of every threshold change.

## 3. Channels

- Dashboard notifications card.
- Email digest.
- Telegram bot.
- Host or CDN alerts.
- Uptime monitor.
- Search Console and Safe Browsing.

## 4. Telegram

- Use a dedicated bot and chat.
- Mask the token in settings and in screenshots.
- Send a test alert.
- Confirm who receives it.
- Rotate the token if it leaks.
- Do not put raw PII in the alert text.

## 5. Notifications Card and Bell

- The dashboard Notifications card shows PRO tips and alerts.
- The menu badge counts alerts, not addon updates.
- A failed addon redeploy raises an alert.
- Confirm the bell badge matches the alert count after a test.

## 6. Security Headers

- If CSP is enabled, allow blob workers needed by media and emoji loaders.
- Test the admin, checkout and media library after a CSP change.
- Start with report-only.
- Do not deploy a strict CSP blind.

## 7. Test the Setup

- [ ] Trigger a test alert.
- [ ] Confirm delivery and recipient.
- [ ] Confirm the notification card and bell.
- [ ] Confirm the audit log records the test.
- [ ] Confirm the alert does not expose PII or secrets.
- [ ] Document the runbook.

## 8. Runbook

| Situation | First action | Owner |
|---|---|---|
| Blocked spike | Check `check-ip` for known-good IPs; review rules | [owner] |
| New admin | Verify with the client; disable if unknown | [owner] |
| File change | Check integrity and evidence; do not delete | [owner] |
| Malware finding | Preserve evidence; scan; follow incident playbook | [owner] |
| 5xx | Check PHP and server logs; roll back last change | [owner] |
| Traffic drop | Check DNS, CDN, firewall and search console | [owner] |
| Redeploy failure | Check addon status and logs; retry after fix | [owner] |

## 9. Without BotBlocker

Use the layers that already exist:

| Signal | Source |
|---|---|
| Site down or 5xx | Uptime monitor, host panel |
| Blocked or challenged requests | CDN/WAF dashboard, Fail2ban or CrowdSec |
| New administrator | WordPress activity log, database check, `wp user list` |
| Failed login spike | Access log, auth log, host panel |
| File change | `hash-evidence.php` plus `integrity-check.php` on a schedule |
| Malware finding | `malware-scan.php` on a schedule, reviewed weekly |
| Traffic drop | Analytics, DNS and CDN status |
| Blacklist | Search Console, Safe Browsing |

Example daily evidence job:

```cron
15 3 * * * php /opt/wordpress-security-ops/scripts/malware-scan.php --path=/var/www/site/wp-content --out=/secure/evidence/malware-$(date +\%F).json
30 3 * * * php /opt/wordpress-security-ops/scripts/integrity-check.php --path=/var/www/site --manifest=/secure/evidence/manifest.json --out=/secure/evidence/integrity-$(date +\%F).json
```

Send the output to a monitored address or webhook. A file nobody reads is not monitoring. Confirm the alert actually arrives and does not expose PII or secrets.

## 10. Review

- Weekly: alert volume and false positives.
- Monthly: thresholds, 2FA coverage, backup restore.
- Quarterly: test alert, token rotation, runbook review.
- After an incident: add a signal or threshold that would have caught it earlier.
