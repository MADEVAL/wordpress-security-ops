$ErrorActionPreference = "Stop"

Write-Host "Validator"
python tests/validate_skill.py
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Python tests"
python -m unittest discover -s tests -p "test_*.py"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "PHP lint"
Get-ChildItem -Path skills -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "Shellcheck"
if (Get-Command shellcheck -ErrorAction SilentlyContinue) {
    Get-ChildItem -Path skills -Recurse -Filter *.sh | ForEach-Object {
        shellcheck $_.FullName
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    }
} else {
    Write-Host "shellcheck not installed; skipping"
}

Write-Host "OK"
