#!/bin/sh
# All checks. Run from the plugin folder:  sh tests/run.sh
set -e
for t in tests/schema-guard.php tests/rules.php tests/resend.php; do
  echo "== $t"
  php "$t" | tail -1
done
echo "== php lint"
find . -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "  all clean"
