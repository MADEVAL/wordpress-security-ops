# Hardening Baseline

Apply only in `harden` mode, with a backup, a plan and a rollback. Change one class at a time and verify login, checkout and forms after each class.

## Before Any Change

- [ ] Backup files and database; verify restore.
- [ ] `export-settings` with redaction.
- [ ] `get-protection-snapshot`.
- [ ] List current IP, country and ASN rules.
- [ ] Record admin, payment, monitoring, hosting and CDN IPs.
- [ ] Confirm maintenance window and SPOC.
- [ ] Stage the change where possible.

## wp-config.php

| Setting | Recommended | Notes |
|---|---|---|
| `DISALLOW_FILE_EDIT` | `true` | Blocks the theme and plugin editor |
| `DISALLOW_FILE_MODS` | `true` on production | Blocks installs and updates; plan updates separately |
| `WP_DEBUG` | `false` on production | Use staging for debugging |
| `WP_DEBUG_DISPLAY` | `false` | Never show errors publicly |
| `WP_DEBUG_LOG` | `true` if needed | Store outside webroot or protect it |
| `DISABLE_WP_CRON` | `true` with a real cron | Only if a server cron exists |
| `FORCE_SSL_ADMIN` | `true` | Requires valid HTTPS |
| `WP_AUTO_UPDATE_CORE` | `minor` or `true` | Match the client policy |
| `AUTOMATIC_UPDATER_DISABLED` | `false` unless controlled | Do not silently stop updates |

Rules:

- Do not print the file or its secrets.
- Change one constant at a time.
- Keep a copy outside the webroot.
- A syntax error can take the site down; use a staging test or a rollback path.

## File Permissions

| Path | Typical |
|---|---|
| Directories | `755` |
| Files | `644` |
| `wp-config.php` | `600` or `640` |
| `uploads` | `755`, no PHP execution |
| `.htaccess` | `644` |

- Do not use `777`.
- Check owner and group.
- Deny PHP execution in `uploads`.
- Remove unused themes and plugins after backup.

## XML-RPC and REST

- Disable XML-RPC if not needed, or allowlist the IPs that need it.
- Block `system.multicall` if XML-RPC stays.
- Disable REST user enumeration for anonymous users if the site does not need it.
- Do not break the block editor, WooCommerce or mobile apps.
- Test after the change.

## Authentication

- Enforce 2FA for administrators and editors.
- Provide backup codes before enforcement.
- Set a grace period.
- Limit login attempts with progressive lockout.
- Add CAPTCHA to login, registration and lost password.
- Use a custom login URL only if the team can maintain it.
- Remove unused accounts and stale administrators.
- Rotate application passwords after an incident.

## Firewall and Bots

- Start with Light or Strong; use Full only when the site tolerates it.
- Enable UA signatures, fake crawler detection, ASN and country rules where needed.
- Set rate limits: CAPTCHA first, block second.
- Keep payment gateway, hosting, monitoring and admin IPs in the allowlist.
- Use a temporary rule with expiry for incident containment.
- Check `check-ip` before and after.
- Do not block a country or ASN that contains the payment gateway or host.
- Test checkout, forms and login.

## Security Headers

| Header | Starting point |
|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` after HTTPS is stable |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` or CSP `frame-ancestors` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Disable unused features |
| `Content-Security-Policy` | Start with report-only; allow blob workers for media and emoji loaders |

Do not deploy a strict CSP blind. A broken CSP can break checkout, media and admin. Use report-only first.

## Database

- Clean expired transients.
- Remove orphan options only after a backup.
- Keep autoload small.
- Repair and optimize in a maintenance window.
- Do not run `UPDATE`, `DELETE` or `DROP` without a backup and approval.

## Backups

- Files, database, uploads and configs.
- Offsite and immutable where possible.
- Encryption and access control.
- Retention matched to the client policy.
- Restore test, not just a green check.
- Backup before the first IOC, not after.

## Updates

- Core, plugins and themes from trusted sources.
- Remove nulled or unknown premium code.
- Pin versions only with a patch plan.
- Test on staging.
- Keep a rollback.
- Check compatibility with PHP and WordPress.

## Verification After Each Class

- [ ] Login as admin and as a normal user.
- [ ] Checkout and payment callback.
- [ ] Contact and registration forms.
- [ ] REST and XML-RPC, if used.
- [ ] Cron runs.
- [ ] Cache clears.
- [ ] No 5xx spike.
- [ ] `check-ip` for known-good IPs.
- [ ] Rollback ready.

## Hardening Diff

Record:

```text
before: <value>
after: <value>
ability or file: <source>
approved by: <person>
verified by: <check>
rollback: <command or ability>
```

## Do Not

- Disable protection permanently without a window and plan.
- Block the office, host, monitoring or payment IP.
- Deploy a strict CSP without report-only testing.
- Use `777` permissions.
- Delete files before evidence.
- Mix two firewalls on one site.
- Promise that hardening makes the site unhackable.
