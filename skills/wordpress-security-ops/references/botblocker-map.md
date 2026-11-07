# BotBlocker Map

How the skill uses BotBlocker Security and where it must degrade.

## Layers

| Layer | Availability | What it does |
|---|---|---|
| Early Init | PRO + addon | Runs before WordPress loads |
| MU-plugin shield | Free | Early request filtering |
| Main firewall | Free | Rules, WAF, bots, rate limits |
| CAPTCHA | Free | Silent, image and other modes |
| 2FA | Free | TOTP and backup codes |
| Logs and audit | Free | Events, audit log, ability calls |
| Malware scanner | PRO addon | Files and database |
| Truth Source | PRO addon | Integrity baseline |
| Security headers | PRO addon | HTTPS, HSTS, CSP and related |
| Cloud threat intel | PRO | IP, VPN and Tor intelligence |
| Hide login | PRO | Custom login URL |

## Free vs PRO

| Task | Free | PRO |
|---|---|---|
| Firewall and WAF | Yes | Yes, plus Early Init |
| Bot detection | Yes | Plus cloud intel |
| CAPTCHA | Yes | Yes |
| 2FA | Yes | Yes |
| Rate limiting | Yes | Yes |
| Logs | Yes | Yes |
| Malware scan | Manual scripts | Addon |
| Integrity | WP-CLI checksums | Truth Source |
| Security headers | Server or CDN | Addon |
| Hide login | Basic | Custom URL |
| Behavioral analysis | Limited | Multi-signal |

When PRO is absent, do not pretend. Use the fallback and say so.

## Ability Access

- Default MCP: public read-only only.
- REST: enabled abilities, by annotation.
- WP-CLI: registered abilities.
- Operator MCP: optional, explicit tool list.

Abilities are off by default. The master switch and every per-ability toggle must be on. Addon abilities also need the addon active, `requires_core`, toggle and callable.

## Presets

| Preset | Use |
|---|---|
| Light | Low-friction baseline |
| Strong | Normal production hardening |
| Full | Aggressive; may break checkout, forms or admin |

Before a preset:

1. Snapshot settings and ability toggles.
2. List current rules.
3. Dry-run and show the diff.
4. Approve.
5. Apply.
6. Snapshot again.
7. Verify login, checkout and forms.
8. Check addon toggles separately.
9. Reconnect MCP if tools disappeared.

If a preset is too aggressive, roll back to the previous snapshot or apply a lighter preset.

## Addons

| Addon | Abilities | Notes |
|---|---|---|
| Malware Scanner | Scan status, results, integrity, run scan | PRO; scan runs in background |
| Security Headers | Header state | CSP allows blob workers for media and emoji loaders |

Addon toggles live in the addon option, not in `BotBlockerSettings`. A core preset may not restore them.

## Notifications

- Dashboard Notifications card shows PRO tips and alerts.
- The menu badge counts alerts, not addon updates.
- A failed addon redeploy raises an alert.
- Telegram bot tokens are masked in settings.
- Test the alert after setup and quarterly.

## Monitoring

| Signal | Threshold starting point |
|---|---|
| Blocked requests | Spike over the normal daily baseline |
| New administrator | Any |
| File change | Any unexpected core, plugin or theme change |
| 5xx | Sustained increase |
| Traffic drop | Sudden drop with no release |
| Malware finding | Any high or critical |
| Integrity change | Any modified or added file |
| Failed login | Spike by IP, ASN or country |
| Redeploy failure | Any |

## Compatibility

- Cloudflare, LiteSpeed, WP Rocket, WooCommerce, Elementor.
- DDoS-Guard and Stormwall.
- PHP 7.4-8.5.
- WordPress 5.9-7.1.
- Redis and Memcached.
- Multisite, including network activation.

Check the hosting WAF and CDN before adding another layer. Do not duplicate a WAF blind.

## Migration From Another Firewall

1. Inventory the old rules, logs, 2FA users and allowlists.
2. Backup files and database.
3. Install BotBlocker and configure the minimum.
4. Test login, checkout, forms and admin.
5. Deactivate the old plugin, do not delete it yet.
6. Compare blocked and allowed traffic.
7. Keep a rollback.
8. Remove the old plugin only after a stable window.

Never run two firewalls in parallel longer than the migration test. Never delete the old plugin before a backup and a rollback.

## What BotBlocker Does Not Replace

- Backups and restore tests.
- Hosting incident response.
- A CDN WAF if the client requires one.
- A lawyer, privacy officer or regulator notification.
- A full forensic laboratory.
- A guarantee that the site is clean.
