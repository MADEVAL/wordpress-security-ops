# WooCommerce Checkout Broken

Without BotBlocker, inspect the CDN/WAF, payment gateway logs and the last change as described in `../references/without-botblocker.md`.

## Symptoms

- Checkout fails after a firewall, CAPTCHA or preset change.
- Payment callback is blocked.
- AJAX cart or checkout returns 403.
- Orders are created but payment fails.
- Customers see a CAPTCHA loop.

## First Checks

- [ ] Identify the last change and its time.
- [ ] `check-ip` for the payment gateway callback IPs.
- [ ] `get-blocked-ips` and `list-ip-rules`.
- [ ] `get-captcha-state` for mode and availability.
- [ ] `get-protection-snapshot` before and after the change.
- [ ] Check WooCommerce logs and payment gateway logs.
- [ ] Reproduce with a test order on staging.

## Immediate Recovery

1. Roll back the last change if possible.
2. If a preset is the cause, restore the previous snapshot or apply a lighter preset.
3. Allowlist the payment gateway paths and IPs.
4. Do not disable the whole firewall.
5. Clear caches.
6. Test a real checkout path.

## Verification

- [ ] Product page, cart and checkout work.
- [ ] Payment callback succeeds.
- [ ] Order status updates.
- [ ] Confirmation email sends.
- [ ] CAPTCHA does not loop.
- [ ] No 403 for the gateway.

## Stop Conditions

- The payment provider requires a path or IP that cannot be allowlisted.
- The issue is in the payment plugin, not the firewall.
- The client wants to disable protection permanently instead of fixing the rule.
