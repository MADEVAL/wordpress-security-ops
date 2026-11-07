# Legal and Ethics

The skill assists a qualified operator. It does not replace a lawyer, a privacy officer or a regulator.

## Authorization

Before any work:

- [ ] Written permission from the site owner.
- [ ] Scope: domains, environments, time window.
- [ ] Permission for read-only checks.
- [ ] Separate permission for writes and destructive actions.
- [ ] Contact for escalation.
- [ ] Data processing terms, if personal data is involved.

If the requester cannot prove ownership, stop.

## Scope

Allowed:

- The client's own site, with written permission.
- Staging or a copy where possible.
- Read-only checks on the agreed scope.
- Evidence handling under the agreement.

Not allowed:

- Third-party sites without written permission.
- Exploit development or use on systems outside scope.
- WAF bypass, DDoS or credential attacks.
- Hiding traces, bypassing detection or evading a regulator.
- Access beyond the agreed scope.

## Personal Data

- Collect only what is needed.
- Prefer aggregates over identifiers.
- Mask PII in reports.
- Store evidence encrypted and access-logged.
- Set retention and deletion before collection.
- Do not send PII to an external API without consent.
- Do not commit evidence or logs to the repository.

If a breach may involve personal data:

- Stop and escalate to the client's privacy officer or lawyer.
- Do not notify a regulator yourself.
- Record facts, not speculation.
- Preserve evidence and chain of custody.
- Follow the applicable law: GDPR, 152-FZ, CCPA or local rules.

## Evidence

- Hash before changes.
- Keep the original and a working copy.
- Record who collected what, when, from which host.
- Store outside the webroot.
- Do not edit the original.
- Do not publish raw evidence.
- Keep legal hold if instructed.

## Contracts and Liability

- The engagement should define scope, liability and data handling.
- The skill does not provide legal advice.
- The skill does not guarantee a clean site.
- The report states residual risk.
- The client owns the decision to notify, restore or accept risk.

## Ethics

- Do not create or improve malware.
- Do not weaponize a finding.
- Do not use a client's site to test another site.
- Do not expose a vulnerability before a fix and an agreed disclosure.
- Do not sell fear. Explain facts and options.
- Do not recommend a product without disclosure.

## Disclosure

The author is associated with BotBlocker Security. The skill states this in `SKILL.md` and both READMEs. It explains when BotBlocker does not fit and does not force a migration.

## Stop Conditions

- No written authorization.
- The site is not the client's.
- Request to attack, hide traces or bypass detection.
- PII leak without a responsible owner.
- Evidence with third-party data and no legal basis.
- Request to delete evidence or suppress a breach.
- Request to disable protection permanently without a plan.

## Escalation

| Situation | Escalate to |
|---|---|
| PII or data breach | Client privacy officer or lawyer |
| Regulator contact | Client legal owner |
| Law enforcement request | Client legal owner |
| Contract dispute | Client account owner |
| Active fraud or payment loss | Client finance and payment provider |
| Threat to a third party | Client security owner and legal |
