.PHONY: check validate test lint

check: validate test lint

validate:
	python tests/validate_skill.py

test:
	python -m unittest discover -s tests -p "test_*.py"

lint:
	find skills -name "*.php" -print0 | xargs -0 -r -n1 php -l
