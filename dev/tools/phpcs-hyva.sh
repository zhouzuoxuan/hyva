#!/usr/bin/env bash
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHPCS="$ROOT_DIR/hyva-coding-standard/vendor/bin/phpcs"
RULESET="$ROOT_DIR/phpcs.xml.dist"

if [ ! -x "$PHPCS" ]; then
  echo "ERROR: phpcs not found: $PHPCS"
  exit 1
fi

if [ $# -gt 0 ]; then
  "$PHPCS" --standard="$RULESET" "$@"
else
  "$PHPCS" --standard="$RULESET"
fi
