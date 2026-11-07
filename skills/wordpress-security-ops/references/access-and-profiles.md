# Access and Profiles

How the skill reads and writes WordPress security state without turning a triage into an accident.

## Channel Decision

REST with an application password and WP-CLI over SSH are the default read/write channels. Default MCP is read-only. Operator MCP is optional, consent-gated and not installed by default.

| Channel | Read | Write | Best for |
|---|---|---|---|
| REST + application password | Yes | Yes, by annotation | Remote work without SSH |
| WP-CLI + SSH | Yes | Yes | Full host and database access |
| Default MCP | Yes, public read-only | No | Triage and audit |
| Operator MCP | Yes, if listed | Yes, if listed | Optional scripted write channel |
| Admin UI | Yes | Yes, manual | Last fallback |

Use the narrowest channel that satisfies the mode. Never use the default MCP for a write.

## Access Vector

Record access as a vector, not a single level:

```json
{
  "control": "rest | wpcli | default-mcp | operator-mcp | ui | none",
  "host": "ssh | file-access | panel | logs-only | none",
  "evidence": "encrypted-store | local-only | none",
  "external": "consented | not-consented",
  "pii": "masked-ability | host-logs | none",
  "write": "rest | wpcli | operator | ui | none"
}
```

Minimum profiles:

| Mode | Control | Host | PII | Write |
|---|---|---|---|---|
| `audit` | REST, default MCP or WP-CLI | optional | optional | none |
| `harden` | REST or WP-CLI | backup access | optional | required |
| `incident` | REST or WP-CLI | logs and files | masked ability or host logs | required |
| `forensic` | any read | SSH, files, DB, logs | host logs preferred | none |
| `recover` | REST or WP-CLI | backup and files | optional | required |
| `monitor` | REST or WP-CLI | optional | no | required for setup |
| `report` | any read | optional | redacted only | none |

If a minimum is missing, say which checks are degraded and why.

## Startup Capability Probe

Run in order. Stop when the channel is unavailable.

1. Confirm scope, environment, maintenance window and SPOC.
2. `get-firewall-status` for version, protection state, secure mode and CAPTCHA mode.
3. Discover the real surface:
   - default MCP: `tools/list`;
   - WP-CLI: `wp ability list`;
   - REST: `GET /wp-json/wp-abilities/v1/abilities`.
4. For each ability that discovery returned, read its schema. Then call, in order, whichever exist: `get-capabilities`, `get-addons-status`, `get-license-status`, `get-protection-snapshot`, `get-health-score`, `validate-config`.
5. If discovery has no BotBlocker abilities, switch to the host plane in `without-botblocker.md`. Do not call names that discovery did not return.

Save the result as `capability-report.json` with present, missing, disabled, hidden, addon-inactive and unsupported items. An absent extended ability is `not_checked`, not `clean`.

## REST

Base path:

```text
/wp-json/wp-abilities/v1/abilities/{ability}/run
```

Authentication: HTTP Basic with `login:application-password`. Use HTTPS. Never put the application password in a URL, report or shell history.

Method by annotation:

| Ability type | Method | Input |
|---|---|---|
| Read-only | `GET` | Query string, URL-encoded JSON or typed array |
| Destructive | `DELETE` | Query string |
| Normal write | `POST` | JSON body with `input` |

Examples:

```bash
# Read-only
curl -sS -u "$SECOPS_USER:$SECOPS_APP_PASSWORD" \
  "https://example.com/wp-json/wp-abilities/v1/abilities/botblocker/get-firewall-status/run"

# Read-only with input
curl -sS -u "$SECOPS_USER:$SECOPS_APP_PASSWORD" \
  "https://example.com/wp-json/wp-abilities/v1/abilities/botblocker/check-ip/run?input=%7B%22ip%22%3A%22203.0.113.10%22%7D"

# Destructive with dry-run and confirm
curl -sS -X DELETE -u "$SECOPS_USER:$SECOPS_APP_PASSWORD" \
  "https://example.com/wp-json/wp-abilities/v1/abilities/botblocker/block-ip/run?input=%7B%22ip%22%3A%22203.0.113.10%22%2C%22reason%22%3A%22bruteforce%22%2C%22confirm%22%3A%22BLOCK%22%2C%22dry_run%22%3Atrue%7D"
```

Rules:

- On `401`, check the application password and HTTPS.
- On `403` or `rest_forbidden`, check capability, role and multisite.
- On `404`, check the ability toggle, master switch and addon state.
- Do not retry a destructive call automatically.
- Do not log the `Authorization` header.
- GET and DELETE put the input in the query string, so the web server records it. Never put a password, token or private key there. Confirm tokens are public by design and may appear in logs.
- Prefer `SECOPS_APP_PASSWORD` over `--app-password`; a command-line secret is visible in the process list and shell history.

## WP-CLI

```bash
wp ability list
wp ability list --public
wp ability get botblocker/get-firewall-status --fields=name,label,readonly,destructive,public,show_in_rest
wp ability can-run botblocker/block-ip --user=admin
wp ability validate botblocker/block-ip --input='{"ip":"203.0.113.10","confirm":"BLOCK"}'
wp ability run botblocker/check-ip --input='{"ip":"203.0.113.10"}'
```

`wp ability` may require a nightly WP-CLI. If the command is missing, use REST.

WP-CLI is also the host plane:

```bash
wp core version
wp plugin list --format=json
wp theme list --format=json
wp user list --fields=ID,user_login,roles --format=json
wp cron event list --format=json
wp core verify-checksums
wp plugin verify-checksums --all
```

## Default MCP

The default server exposes three meta-tools:

| Tool | Use |
|---|---|
| `mcp-adapter-discover-abilities` | List public abilities |
| `mcp-adapter-get-ability-info` | Read schema and annotations |
| `mcp-adapter-execute-ability` | Execute a public read-only ability |

Handshake:

1. `initialize` without a session header.
2. Read `Mcp-Session-Id`.
3. Send `notifications/initialized` with the header.
4. Call `tools/list` and `tools/call`.
5. `DELETE` the session when finished.

Never hardcode the protocol version; read it from `initialize`. The default server cannot write. A destructive ability returns `ability_not_found`.

## Operator MCP

Operator MCP is optional. It is a custom server with an explicit tool list, and it does not make an ability public. The ability's permission callback still applies.

If used:

1. Get explicit consent; it is a site change.
2. Install only over HTTPS with an IP allowlist or VPN.
3. Use a dedicated user and application password.
4. Prefer a dedicated capability over `manage_options`.
5. Verify `tools/list`.
6. Run one dry-run write and confirm `applied=false`.
7. Run the approved write, verify and log.
8. Revoke the password and remove or disable the server.

Do not install it by default. Do not expose it to the public internet.

## Authentication and Least Privilege

- Use a dedicated user, not the main administrator.
- Use an application password, not the account password.
- Use HTTPS everywhere.
- Prefer a dedicated capability such as `bbcs_operate_security` over `manage_options`.
- Keep the operator IP in an allowlist or VPN.
- Revoke access after the engagement.
- On multisite, check `canManage()` and network vs site scope.

Capability resolution can involve:

1. the ability definition, including addon definitions;
2. `bbcs_ability_required_capability`;
3. `bbcs_ability_capability_by_risk`;
4. the plugin default, usually `manage_options`.

An ability hidden by capability is not the same as an ability that is disabled. Check the user, role, multisite and filters before concluding it does not exist.

## Addon Gates

An addon ability is available only when all gates pass:

1. master switch;
2. addon active;
3. `requires_core`;
4. per-ability toggle;
5. callable.

Addon toggles live in the addon option, not in core settings. A preset may restore core toggles but not addon toggles. Verify separately.

## Error Handling

| Symptom | Likely cause | Action |
|---|---|---|
| `401` | Bad application password or HTTP | Fix auth, use HTTPS |
| `403` / `rest_forbidden` | Missing capability, role or IP allowlist | Check user and multisite |
| `404` / `ability_not_found` | Master switch, toggle, addon or capability hiding | Check all gates; run discovery |
| `ability_confirmation_required` | Missing or wrong token | Read schema, ask human, pass exact token |
| `ability_rate_limited` | Destructive rate limit | Wait; do not bypass |
| `ability_self_block_requires_force` | Own or server IP | Verify intent; do not force blindly |
| `ability_allow_downgrade_requires_force` | Allow to block downgrade | Verify intent; do not force |
| `ability_readonly_rule` | System rule | Choose another rule |
| `ability_unavailable` | Addon, class or tables missing | Check install and database |
| `-32600` | MCP session missing | Re-initialize; retry read-only only |

## PII

PII abilities are not public in MCP. Use REST or WP-CLI. The plugin masks the output; the skill does not request raw values. Raw evidence comes from host logs, database exports and filesystem copies, stored outside the webroot and access-logged. If only masked abilities are available, mark evidence `masked-only`.

## Safety Checklist

- [ ] Authorization is written and scoped.
- [ ] Channel is the narrowest that works.
- [ ] Application password is dedicated and revocable.
- [ ] HTTPS is enforced.
- [ ] Capability is least privilege.
- [ ] The ability schema was read before the call.
- [ ] A backup and evidence manifest exist before a write.
- [ ] Dry-run was reviewed.
- [ ] Human approval is recorded.
- [ ] Post-check passed.
- [ ] Operator MCP, if used, was removed and revoked.
