#!/bin/sh
# POSIX fallback for hash-evidence.php.
#
# Outputs JSON Lines, one file per line. Use hash-evidence.php for the
# schema-compliant manifest object.
#
# Usage:
#   ./hash-evidence.sh /path/to/site [out.jsonl]
#
# Requires: find, sha256sum or shasum, stat.

set -eu

ROOT="${1:-.}"
OUT="${2:-}"

if [ ! -d "$ROOT" ]; then
  echo "error: not a directory: $ROOT" >&2
  exit 2
fi

if command -v sha256sum >/dev/null 2>&1; then
  HASH_CMD="sha256sum"
elif command -v shasum >/dev/null 2>&1; then
  HASH_CMD="shasum"
else
  echo "error: sha256sum or shasum is required" >&2
  exit 2
fi

sha256_file() {
  if [ "$HASH_CMD" = "sha256sum" ]; then
    sha256sum "$1"
  else
    shasum -a 256 "$1"
  fi
}

json_escape() {
  printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}

collect() {
  find "$ROOT" -type f \
    ! -path '*/.git/*' \
    ! -path '*/node_modules/*' \
    -print0 |
  while IFS= read -r -d '' file; do
    rel="${file#"$ROOT"/}"
    hash_line="$(sha256_file "$file")"
    hash="${hash_line%% *}"
    size="$(stat -c '%s' "$file" 2>/dev/null || stat -f '%z' "$file")"
    mtime="$(stat -c '%Y' "$file" 2>/dev/null || stat -f '%m' "$file")"
    iso="$(date -u -d "@$mtime" '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date -u -r "$mtime" '+%Y-%m-%dT%H:%M:%SZ')"
    now="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf '{"path":"%s","sha256":"%s","size":%s,"mtime":"%s","collected_at":"%s","collector":"hash-evidence.sh","source":"filesystem","redaction":"none"}\n' \
      "$(json_escape "$rel")" "$hash" "$size" "$iso" "$now"
  done
}

if [ -n "$OUT" ]; then
  collect > "$OUT"
  chmod 600 "$OUT" 2>/dev/null || true
  echo "wrote: $OUT"
else
  collect
fi
