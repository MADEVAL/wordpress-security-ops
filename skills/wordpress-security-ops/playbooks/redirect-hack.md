# Redirect Hack

Without BotBlocker, use `grep`, checksums and host logs as described in `../references/without-botblocker.md`.

## Symptoms

- Mobile visitors redirect to spam, phishing or gambling.
- Search results show unrelated titles or snippets.
- Traffic from Google or Yandex drops.
- Users report a warning page.
- The redirect appears only for certain user agents, referers or countries.

## First Checks

- [ ] Reproduce with mobile and desktop user agents.
- [ ] Check `home` and `siteurl`.
- [ ] Check `.htaccess`, `web.config`, `index.php` and mu-plugins.
- [ ] Check `wp_options` for injected scripts or URLs.
- [ ] Check posts and post meta for iframes, `display:none`, `unescape`, `eval`.
- [ ] Check theme files, especially `functions.php`, `header.php`, `footer.php`.
- [ ] Check JavaScript files in uploads and themes.
- [ ] Check DNS and CDN settings.
- [ ] `get-integrity-status` or checksums.
- [ ] `get-scan-status` and `get-scan-results` if the addon is active.

## Containment

- Preserve evidence and hashes first.
- Block the attacker IP if confirmed.
- Put the site in maintenance mode if visitors are being exposed to phishing.
- Remove the redirect only after the evidence copy exists.
- Do not disable the firewall.

## Eradication

- Remove malicious code from files, options, posts and meta.
- Reinstall modified core, plugin and theme files from trusted sources.
- Remove unknown users, cron and mu-plugins.
- Check for a payload in `wp-config.php` above `ABSPATH`.
- Check for a malicious drop-in.
- Rotate salts and keys if admin access is suspected.

## Recovery

- Restore from a pre-IOC backup or rebuild.
- Verify `home`, `siteurl`, permalinks and redirects.
- Check search console and blacklist.
- Request a review only after the cause is removed.
- 72-hour watch.

## Verification

- [ ] Mobile and desktop requests go to the correct destination.
- [ ] No injected script in the database or theme.
- [ ] Core and plugin checksums clean.
- [ ] Search Console and Yandex show no security issue.
- [ ] No new redirect after 72 hours.

## Stop Conditions

- The redirect is at the DNS or CDN layer and the client does not control it.
- The site is phishing or malware distribution and the host must be involved.
- The client asks to hide the redirect instead of removing it.
