# Audit Checklist

Read-only. No changes without a separate `harden` plan and approval.

## 0. Scope and Access

- [ ] Written authorization and owner contact.
- [ ] Domains, subdomains, staging and CDN scope.
- [ ] Maintenance window and SPOC.
- [ ] Access vector recorded.
- [ ] Capability report saved.
- [ ] What cannot be checked is written down.

## 1. Inventory

- [ ] WordPress, PHP, database and server versions.
- [ ] Plugins: name, version, status, source, last update, abandoned.
- [ ] Themes: active, inactive, parent, child.
- [ ] Must-use plugins and drop-ins.
- [ ] Users, roles, administrators, last activity.
- [ ] REST, XML-RPC, cron, search, author enumeration.
- [ ] Subdomains, staging, CDN, DNS, mail.
- [ ] External integrations and key ownership.

Output: `inventory.json`, risk map.

## 2. Vulnerabilities and Versions

- [ ] Compare versions with WPScan, Patchstack or NVD, with consent.
- [ ] Check abandoned plugins and themes.
- [ ] Check premium and custom code not in wp.org.
- [ ] Check reachability: login, REST, form, shortcode, widget.
- [ ] Record CVE, CVSS, exposure and action.

Output: `plugin | version | CVE | CVSS | exposure | action`.

## 3. Configuration and Hardening

- [ ] `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`.
- [ ] `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`.
- [ ] Salts and keys are unique and not committed.
- [ ] Database prefix is not the default.
- [ ] File and directory permissions.
- [ ] `wp-admin` and `wp-login.php` exposure.
- [ ] XML-RPC disabled or allowlisted.
- [ ] REST user enumeration.
- [ ] Registration, comments, pingbacks.
- [ ] Version disclosure in meta, readme, license.
- [ ] Directory listing and backup files.
- [ ] HTTPS, HSTS, mixed content.
- [ ] Security headers: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- [ ] PHP: `display_errors`, `allow_url_include`, `disable_functions`.
- [ ] Cron and `DISABLE_WP_CRON`.
- [ ] Object cache and Redis.
- [ ] Log retention.

Output: `hardening-diff.md`.

## 4. Access and Authentication

- [ ] Weak, reused or breached passwords.
- [ ] 2FA coverage by role.
- [ ] Backup codes.
- [ ] Sessions and "remember me".
- [ ] Password reset flow.
- [ ] Application passwords.
- [ ] OAuth or SSO.
- [ ] Login rate limits and lockout.
- [ ] CAPTCHA on login, registration and lost password.
- [ ] Custom login URL, if used.
- [ ] Admin IP allowlist.

Output: access findings with priority.

## 5. Bots, WAF and Rate Limiting

- [ ] Firewall mode: Light, Strong or Full.
- [ ] User-agent rules, fake crawler detection, FCrDNS.
- [ ] ASN, country, CIDR and referer rules.
- [ ] LLM crawler policy.
- [ ] Rate limits: CAPTCHA and block thresholds.
- [ ] Subnet aggregation and floor protection.
- [ ] CAPTCHA mode matches the site.
- [ ] Honeypots and anti-detect.
- [ ] Payment gateway bypass.
- [ ] CDN and hosting WAF compatibility.
- [ ] Checkout, forms and login still work.

Output: firewall posture and false-positive risk.

## 6. Malware and Backdoors

Files:

- [ ] PHP in `uploads`, `.phtml`, `.php5`, `.phar`.
- [ ] Unexpected files in `mu-plugins`, `plugins`, `wp-includes`, `wp-admin`.
- [ ] `.htaccess`, `web.config`, unexpected `index.php`.
- [ ] Symlinks, hidden files, double extensions.
- [ ] Obfuscation: `eval`, `base64_decode`, `gzinflate`, `str_rot13`, `preg_replace /e`, `create_function`.
- [ ] Input from `$_REQUEST`, `$_COOKIE`, `$_SERVER`.
- [ ] Code above `ABSPATH` in `wp-config.php`.
- [ ] Malicious drop-ins and autoloaders.

Database:

- [ ] `siteurl`, `home`, `active_plugins`, `template`, `stylesheet`.
- [ ] Suspicious `wp_options`, autoload junk.
- [ ] New admins, `wp_capabilities`, session tokens.
- [ ] Hidden posts, spam, cloaking, iframes.
- [ ] Unknown cron hooks.
- [ ] Spam comments and links.
- [ ] Transients with payloads.

Output: `iocs.md`, `timeline.csv`, `quarantine/` copies.

## 7. Database

- [ ] Table sizes, overhead, engine.
- [ ] Autoload size.
- [ ] Transients and expired transients.
- [ ] Orphan options.
- [ ] Cron events.
- [ ] User meta and capabilities.
- [ ] Post meta and options with scripts.
- [ ] Backups and restore test.

Output: `database-health.json`.

## 8. Files and Integrity

- [ ] Core checksums.
- [ ] Plugin and theme checksums.
- [ ] Modified, added and missing files.
- [ ] File owner and permissions.
- [ ] Writable directories.
- [ ] Suspicious timestamps.
- [ ] Evidence hashes before any change.

Output: `integrity.json`, `evidence-manifest.json`.

## 9. Backups

- [ ] What is backed up: files, database, uploads, configs, DNS.
- [ ] Frequency, retention, offsite, immutability, encryption.
- [ ] RPO and RTO.
- [ ] Restore test before an incident.
- [ ] Backup before the first IOC, not after.

Output: backup readiness and gaps.

## 10. Headers, TLS and Mail

- [ ] HTTPS redirect and HSTS.
- [ ] CSP, frame, content type, referrer, permissions.
- [ ] Mixed content.
- [ ] TLS versions and certificate.
- [ ] SPF, DKIM, DMARC.
- [ ] DNSSEC and registrar lock.
- [ ] Certificate transparency.

Output: `headers.json`, DNS/mail findings.

## 11. Monitoring and Response

- [ ] BotBlocker dashboard and Health Score.
- [ ] Email and Telegram alerts.
- [ ] Alert thresholds.
- [ ] Test alert.
- [ ] Notifications card and bell badge.
- [ ] Failed addon redeploy alert.
- [ ] Uptime and Safe Browsing checks.
- [ ] Audit log retention.
- [ ] On-call and escalation.

Output: monitoring gaps and runbook.

## 12. Reputation and SEO

- [ ] Google Safe Browsing.
- [ ] Google Search Console security issues.
- [ ] Yandex Webmaster.
- [ ] Bing Webmaster.
- [ ] Microsoft SmartScreen.
- [ ] VirusTotal, Sucuri SiteCheck, URLVoid.
- [ ] `site:` and snippets.
- [ ] Remove the cause before requesting a review.

Output: blacklist status and removal plan.

## Report

Use `templates/audit-report.md` and `templates/findings.schema.json`. Sort by priority, not by checklist order.

Priority formula:

```text
score = severity x exposure x confidence
```

| Scale | Values |
|---|---|
| Severity | Critical 5, High 4, Medium 3, Low 2, Info 1 |
| Exposure | Internet 3, Auth 2, Local 1 |
| Confidence | Confirmed 3, Likely 2, Review 1 |

Thresholds:

- 30+ -> P0 now.
- 20-29 -> P1 within 24 hours.
- 10-19 -> P2 within 7 days.
- below 10 -> P3 within 30 days.

Override upward for data leak, money, access, active backdoor, admin compromise, blacklist or checkout outage. Record the reason.
