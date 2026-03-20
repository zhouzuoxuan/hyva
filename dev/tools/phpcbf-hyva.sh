#!/usr/bin/env bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHPCBF="$ROOT_DIR/hyva-coding-standard/vendor/bin/phpcbf"
RULESET="$ROOT_DIR/phpcs.xml.dist"

if [ ! -x "$PHPCBF" ]; then
  echo "ERROR: phpcbf not found: $PHPCBF"
  exit 1
fi

if [ $# -gt 0 ]; then
  "$PHPCBF" --standard="$RULESET" "$@"
else
  "$PHPCBF" --standard="$RULESET"
fi
