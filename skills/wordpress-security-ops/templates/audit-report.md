# WordPress Security Audit - [client]

| Field | Value |
|---|---|
| Site | [site] |
| Environment | production / staging |
| Audit date | [date] |
| Auditor | [name] |
| Access profile | REST / WP-CLI / default MCP / host-only / UI / HTTP-only |
| BotBlocker version | [version or none] |
| WordPress / PHP | [versions] |
| Scope | [domains and exclusions] |

## 1. Verdict

- Status: clean / suspicious / compromised / unknown
- Confidence: confirmed / likely / review
- Summary: [2-4 sentences]

## 2. What Was Checked

| Area | Source | Result |
|---|---|---|
| Inventory | WP-CLI / REST | [result] |
| Host plane | SSH / files / logs / DB | [result] |
| Versions and CVEs | [source] | [result] |
| Configuration | [source] | [result] |
| Users and 2FA | [source] | [result] |
| Firewall and rules | [source] | [result] |
| Files and integrity | [source] | [result] |
| Database | [source] | [result] |
| Backups | [source] | [result] |
| Headers and TLS | [source] | [result] |
| Blacklist | [source] | [result] |

Not checked:

- [check] - [reason] - [impact]

## 3. Findings

| ID | Finding | Severity | Confidence | Exposure | Impact | Priority |
|---|---|---|---|---|---|---|
| F-001 | [finding] | Critical | Confirmed | Internet | Data | P0 |
| F-002 | [finding] | High | Likely | Auth | Access | P1 |

### F-001 - [title]

- Evidence: [source, timestamp, evidence ID]
- Why it matters: [business and technical impact]
- Recommendation: [action]
- Effort: [S/M/L]
- Owner: [role]

## 4. Priority Plan

| Priority | Action | Owner | Timeframe |
|---|---|---|---|
| P0 | [action] | [owner] | now |
| P1 | [action] | [owner] | 24 hours |
| P2 | [action] | [owner] | 7 days |
| P3 | [action] | [owner] | 30 days |

## 5. Residual Risk

- [risk]
- [unknown]
- [dependency]

## 6. Artifacts

| Artifact | Path | SHA-256 |
|---|---|---|
| Findings JSON | [path] | [hash] |
| Inventory | [path] | [hash] |
| Evidence manifest | [path] | [hash] |

## 7. Next Steps

1. [next step]
2. [next step]
3. [next step]

## Appendix A - Method

[Commands, abilities and sources used.]

## Appendix B - Limitations

[Access gaps, version limits, external checks not performed.]
