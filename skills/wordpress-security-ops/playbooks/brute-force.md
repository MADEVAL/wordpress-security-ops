# Brute Force and Login Attacks

Without BotBlocker, use the access log and host firewall equivalents in `../references/without-botblocker.md`. The phases and gates do not change.

## Symptoms

- Many failed logins in a short period.
- `wp-login.php` or XML-RPC traffic spike.
- Locked-out admins.
- Distributed attempts from many IPs or one ASN.

## First Checks

- [ ] `get-login-attempts` for failures and successes.
- [ ] `get-top-ips` for concentration.
- [ ] `get-event-details` for path, UA and reason.
- [ ] `get-traffic-summary` for scale.
- [ ] `check-ip` for the office, hosting and monitoring IPs.
- [ ] Confirm which accounts are targeted.
- [ ] Check whether any attempt succeeded.

## Containment

1. Block the confirmed attacker IP or subnet with `block-ip` or `upsert-ip-rule`.
2. Dry-run, show `previous_rule`, approve, apply.
3. If distributed, use a temporary ASN or country rule after checking good IPs.
4. Enable or tighten progressive lockout.
5. Add CAPTCHA to login and lost password.
6. Enforce 2FA for administrators.
7. Rotate passwords for targeted admins if a login succeeded.

Do not block the office, hosting, monitoring or payment IP. Do not use `unblock-ip` to clean up unless every rule for the IP should go.

## Eradication

- [ ] Remove unknown admin users.
- [ ] Revoke sessions and application passwords.
- [ ] Check for new plugins, themes, cron and options.
- [ ] Check `wp-config.php` and mu-plugins.
- [ ] Verify no successful login from an unknown network.

## Recovery

- [ ] 2FA on all admins with backup codes.
- [ ] Strong unique passwords.
- [ ] Rate limit and CAPTCHA tested.
- [ ] Alerts for failed login spikes.
- [ ] 72-hour watch.

## Verification

- [ ] Admin login works.
- [ ] Normal user login works.
- [ ] Password reset works.
- [ ] No lockout for known-good IPs.
- [ ] No successful attacker login after containment.

## Stop Conditions

- A login succeeded and admin access was obtained: treat as a full incident.
- The attack is distributed and a country block would hit customers or payments.
- The client refuses 2FA for administrators without a compensating control.
