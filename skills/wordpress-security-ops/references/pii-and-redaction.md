# PII and Redaction

PII is a separate plane. The default MCP channel stays clean, live triage stays fast, and raw evidence stays controlled.

## Source of Record

| Purpose | Source | Handling |
|---|---|---|
| Triage | PII read-only abilities over REST or WP-CLI | Plugin masks output; do not request raw values |
| Raw evidence | Web-server logs, PHP logs, DB exports, filesystem copies | Encrypted store outside webroot, access log |
| Default MCP | No PII | Use aggregate abilities only |
| Optional operator MCP | Masked output only | Same retention rules |
| Client report | No raw PII | Mask IP, login, email and identifying UA |
| Technical report | Pseudonymized | Reference evidence IDs, not raw values |
| Repository | No evidence, logs, exports or secrets | Never commit |

If only masked abilities are available, mark evidence `masked-only` and do not claim raw IOC completeness.

## Data Classes

| Class | Examples | Client report | Technical report | Evidence |
|---|---|---|---|---|
| Secret | Password, application password, cookie, token, private key, salt | Never | Never | Never |
| Direct identifier | Login, email, user ID | Mask or pseudonymize | Pseudonymize | Allowed if authorized |
| Network identifier | IP, subnet, ASN | Mask or aggregate | Mask or reference | Allowed if authorized |
| Device identifier | User agent, session id | Aggregate | Reference | Allowed if authorized |
| Content | Post text, comment, file name | Quote only if needed | Quote with care | Allowed |
| Aggregate | Counts, country, ASN totals, top paths | Allowed | Allowed | Allowed |

## Masking Rules

| Value | Masked form | Notes |
|---|---|---|
| IPv4 `203.0.113.10` | `203.0.*.*` | Keep the first two octets |
| IPv6 `2001:db8:1:2::5` | `2001:db8:*:*:*:*:*:*` | Keep the network prefix |
| Email `admin@example.com` | `a***@example.com` | Keep domain only when needed |
| Login `administrator` | `a***r` | Keep length hint |
| Cookie or token | `[REDACTED]` | Never partial |
| Application password | `[REDACTED]` | Never partial |
| Private key | `[REDACTED]` | Never print the header |
| Session id | `[REDACTED]` | Replace fully |
| User agent | Keep browser family, drop version if identifying | `Chrome (mobile)` |

When in doubt, mask more. A report can explain a pattern without exposing the value.

## Redaction Keys

Mask values whose key matches, case-insensitive:

```text
password, passwd, secret, token, api_key, apikey, authorization,
cookie, session, nonce, salt, private_key, application_password,
auth_token, auth_key
```

Matching is a case-insensitive substring on the key name. `authorization` matches; `author` does not. A bare `pass` is not used because it would match unrelated keys such as `passenger`.

Do not mask every field named `hash`: hashes are evidence. Mask `password_hash`, `user_activation_key` and session tokens.

## Report Split

### Client version

- No raw IP, login, email or session id.
- Aggregate counts and masked examples.
- Plain language: what happened, what was done, what remains.
- No exploit details that help an attacker.
- Clear residual risk.

### Technical version

- Evidence IDs and hashes.
- Pseudonymized actors and networks.
- Exact ability calls, arguments and results, redacted.
- Timeline with source references.
- Retention and chain of custody.

## Evidence

- Store outside the webroot.
- Encrypt at rest.
- Access log with who, when and why.
- Manifest: path, SHA-256, size, mtime, collector, UTC time, source, redaction.
- Do not commit evidence.
- Delete according to the engagement agreement.
- Keep legal hold if the client or lawyer requires it.

## Redaction Tests

Test at least:

- password, secret and token keys;
- `Authorization: Basic ...` and `Bearer ...`;
- cookies and session ids;
- IPv4 and IPv6;
- email and login;
- private key header;
- nested arrays and JSON strings;
- false positives: file hash, commit hash, public key fingerprint.

## Incident Examples

| Raw | Client report |
|---|---|
| `203.0.113.10` | `203.0.*.*` |
| `admin@example.com` | `a***@example.com` |
| `administrator` | `a***r` |
| `PHPSESSID=abc123` | `[REDACTED]` |
| `curl/8.0` | `curl` |
| `POST /wp-login.php` | `POST /wp-login.php` |

## Stop Conditions

- A PII leak has no responsible notification owner.
- Evidence contains third-party data without a legal basis.
- A client asks to remove evidence or hide a breach.
- A report would expose a secret or a raw identifier unnecessarily.
