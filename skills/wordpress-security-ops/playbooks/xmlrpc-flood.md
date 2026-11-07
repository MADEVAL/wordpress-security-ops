# XML-RPC Flood

Without BotBlocker, use the access log and server or CDN rules in `../references/without-botblocker.md`.

## Symptoms

- `xmlrpc.php` request spike.
- `system.multicall` brute force.
- Host CPU or bandwidth saturation.
- Pingback abuse.

## First Checks

- [ ] `get-traffic-summary` and `get-event-details` for `xmlrpc.php`.
- [ ] `get-top-ips` and ASN.
- [ ] Check whether XML-RPC is needed by the site, app or Jetpack.
- [ ] Check pingbacks and comments.

## Containment

- Block the source IP or ASN.
- Disable XML-RPC if nothing needs it.
- If something needs it, allowlist the required IPs and block `system.multicall`.
- Add rate limits.
- Do not block the host or CDN.

## Verification

- [ ] Site, admin and app work.
- [ ] XML-RPC is disabled or allowlisted.
- [ ] No request spike after containment.
- [ ] No legitimate integration broken.

## Stop Conditions

- A required mobile app or service depends on XML-RPC and cannot be allowlisted.
- The flood is part of a larger DDoS and the host or CDN must act.
