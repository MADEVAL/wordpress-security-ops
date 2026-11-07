import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
INSTALL = ROOT / "install.py"


class InstallerTests(unittest.TestCase):
    def test_list_targets(self):
        result = subprocess.run(
            [sys.executable, str(INSTALL), "--list"],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        for target in ("claude", "opencode", "agents", "codex", "project"):
            self.assertIn(target, result.stdout)

    def test_dry_run_writes_nothing(self):
        target = ROOT / ".opencode" / "skills" / "wordpress-security-ops"
        existed = target.exists()
        result = subprocess.run(
            [sys.executable, str(INSTALL), "--target", "project", "--dry-run"],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("would install", result.stdout)
        if not existed:
            self.assertFalse(target.exists())


if __name__ == "__main__":
    unittest.main()
