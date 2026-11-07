.PHONY: check validate test lint shellcheck

check: validate test lint shellcheck

validate:
	python tests/validate_skill.py

test:
	python -m unittest discover -s tests -p "test_*.py"

lint:
	find skills -name "*.php" -print0 | xargs -0 -r -n1 php -l

shellcheck:
	@if command -v shellcheck >/dev/null 2>&1; then \
		find skills -name "*.sh" -print0 | xargs -0 -r -n1 shellcheck; \
	else \
		echo "shellcheck not installed; skipping"; \
	fi
