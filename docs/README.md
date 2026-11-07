# Development Docs

Internal design and delivery documents for `wordpress-security-ops`. None of these files are part of the installed skill payload.

| File | Purpose |
|---|---|
| `ARCHITECTURE.md` | Design baseline: channels, abilities, gates, PII, output contract |
| `IMPLEMENTATION-PLAN.md` | Phased delivery plan, tasks, exit criteria and risks |
| `SPEC.md` | Original specification and context |
| `ABILITIES.md` | Current ability registry: 48 definitions with class, confirm, dry_run, risk and addon |

Related material:

- `../skills/wordpress-security-ops/` - the installable skill payload.
- `../tests/` - validator and unit tests.

The archived 1.7.10 zip was an older snapshot with 22 abilities. The current plugin source defines 48; an unpublished build may report 60. Discovery is authoritative at runtime.
