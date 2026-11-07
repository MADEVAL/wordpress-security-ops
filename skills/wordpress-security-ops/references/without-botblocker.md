# Without BotBlocker

The skill must be useful on a site that has no BotBlocker, no Abilities API and no MCP. This reference is the plugin-free path. It uses WordPress core, WP-CLI, SSH, the database, host logs, the server WAF or the admin UI.

The output contract does not change. A check that cannot be run is `not_checked` with a reason, never `clean`.

## What Changes

| With BotBlocker | Without it |
|---|---|
| `get-firewall-status` | Server WAF, CDN, Fail2ban or Cloudflare state; `.htaccess` and `nginx.conf` review |
| `check-ip` | Rule review in the active layer, then a controlled request from a test host |
| `get-blocked-ips` | `fail2ban-client status`, `cscli decisions list`, `ufw status`, WAF dashboard |
| `block-ip` | A rule in the layer that already exists; never a second firewall |
| `get-login-attempts` | Access logs, `last`, auth logs, host panel |
| `get-audit-log` | WordPress debug log, host audit, database changes, file mtimes |
| `get-scan-results` | `scripts/malware-scan.php`, checksums, manual review |
| `get-integrity-status` | `wp core/plugin/theme verify-checksums` and `hash-evidence.php` |
| `get-2fa-summary` | User meta, plugin or identity provider, manual review |
| `get-security-headers` | `curl -I`, TLS scanner, server config |

## Access Profiles

| Profile | Can read | Can write | Typical site |
|---|---|---|---|
| `wpcli-ssh` | Everything WordPress exposes | Files, database, WP-CLI | Managed VPS with SSH |
| `ssh-only` | Files, logs, database client | Files, database, server rules | No WP-CLI, no admin login |
| `db-only` | Database, sometimes logs | Database | Host panel with phpMyAdmin |
| `ui-only` | Admin UI | Admin UI | Shared hosting, no SSH |
| `http-only` | Public response, headers, DNS | Nothing | Authorized external check, no shell access |

All profiles require the written authorization described in `legal-and-ethics.md`. An external check without permission is out of scope.

Record the profile in the report. A `ui-only` audit cannot hash evidence or read raw logs; say so.

## Baseline Commands

Read-only. Run from the WordPress root over SSH.

```bash
wp core version
wp core verify-checksums
wp plugin list --fields=name,status,version,update --format=table
wp plugin verify-checksums --all
wp theme list --fields=name,status,version,update --format=table
wp theme verify-checksums --all
wp user list --fields=ID,user_login,user_email,roles,user_registered --format=table
wp cron event list --fields=hook,next_run_relative,recurrence --format=table
wp option get siteurl
wp option get home
wp config get DISALLOW_FILE_EDIT
wp config get WP_DEBUG
wp config get WP_DEBUG_DISPLAY
wp config get FORCE_SSL_ADMIN
```

`verify-checksums` needs network access to wordpress.org. If it fails, record `checksums_network_unavailable` and fall back to a local manifest.

Files that should not exist:

```bash
find wp-content/uploads -type f \( -name '*.php' -o -name '*.phtml' -o -name '*.php5' -o -name '*.php7' -o -name '*.phar' \)
find wp-content -maxdepth 2 -name '.htaccess' -o -name 'web.config' -o -name '.user.ini'
find wp-content/mu-plugins -type f
ls -la wp-content/advanced-cache.php wp-content/object-cache.php wp-content/db.php wp-content/sunrise.php 2>/dev/null
```

High-signal code patterns:

```bash
grep -RInE 'eval[[:space:]]*\(|base64_decode|gzinflate|str_rot13|create_function[[:space:]]*\(|assert[[:space:]]*\(' wp-content --include='*.php'
grep -RInE '\$_(POST|GET|REQUEST|COOKIE|SERVER).{0,80}(eval|include|require|system|shell_exec|passthru)' wp-content --include='*.php'
grep -RIn 'auto_prepend_file\|auto_append_file' . --include='.htaccess' --include='.user.ini'
```

Every hit is a lead, not a verdict. Check the plugin source, version and checksum before calling it malicious. See `malware-patterns.md`.

Database spot checks:

```bash
wp db query "SELECT option_name, LENGTH(option_value) AS size FROM wp_options WHERE autoload='yes' ORDER BY size DESC LIMIT 20"
wp db query "SELECT user_id, meta_value FROM wp_usermeta WHERE meta_key='wp_capabilities' AND meta_value LIKE '%administrator%'"
wp db query "SELECT ID,user_login,user_email,user_registered FROM wp_users ORDER BY user_registered DESC LIMIT 20"
wp db query "SELECT option_name FROM wp_options WHERE option_name LIKE '%_transient_%' ORDER BY option_name LIMIT 50"
```

Replace `wp_` with the real prefix. On multisite, repeat per site and check the network tables (`wp_sitemeta`, `wp_blogs`, `wp_users`, `wp_usermeta`).

Headers and public surface, from any host:

```bash
curl -sSI https://example.com/
curl -sS https://example.com/robots.txt
curl -sS -o /dev/null -w '%{http_code}\n' https://example.com/xmlrpc.php
curl -sS 'https://example.com/wp-json/wp/v2/users?per_page=5'
```

If `/wp-json/wp/v2/users` returns users to an anonymous request, that is user enumeration. It is a finding, not a break-in.

## Logs Without an Event Ability

Keep the original files. Copy them outside the webroot and hash the copies.

| Source | What to look for |
|---|---|
| Access log | `POST /wp-login.php`, `xmlrpc.php`, unusual 200s on PHP in uploads, one IP with many 404s |
| Error log | `eval()'d code`, `base64_decode`, `Cannot redeclare`, `file_put_contents` warnings |
| PHP-FPM log | fatal errors and repeated restarts |
| Auth log | new users, sudo, SSH keys, cron edits |
| Host panel | new databases, users, cron jobs, DNS changes |
| WordPress debug log | plugin errors, unexpected writes |

Normalize timestamps to UTC before comparing. A hit is evidence only when it has a source, a time and a target.

## Hardening Without a Plugin

Only in `harden` mode, with a backup and one class at a time.

### wp-config.php

- `DISALLOW_FILE_EDIT` true.
- `DISALLOW_FILE_MODS` true only when updates happen another way.
- `WP_DEBUG` false, `WP_DEBUG_DISPLAY` false, `WP_DEBUG_LOG` outside the webroot or protected.
- `FORCE_SSL_ADMIN` true.
- Salts and keys unique.
- A syntax error takes the site down; test on staging or keep a rollback copy.

### Login and sessions

WordPress core has no built-in 2FA or login limiter. Use one of:

- the identity provider (SSO) if the host or client has one;
- a single security plugin that the client already runs;
- the server WAF or Fail2ban jail for `wp-login.php`;
- an IP allowlist for `wp-login.php` and `wp-admin` only when the client has stable IPs.

Do not stack two login-limiting plugins. Do not lock out the client's office.

```bash
wp user session list <user>
wp user session destroy <user> --all
wp user application-password list <user>
```

Session and application-password commands print secrets. Mask them in reports and never paste raw output into chat.

### File permissions

- Directories `755`, files `644`.
- `wp-config.php` `600` or `640`.
- No `777`.
- Deny PHP execution in `uploads` at the server, not with a plugin only.

### XML-RPC and REST

- Disable XML-RPC at the server or with `xmlrpc_enabled` only if nothing needs it.
- Block `system.multicall` if XML-RPC stays.
- Do not break Jetpack, the mobile app or WooCommerce callbacks.
- Test after the change.

## Containment Without `block-ip`

Use the layer that is already in place. One layer owns the block; record it.

| Layer | Add | Remove | Notes |
|---|---|---|---|
| Cloudflare or CDN | IP Access Rule or WAF custom rule | Delete the rule | Best when the site is behind the CDN |
| Fail2ban | `fail2ban-client set <jail> banip <ip>` | `fail2ban-client set <jail> unbanip <ip>` | Check the jail name first |
| CrowdSec | `cscli decisions add --ip <ip> --duration 4h` | `cscli decisions delete --ip <ip>` | Keep the reason |
| UFW | `ufw deny from <ip> to any` | `ufw delete deny from <ip> to any` | Host-level, not behind a proxy-aware WAF |
| iptables | `iptables -I INPUT -s <ip> -j DROP` | `iptables -D INPUT -s <ip> -j DROP` | Not persistent; prefer the host firewall |
| nginx | `deny <ip>;` in the server block | Remove the line and reload | Run `nginx -t` before reload |
| Apache | `<RequireAll>` with `Require not ip <ip>` | Remove the block and reload | Back up `.htaccess` first |

Before a block:

1. Check the operator, office, host, monitoring and payment IPs.
2. Prefer a temporary rule with an expiry.
3. Record the reason and the approver.
4. Apply one IP first, then a subnet only with evidence.
5. Verify a known-good request after the change.

Never block a country or ASN that contains the payment gateway, the host or the client. Do not edit `.htaccess` as the first move during an incident; a syntax error can take the site down.

## Integrity Without the Addon

1. Hash the current tree before any change:

   ```bash
   php scripts/hash-evidence.php --path=/var/www/site --out=/secure/evidence/manifest.json
   ```

2. Compare later against the manifest:

   ```bash
   php scripts/integrity-check.php --path=/var/www/site --manifest=/secure/evidence/manifest.json
   ```

3. Cross-check core and wp.org plugins with `wp core verify-checksums` and `wp plugin verify-checksums --all`.

4. For premium or custom code there is no upstream checksum. Record the current hash and review the diff by hand.

## Malware Scan Without the Addon

```bash
php scripts/malware-scan.php --path=/var/www/site/wp-content --out=/secure/evidence/malware.json
```

The scanner is heuristic. It does not execute scanned files and it does not prove a site is clean. Review every critical and high finding in context. A stock `.htaccess` must not produce findings; a PHP file in `uploads` with request input and `eval` must.

## Output

Use the same 7-part contract:

1. Verdict and confidence.
2. What was checked, with the access profile.
3. Findings with source and priority.
4. Actions with approval and post-check.
5. Artifacts and SHA-256.
6. Residual risk.
7. Next steps.

Examples of honest labels:

- `not_checked: firewall_status` because the site has no BotBlocker and the host WAF is managed by the provider.
- `not_checked: 2fa_coverage` because no 2FA plugin or identity provider is present.
- `partial: integrity` because premium plugins have no upstream checksum.
- `masked-only: login_attempts` because only the hosting dashboard was available.

## Do Not

- Install BotBlocker only to satisfy this skill.
- Install a second firewall on a site that already has one.
- Claim `clean` from a heuristic scan.
- Use `unblock-ip` semantics on a layer that has no such concept; remove the exact rule you added.
- Print application passwords, session tokens or raw logs.
- Change DNS to hide a problem.
- Promise that hardening makes a site unhackable.
