# Admin Lockout

Without BotBlocker, use session and user commands plus the host firewall from `../references/without-botblocker.md`.

## Symptoms

- Admin cannot log in.
- The firewall blocks the admin IP.
- Full secure mode blocks the admin area.
- 2FA device is lost.
- Password reset email does not arrive.

## First Checks

- [ ] `check-ip` for the admin's public IP.
- [ ] `get-blocked-ips` and `list-ip-rules`.
- [ ] `get-firewall-status` for secure mode and protection state.
- [ ] `get-login-attempts` for the admin account.
- [ ] Confirm the admin identity and authority.
- [ ] Check email delivery and spam.

## Recovery Paths

### IP blocked

1. Dry-run `block-ip` or review the matching rule.
2. Prefer `delete-ip-rule` by id over `unblock-ip` if only one rule is wrong.
3. If the rule is a system or country rule, add an allow rule instead of deleting the block.
4. Apply with approval.
5. `check-ip` again.
6. Ask the admin to log in from the same IP.

### Full secure mode

1. Confirm the admin IP is allowlisted.
2. Dry-run `set-secure-mode` to `frontend`.
3. Approve, apply, verify admin access.
4. Revert to `full` only after the allowlist is correct.

### 2FA lost

1. Verify identity out of band.
2. Use a backup code if available.
3. Reset the 2FA secret for that user through the admin UI or a controlled CLI action.
4. Require a new enrollment immediately.
5. Record the change.

### Password or email

1. Use WP-CLI `wp user update` or the hosting panel.
2. Do not disable the firewall to recover access.
3. Check mail logs and SPF/DKIM/DMARC.

## Do Not

- Disable the firewall globally.
- Delete all rules with `unblock-ip` without reviewing `previous_rules`.
- Set `force` on a self-block unless the intent is explicit.
- Bypass 2FA without identity verification.

## Verification

- [ ] Admin login works from the expected IP.
- [ ] Normal users are not affected.
- [ ] Checkout and forms still work.
- [ ] The allowlist is documented.
- [ ] No new lockout after 24 hours.

## Stop Conditions

- The requester cannot prove admin identity.
- The lockout is caused by a host or CDN WAF outside the client's control.
- The only fix would expose the admin area to the public internet.
