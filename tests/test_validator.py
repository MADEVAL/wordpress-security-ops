import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
VALIDATOR = ROOT / "tests" / "validate_skill.py"
BAD = ROOT / "tests" / "fixtures" / "bad-skill"


class ValidatorTests(unittest.TestCase):
    def test_main_repo_passes(self):
        result = subprocess.run(
            [sys.executable, str(VALIDATOR)],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_bad_fixture_fails(self):
        result = subprocess.run(
            [sys.executable, str(VALIDATOR), "--root", str(BAD)],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
        self.assertIn("FAILED", result.stdout)


if __name__ == "__main__":
    unittest.main()
