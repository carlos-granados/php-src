#!/usr/bin/env bash
#
# Pass 2 setup: real-world psl consumer (ondrejmirtes/backward-compatibility-check)
# scanning nikic/php-parser, with psl on BCC's runtime hot path.
#
# WHY this shape (learned the hard way):
#  - Modern Roave BCC dropped its psl dependency, so it can't drive a generics
#    measurement. The maintained Mirtes fork still uses psl (^2.0.4, ~104 call
#    sites: Str/Dict/Vec/Iter/Type) — the original "BC-checker uses psl" design.
#  - Our benchmark binaries are php 8.6-dev; mature tooling needs coaxing:
#      * Composer runs under Debian's php8.2 (needs ext-curl; the 8.6 binaries
#        also have curl now, but the 8.2 runner avoids version friction).
#      * BCC installs from its committed composer.lock (composer *require*
#        re-resolves to an incompatible better-reflection namespace).
#      * ondrejmirtes/better-reflection 6.3.0 redeclares reflection constants
#        untyped; php 8.6 typed the native ones -> patch them to `const int`.
#      * Scan target must be flat + dependency-free (psl v6 is a monorepo whose
#        paths don't resolve; php-parser is flat, dep-free, big API -> lots of
#        psl exercise, trivial in-process composer install).
#
# Run from the php-src root:  bench-harness/pass2/setup.sh
# Prereq: bench-harness/build.sh has produced the release binaries (they now
# include openssl/curl/bcmath/intl/sodium — required to run BCC + psl).
set -euo pipefail

WORK="${PASS2_WORK:-/tmp/pass2}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
mkdir -p "$WORK"
export COMPOSER_CACHE_DIR="$WORK/composer-cache"
export DEBIAN_FRONTEND=noninteractive

echo "=== apt: tooling + ext dev headers + Debian php (composer runner) ==="
apt-get update -qq
apt-get install -y --no-install-recommends \
  curl unzip git ca-certificates \
  libsodium-dev libcurl4-openssl-dev libicu-dev \
  php-cli php-curl php-mbstring php-xml php-zip >/dev/null

command -v composer >/dev/null || {
  echo "=== composer 2.7.9 (compatible with the php8.2 runner) ==="
  curl -sS https://getcomposer.org/download/2.7.9/composer.phar -o /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
}
RUNNER=/usr/bin/php8.2   # Composer runner (has ext-curl)

echo "=== clone Mirtes BCC + install from its lock ==="
[ -d "$WORK/mbcc-repo" ] || git clone --quiet https://github.com/ondrejmirtes/BackwardCompatibilityCheck.git "$WORK/mbcc-repo"
( cd "$WORK/mbcc-repo" && "$RUNNER" /usr/local/bin/composer install --ignore-platform-reqs --no-interaction -q )

echo "=== patch better-reflection adapters for php 8.6 typed reflection constants ==="
ADIR="$WORK/mbcc-repo/vendor/ondrejmirtes/better-reflection/src/Reflection/Adapter"
sed -i -E 's/public const ([A-Z_]+) = ([0-9]+);/public const int \1 = \2;/' \
  "$ADIR"/ReflectionClass.php "$ADIR"/ReflectionProperty.php "$ADIR"/ReflectionClassConstant.php

echo "=== keep a pristine (unconverted) copy of psl for toggling ==="
PSL="$WORK/mbcc-repo/vendor/azjezz/psl/src/Psl"
[ -d "$WORK/psl-orig" ] || cp -r "$PSL" "$WORK/psl-orig"

echo "=== clone scan target: nikic/php-parser (flat, dep-free, large API) ==="
[ -d "$WORK/phpparser" ] || git clone --quiet https://github.com/nikic/PHP-Parser.git "$WORK/phpparser"

echo
echo "Setup complete. Measure with:  bench-harness/pass2/measure.sh"
