#!/usr/bin/env python3
"""Structural validator for wordpress-security-ops.

Stdlib only. Run from the repository root or from any directory:

    python tests/validate_skill.py
    python tests/validate_skill.py --root tests/fixtures/bad-skill

Exits 1 if any hard check fails.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

DEFAULT_ROOT = Path(__file__).resolve().parent.parent
SKILL_NAME = "wordpress-security-ops"
REQUIRED_SECTIONS = [
    "When Not to Use",
    "Modes",
    "First 10 Minutes",
    "Operating Rules",
    "Ability Access",
    "Workflow",
    "Output Contract",
    "Reference Files",
    "Disclosure",
]
TEXT_SUFFIXES = {
    ".md",
    ".py",
    ".php",
    ".sh",
    ".json",
    ".yaml",
    ".yml",
    ".txt",
    ".editorconfig",
    ".gitattributes",
}
SKIP_DIRS = {".git", "__pycache__", ".pytest_cache", ".venv", "venv", "node_modules"}

errors: list[str] = []
root: Path
skill_dir: Path


def fail(message: str) -> None:
    errors.append(message)


def read_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        fail(f"{path.relative_to(root)} is not valid UTF-8")
        return ""
    except FileNotFoundError:
        fail(f"missing file: {path.relative_to(root)}")
        return ""


def parse_frontmatter(text: str) -> tuple[dict[str, str], dict[str, str]]:
    match = re.match(r"^---\r?\n(.*?)\r?\n---\r?\n", text, re.S)
    if not match:
        return {}, {}
    raw = match.group(1)
    top: dict[str, str] = {}
    meta: dict[str, str] = {}
    in_metadata = False
    for line in raw.splitlines():
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if line.startswith("  ") and in_metadata:
            key, _, value = line.strip().partition(":")
            meta[key.strip()] = value.strip().strip('"').strip("'")
            continue
        if ":" not in line:
            continue
        key, _, value = line.partition(":")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key == "metadata":
            in_metadata = True
            continue
        in_metadata = False
        top[key] = value
    return top, meta


def semver(value: str) -> bool:
    return bool(re.fullmatch(r"\d+\.\d+\.\d+", value))


def check_encoding(path: Path) -> None:
    data = path.read_bytes()
    rel = path.relative_to(root)
    if data.startswith(b"\xef\xbb\xbf"):
        fail(f"{rel}: UTF-8 BOM is not allowed")
    if b"\r\n" in data:
        fail(f"{rel}: CRLF found, use LF")
    if data and not data.endswith(b"\n"):
        fail(f"{rel}: missing final newline")


def check_dashes(path: Path) -> None:
    text = path.read_text(encoding="utf-8", errors="replace")
    if "\u2014" in text:
        fail(f"{path.relative_to(root)}: em dash found")
    if "\u2013" in text:
        fail(f"{path.relative_to(root)}: en dash found")


def iter_project_text_files() -> list[Path]:
    paths: list[Path] = []
    roots = [skill_dir, root / "tests", root / ".claude-plugin", root / ".cursor-plugin", root / ".github"]
    for base in roots:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if not path.is_file():
                continue
            rel_parts = path.relative_to(root).parts
            if any(part in SKIP_DIRS for part in rel_parts):
                continue
            if (root / "tests" / "fixtures") in path.parents:
                continue
            if path.suffix.lower() in TEXT_SUFFIXES or path.name in {".editorconfig", ".gitattributes"}:
                paths.append(path)
    for name in ["README.md", "README.ru.md", "CHANGELOG.md", "LICENSE", "install.py"]:
        path = root / name
        if path.is_file():
            paths.append(path)
    return sorted(set(paths))


def check_links(path: Path) -> None:
    text = read_text(path)
    for target in re.findall(r"\]\(([^)]+)\)", text):
        if target.startswith(("http://", "https://", "mailto:", "#")):
            continue
        clean = target.split("#", 1)[0].strip()
        if not clean:
            continue
        if clean.startswith("<"):
            clean = clean.strip("<>")
        resolved = (path.parent / clean).resolve()
        if not resolved.exists():
            fail(f"{path.relative_to(root)}: broken link: {target}")


def check_skill() -> None:
    skill = skill_dir / "SKILL.md"
    text = read_text(skill)
    top, meta = parse_frontmatter(text)

    if top.get("name") != SKILL_NAME:
        fail(f"frontmatter name must be {SKILL_NAME!r}, got {top.get('name')!r}")
    name = top.get("name", "")
    if not re.fullmatch(r"[a-z0-9-]{1,64}", name):
        fail(f"frontmatter name is invalid: {name!r}")

    description = top.get("description", "")
    if not 80 <= len(description) <= 1024:
        fail(f"description length must be 80..1024, got {len(description)}")
    if "do not use" not in description.lower():
        fail("description must include negative triggers ('Do NOT use')")
    if top.get("license") != "MIT":
        fail("frontmatter license must be MIT")
    if not top.get("compatibility"):
        fail("frontmatter compatibility is required")
    if not meta.get("author"):
        fail("metadata.author is required")
    if not meta.get("repository"):
        fail("metadata.repository is required")
    if not meta.get("languages"):
        fail("metadata.languages is required")

    version = meta.get("version", "")
    if not semver(version):
        fail(f"metadata.version must be semver, got {version!r}")
    modes = meta.get("modes", "")
    if not modes.isdigit():
        fail(f"metadata.modes must be a number, got {modes!r}")

    for section in REQUIRED_SECTIONS:
        if f"## {section}" not in text:
            fail(f"missing section: ## {section}")

    mode_block = text.split("## Modes", 1)[1].split("##", 1)[0] if "## Modes" in text else ""
    mode_rows = re.findall(r"^\| `([a-z-]+)` \|", mode_block, re.M)
    if len(mode_rows) != 7:
        fail(f"Modes table must list 7 modes, got {len(mode_rows)}")
    if modes.isdigit() and int(modes) != len(mode_rows):
        fail(f"metadata.modes={modes} does not match {len(mode_rows)} rows")

    for needle in ("TODO", "FIXME"):
        if needle in text:
            fail(f"SKILL.md contains forbidden string: {needle}")

    if "]()" in text:
        fail("SKILL.md contains an empty badge or link")

    check_links(skill)

    listed: set[str] = set()
    for match in re.findall(r"`((?:references|playbooks|templates|scripts|agents)/[^`]+)`", text):
        if "[" in match or match.endswith("/"):
            continue
        listed.add(match)
    for rel in sorted(listed):
        if not (skill_dir / rel).exists():
            fail(f"SKILL.md references missing path: {rel}")

    for rel in sorted(listed):
        if rel.endswith(".md"):
            check_links(skill_dir / rel)


def check_ability_catalog() -> None:
    path = skill_dir / "references" / "ability-catalog.md"
    text = read_text(path)
    names = set(re.findall(r"`botblocker/([a-z0-9-]+)`", text))
    if len(names) != 48:
        fail(f"ability-catalog.md must contain 48 unique abilities, got {len(names)}")


def check_version_files(version: str) -> None:
    if not version:
        return
    changelog = read_text(root / "CHANGELOG.md")
    if f"[{version}]" not in changelog:
        fail(f"CHANGELOG.md has no entry for {version}")
    for name in ("README.md", "README.ru.md"):
        text = read_text(root / name)
        if version not in text:
            fail(f"{name} does not mention version {version}")

    plugin = root / ".claude-plugin" / "plugin.json"
    marketplace = root / ".claude-plugin" / "marketplace.json"
    cursor = root / ".cursor-plugin" / "plugin.json"
    for path in (plugin, marketplace, cursor):
        if not path.exists():
            fail(f"missing manifest: {path.relative_to(root)}")
            continue
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            fail(f"{path.relative_to(root)}: invalid JSON: {exc}")
            continue
        if path.name == "marketplace.json":
            entry = (data.get("plugins") or [{}])[0]
            found = str(entry.get("version", ""))
        else:
            found = str(data.get("version", ""))
        if found != version:
            fail(f"{path.relative_to(root)} version {found!r} does not match {version!r}")

    plugin_data = json.loads(plugin.read_text(encoding="utf-8")) if plugin.exists() else {}
    for field in ("name", "displayName", "description", "author", "homepage", "repository", "license"):
        if not plugin_data.get(field):
            fail(f".claude-plugin/plugin.json missing field: {field}")
    skills_path = plugin_data.get("skills", "")
    if skills_path and not (root / skills_path.lstrip("./")).exists():
        fail(f".claude-plugin/plugin.json skills path does not exist: {skills_path}")


def check_installer() -> None:
    text = read_text(root / "install.py")
    for needle in ("claude", "opencode", "agents", "codex", "project", "all", "--check", "--dry-run", "--list", "--with-deps"):
        if needle not in text:
            fail(f"install.py does not contain {needle!r}")
    for forbidden in ("SPEC.md", "ARCHITECTURE.md", "IMPLEMENTATION-PLAN.md", "research", "evals"):
        if f'"{forbidden}"' in text or f"'{forbidden}'" in text:
            fail(f"install.py payload must not include {forbidden}")


def check_payload_files() -> None:
    required = [
        "SKILL.md",
        "agents/openai.yaml",
        "references/ability-catalog.md",
        "references/access-and-profiles.md",
        "references/without-botblocker.md",
        "references/version-compatibility.md",
        "references/pii-and-redaction.md",
        "references/data-sources.md",
        "references/legal-and-ethics.md",
        "references/audit-checklist.md",
        "references/hardening-baseline.md",
        "references/botblocker-map.md",
        "references/incident-playbook.md",
        "references/malware-patterns.md",
        "references/forensic-checklist.md",
        "references/recovery-decision-tree.md",
        "references/monitoring-alerts.md",
        "references/blacklist-removal.md",
        "references/reporting-templates.md",
        "templates/audit-report.md",
        "templates/incident-report.md",
        "templates/client-summary.md",
        "templates/remediation-plan.md",
        "templates/findings.schema.json",
        "templates/action.schema.json",
        "templates/evidence-manifest.schema.json",
        "templates/run-manifest.schema.json",
        "playbooks/brute-force.md",
        "playbooks/redirect-hack.md",
        "playbooks/spam-injection.md",
        "playbooks/fake-googlebot.md",
        "playbooks/xmlrpc-flood.md",
        "playbooks/admin-lockout.md",
        "playbooks/woo-checkout-broken.md",
        "playbooks/multisite-compromise.md",
        "playbooks/wordfence-migration.md",
        "scripts/ability-client.php",
        "scripts/inventory.php",
        "scripts/db-audit.php",
        "scripts/user-audit.php",
        "scripts/cron-audit.php",
        "scripts/hash-evidence.php",
        "scripts/hash-evidence.sh",
        "scripts/integrity-check.php",
        "scripts/build-timeline.php",
        "scripts/malware-scan.php",
    ]
    for rel in required:
        if not (skill_dir / rel).exists():
            fail(f"missing payload file: {rel}")


def main() -> int:
    global root, skill_dir
    parser = argparse.ArgumentParser(description="Validate the wordpress-security-ops skill.")
    parser.add_argument("--root", type=Path, default=DEFAULT_ROOT)
    args = parser.parse_args()
    root = args.root.resolve()
    skill_dir = root / "skills" / SKILL_NAME

    if not skill_dir.exists():
        fail(f"missing skill directory: skills/{SKILL_NAME}")

    text_files = iter_project_text_files()
    for path in text_files:
        check_encoding(path)
        check_dashes(path)

    if skill_dir.exists():
        check_skill()
        check_ability_catalog()
    version = ""
    if (skill_dir / "SKILL.md").exists():
        _, meta = parse_frontmatter(read_text(skill_dir / "SKILL.md"))
        version = meta.get("version", "")
    check_version_files(version)
    check_installer()
    check_payload_files()

    if errors:
        print("wordpress-security-ops validator")
        print("\nErrors:")
        for message in errors:
            print(f"  - {message}")
        print(f"\nFAILED: {len(errors)} error(s)")
        return 1
    print("wordpress-security-ops validator")
    print("OK: all structural checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
