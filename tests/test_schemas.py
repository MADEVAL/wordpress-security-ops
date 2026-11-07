import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEMPLATES = ROOT / "skills" / "wordpress-security-ops" / "templates"


class SchemaTests(unittest.TestCase):
    def test_schemas_parse(self):
        for name in (
            "findings.schema.json",
            "action.schema.json",
            "evidence-manifest.schema.json",
            "run-manifest.schema.json",
        ):
            data = json.loads((TEMPLATES / name).read_text(encoding="utf-8"))
            self.assertEqual(data["$schema"], "http://json-schema.org/draft-07/schema#")
            self.assertTrue(data["title"])
            self.assertEqual(data["type"], "object")

    def test_findings_required_fields(self):
        schema = json.loads((TEMPLATES / "findings.schema.json").read_text(encoding="utf-8"))
        required = set(schema["properties"]["findings"]["items"]["required"])
        expected = {
            "id",
            "title",
            "severity",
            "confidence",
            "exposure",
            "impact",
            "priority",
            "status",
            "source",
        }
        self.assertEqual(required, expected)

    def test_action_required_fields(self):
        schema = json.loads((TEMPLATES / "action.schema.json").read_text(encoding="utf-8"))
        required = set(schema["properties"]["actions"]["items"]["required"])
        self.assertEqual(required, {"id", "title", "channel", "risk", "status"})

    def test_evidence_required_fields(self):
        schema = json.loads((TEMPLATES / "evidence-manifest.schema.json").read_text(encoding="utf-8"))
        required = set(schema["properties"]["items"]["items"]["required"])
        expected = {"path", "sha256", "size", "mtime", "collected_at", "source", "redaction"}
        self.assertEqual(required, expected)


if __name__ == "__main__":
    unittest.main()
