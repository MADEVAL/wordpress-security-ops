import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CATALOG = ROOT / "skills" / "wordpress-security-ops" / "references" / "ability-catalog.md"


class AbilityCatalogTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.text = CATALOG.read_text(encoding="utf-8")
        cls.names = set(re.findall(r"`botblocker/([a-z0-9-]+)`", cls.text))

    def test_unique_ability_count(self):
        self.assertEqual(len(self.names), 48)

    def test_sections_have_expected_rows(self):
        public = self.text.split("## Public Read-Only", 1)[1].split("## PII Read-Only", 1)[0]
        pii = self.text.split("## PII Read-Only", 1)[1].split("## Manage", 1)[0]
        manage = self.text.split("## Manage", 1)[1].split("## Extended Abilities", 1)[0]
        self.assertEqual(len(re.findall(r"^\| `botblocker/", public, re.M)), 26)
        self.assertEqual(len(re.findall(r"^\| `botblocker/", pii, re.M)), 8)
        self.assertEqual(len(re.findall(r"^\| `botblocker/", manage, re.M)), 14)

    def test_catalog_matches_current_build(self):
        self.assertEqual(len(re.findall(r"^\| `botblocker/", self.text, re.M)), 48)
        self.assertIn("defines 48 abilities", self.text)
        self.assertNotIn("proposed", self.text.lower())

    def test_known_ability_present(self):
        for name in (
            "get-health-score",
            "get-protection-snapshot",
            "get-audit-log",
            "get-top-ips",
            "delete-ip-rules",
            "apply-preset",
            "get-security-headers",
        ):
            self.assertIn(name, self.names)

    def test_live_confirm_tokens(self):
        expected = {
            "block-ip": "`BLOCK`",
            "unblock-ip": "`UNBLOCK`",
            "delete-ip-rule": "`DELETE`",
            "delete-ip-rules": "`DELETE`",
            "set-country-rule": "`COUNTRY`",
            "set-asn-rule": "`ASN`",
            "update-threat-data": "`UPDATE`",
            "apply-preset": "`PRESET`",
            "run-malware-scan": "`SCAN`",
        }
        for name, token in expected.items():
            row = re.search(rf"^\| `botblocker/{name}` \|([^\n]+)$", self.text, re.M)
            self.assertIsNotNone(row, name)
            self.assertIn(token, row.group(1))
        for name in ("upsert-ip-rule", "set-captcha-mode"):
            row = re.search(rf"^\| `botblocker/{name}` \|([^\n]+)$", self.text, re.M)
            self.assertIsNotNone(row, name)
            self.assertIn("| - |", row.group(1))

    def test_no_duplicate_rows(self):
        rows = re.findall(r"^\| `botblocker/([a-z0-9-]+)`", self.text, re.M)
        self.assertEqual(len(rows), len(set(rows)))


if __name__ == "__main__":
    unittest.main()
