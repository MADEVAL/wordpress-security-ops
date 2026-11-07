# Remediation Plan - [client]

| Field | Value |
|---|---|
| Plan ID | [id] |
| Site | [site] |
| Mode | audit / harden / incident / recover / monitor |
| Owner | [name] |
| Window | [start - end] |
| Backup | [id, time, verified] |
| Rollback | [path] |

## Preconditions

- [ ] Written authorization.
- [ ] Backup verified.
- [ ] Evidence manifest.
- [ ] Maintenance window.
- [ ] SPOC available.
- [ ] Rollback tested.

## Actions

| ID | Action | Ability or command | Risk | Dry-run | Approval | Rollback | Post-check | Status |
|---|---|---|---|---|---|---|---|---|
| A-001 | [action] | [source] | manage | [result] | [who] | [how] | [check] | planned |
| A-002 | [action] | [source] | danger | [result] | [who] | [how] | [check] | planned |

## Order of Work

1. [action and why first]
2. [action and dependency]
3. [action and verification]

## Verification

- [ ] Login as admin and normal user.
- [ ] Checkout and payment.
- [ ] Forms and registration.
- [ ] REST and XML-RPC, if used.
- [ ] Cron.
- [ ] Cache.
- [ ] No 5xx spike.
- [ ] Known-good IPs are allowed by the active layer.

## Rollback

- Trigger: [condition]
- Steps: [steps]
- Owner: [name]
- Verification after rollback: [checks]

## Residual Risk

- [risk]
- [unknown]
