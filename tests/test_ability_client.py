import json
import shutil
import subprocess
import threading
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

ROOT = Path(__file__).resolve().parent.parent
CLIENT = ROOT / "skills" / "wordpress-security-ops" / "scripts" / "ability-client.php"
PHP = shutil.which("php")


class Handler(BaseHTTPRequestHandler):
    def _record(self):
        self.server.last_method = self.command
        self.server.last_path = self.path
        self.server.last_auth = self.headers.get("Authorization", "")

    def _send(self, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        self._record()
        self._send({"ok": True, "method": "GET", "path": self.path})

    def do_DELETE(self):
        self._record()
        query = parse_qs(urlparse(self.path).query)
        raw = query.get("input", ["{}"])[0]
        self.server.last_input = json.loads(raw)
        self._send({"ok": True, "method": "DELETE", "input": self.server.last_input})

    def do_POST(self):
        self._record()
        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length).decode("utf-8") if length else "{}"
        self.server.last_body = raw
        self._send({"ok": True, "method": "POST"})

    def log_message(self, *args):
        pass


@unittest.skipIf(PHP is None, "php is not installed")
class AbilityClientTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.server.last_method = ""
        cls.server.last_path = ""
        cls.server.last_auth = ""
        cls.server.last_input = {}
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()
        cls.base = f"http://127.0.0.1:{cls.server.server_port}"

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def run_client(self, *args):
        return subprocess.run(
            [PHP, str(CLIENT), *args],
            capture_output=True,
            text=True,
            check=False,
        )

    def test_read_uses_get_and_basic_auth(self):
        result = self.run_client(
            "--transport=rest",
            f"--url={self.base}",
            "--allow-http",
            "--user=test",
            "--app-password=test",
            "--ability=botblocker/get-firewall-status",
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        data = json.loads(result.stdout)
        self.assertTrue(data["ok"])
        self.assertEqual(data["method"], "GET")
        self.assertEqual(self.server.last_method, "GET")
        self.assertTrue(self.server.last_auth.startswith("Basic "))
        self.assertNotIn("test:test", result.stdout)
        self.assertNotIn("Basic ", result.stdout)

    def test_dry_run_uses_delete_and_passes_confirm(self):
        result = self.run_client(
            "--transport=rest",
            f"--url={self.base}",
            "--allow-http",
            "--user=test",
            "--app-password=test",
            "--ability=botblocker/block-ip",
            "--write",
            "--dry-run",
            "--confirm=BLOCK",
            '--input={"ip":"203.0.113.10"}',
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(self.server.last_method, "DELETE")
        self.assertTrue(self.server.last_input.get("dry_run"))
        self.assertEqual(self.server.last_input.get("confirm"), "BLOCK")
        self.assertEqual(self.server.last_input.get("ip"), "203.0.113.10")

    def test_write_without_confirm_is_refused(self):
        before = self.server.last_method
        result = self.run_client(
            "--transport=rest",
            f"--url={self.base}",
            "--allow-http",
            "--user=test",
            "--app-password=test",
            "--ability=botblocker/block-ip",
            "--write",
            '--input={"ip":"203.0.113.10"}',
        )
        self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
        self.assertEqual(self.server.last_method, before)

    def test_default_mcp_write_is_refused(self):
        result = self.run_client(
            "--transport=mcp",
            f"--url={self.base}",
            "--allow-http",
            "--ability=botblocker/block-ip",
            "--write",
            "--confirm=BLOCK",
            '--input={"ip":"203.0.113.10"}',
        )
        self.assertEqual(result.returncode, 4, result.stdout + result.stderr)


if __name__ == "__main__":
    unittest.main()
