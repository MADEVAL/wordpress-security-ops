# Reporting Templates

Use the same structure in every mode. Put detail in artifacts, keep the chat summary short.

## Client Report

1. What happened.
2. What was affected.
3. What was done.
4. What remains.
5. What the client should do next.
6. Residual risk and limitations.

No raw PII. No secrets. No jargon without an explanation.

## Technical Report

1. Scope and access.
2. Sources and methods.
3. Timeline and IOC.
4. Findings with severity, confidence, exposure, impact and priority.
5. Actions with dry-run, approval, result and post-check.
6. Evidence IDs and hashes.
7. Residual risk.
8. 30/90/365 plan.

## Audit Report

Use `templates/audit-report.md`. Sort by priority, not by checklist order.

## Incident Report

Use `templates/incident-report.md`. Include the timeline, IOC, root cause, persistence, recovery, verification and notifications.

## Client Summary

Use `templates/client-summary.md`. One page, plain language, clear next steps, explicit residual risk.

## Remediation Plan

Use `templates/remediation-plan.md`. Every action has an owner, timeframe, dry-run, approval, rollback and post-check.

## Language

- English by default.
- Russian for the client when requested.
- Do not translate technical terms at the cost of accuracy.
- Keep UTC timestamps.
- Define acronyms on first use.

## Redaction

- Mask IP, login, email and identifying UA in the client version.
- Keep evidence IDs and hashes.
- Do not include passwords, cookies, tokens, application passwords or private keys.
- Do not include raw database dumps.
- Do not include third-party data without a legal basis.

## Priority

| Priority | Timeframe |
|---|---|
| P0 | Now |
| P1 | 24 hours |
| P2 | 7 days |
| P3 | 30 days |

Override upward for data, money, access, active backdoor, admin compromise, blacklist or checkout outage. Record the reason.

## 30/90/365 Plan

| Horizon | Focus |
|---|---|
| 30 days | Fix critical and high findings; verify backups; enable 2FA; monitoring |
| 90 days | Remove abandoned plugins; update PHP; test restore; review roles |
| 365 days | Recurring audit; tabletop incident exercise; contract and retention review |

## Artifacts

| Artifact | Format |
|---|---|
| Findings | JSON |
| Timeline | CSV |
| Evidence manifest | JSON + SHA-256 |
| Audit report | Markdown |
| Incident report | Markdown |
| Client summary | Markdown or PDF |
| Remediation plan | Markdown |

Name files `YYYY-MM-DD_client_artifact_vN.ext`. Do not overwrite a previous version.

## Final Check

- [ ] What was not checked is explicit.
- [ ] Residual risk is explicit.
- [ ] No secrets or raw PII.
- [ ] Every finding has a source.
- [ ] Every action has an approval and a post-check.
- [ ] The client can understand the next step.
