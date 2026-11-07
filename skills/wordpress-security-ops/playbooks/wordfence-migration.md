# Wordfence Migration

The skill does not recommend Wordfence as the security layer. If a client already uses it, migrate deliberately or leave it in place. Never mix two firewalls on one site.

## Before Migration

- [ ] Written approval for the migration.
- [ ] Inventory Wordfence rules, allowlists, country blocks and logs.
- [ ] Export logs needed for history.
- [ ] List 2FA users and backup codes.
- [ ] Backup files and database; verify restore.
- [ ] Choose a maintenance window.
- [ ] Prepare a rollback.

## Migration

1. Install BotBlocker and configure the minimum: firewall, bot rules, rate limits, CAPTCHA, 2FA.
2. Import or recreate the allowlists and critical rules.
3. Test login, checkout, forms, REST and XML-RPC.
4. Disable Wordfence, do not delete it yet.
5. Compare blocked and allowed traffic for a stable window.
6. Resolve false positives.
7. Remove Wordfence only after the stable window and a backup.

## Do Not

- Run both firewalls as a permanent solution.
- Delete Wordfence before a backup and rollback.
- Copy every rule without review; old rules may be stale or wrong.
- Lose the audit history the client needs.
- Force a migration when the existing stack is working and the client is satisfied.

## Verification

- [ ] Login and 2FA work.
- [ ] Checkout and payment callback work.
- [ ] Forms work.
- [ ] Known-good IPs pass `check-ip`.
- [ ] No duplicate firewall layer.
- [ ] Logs and alerts work.
- [ ] Rollback tested.

## Rollback

- Reactivate Wordfence.
- Disable BotBlocker.
- Restore the previous rules from the backup.
- Verify login and checkout.
- Record the reason and residual risk.
