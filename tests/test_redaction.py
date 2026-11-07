import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LIB = ROOT / "skills" / "wordpress-security-ops" / "scripts" / "lib" / "redact.php"


def run_php(code: str):
    result = subprocess.run(
        ["php", "-r", code],
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0:
        raise AssertionError(result.stderr or result.stdout)
    return json.loads(result.stdout)


class RedactionTests(unittest.TestCase):
    def test_masks_ip_email_and_login(self):
        code = (
            "require " + json.dumps(LIB.as_posix()) + ";"
            "$out = ["
            "'ip' => secops_mask_ip('203.0.113.10'),"
            "'email' => secops_mask_email('admin@example.com'),"
            "'login' => secops_mask_login('administrator')"
            "]; echo json_encode($out);"
        )
        data = run_php(code)
        self.assertEqual(data["ip"], "203.0.*.*")
        self.assertEqual(data["email"], "a***@example.com")
        self.assertEqual(data["login"], "a***r")

    def test_sensitive_keys(self):
        code = (
            "require " + json.dumps(LIB.as_posix()) + ";"
            "$out = ["
            "'password' => secops_sensitive_key('user_password'),"
            "'author' => secops_sensitive_key('author'),"
            "'token' => secops_sensitive_key('api_token')"
            "]; echo json_encode($out);"
        )
        data = run_php(code)
        self.assertTrue(data["password"])
        self.assertFalse(data["author"])
        self.assertTrue(data["token"])

    def test_redacts_nested_secret(self):
        code = (
            "require " + json.dumps(LIB.as_posix()) + ";"
            "$out = secops_redact(['password' => 'secret', 'author' => 'alice', 'nested' => ['api_key' => 'x']]);"
            "echo json_encode($out);"
        )
        data = run_php(code)
        self.assertEqual(data["password"], "[REDACTED]")
        self.assertEqual(data["author"], "alice")
        self.assertEqual(data["nested"]["api_key"], "[REDACTED]")

    def test_masks_bearer_token(self):
        code = (
            "require " + json.dumps(LIB.as_posix()) + ";"
            "$out = secops_mask_text('Authorization: Bearer abc123', false);"
            "echo json_encode($out);"
        )
        data = run_php(code)
        self.assertIn("[REDACTED]", data)
        self.assertNotIn("abc123", data)

    def test_masks_pii_text(self):
        code = (
            "require " + json.dumps(LIB.as_posix()) + ";"
            "$out = secops_mask_text('from 203.0.113.10 by admin@example.com', true);"
            "echo json_encode($out);"
        )
        data = run_php(code)
        self.assertIn("203.0.*.*", data)
        self.assertIn("a***@example.com", data)


if __name__ == "__main__":
    unittest.main()
