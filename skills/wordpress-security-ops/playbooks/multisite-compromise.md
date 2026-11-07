# Multisite Compromise

Without BotBlocker, check shared tables, uploads and network admins as described in `../references/without-botblocker.md`.

## Symptoms

- One site in the network is defaced, redirected or spammed.
- Super admin or network settings changed.
- Shared plugin or theme modified.
- New site created unexpectedly.
- Network-wide rule changed.

## First Checks

- [ ] Confirm network-active vs per-site scope.
- [ ] Check `canManage()` and super admin access.
- [ ] List sites, admins and network admins.
- [ ] Check shared users, usermeta and options.
- [ ] Check network-activated plugins and themes.
- [ ] Check uploads and shared tables.
- [ ] Check network rules and presets.

## Containment

- Preserve evidence from all affected sites.
- Block the attacker IP or ASN at the network level if appropriate.
- Restrict super admin access.
- Do not assume one site is isolated.
- Do not run a network-wide preset without a snapshot.

## Eradication

- Remove malicious files, users, cron and options per site.
- Reinstall shared plugins and themes from trusted sources.
- Check network tables and shared uploads.
- Rotate network and site keys.
- Review super admins and their sessions.

## Recovery

- Rebuild affected sites from clean backups.
- Verify the network admin, site admin and normal user paths.
- Check each site's checkout, forms and login.
- 72-hour watch across the network.

## Verification

- [ ] No unknown super admin.
- [ ] Network and site settings correct.
- [ ] Shared plugins and themes checksums clean.
- [ ] Each site loads and functions.
- [ ] No new IOC after 72 hours.

## Stop Conditions

- The scope is unclear and the client cannot confirm which sites are affected.
- The host or CDN must act at the network edge.
- A network-wide change would affect unrelated customers.
