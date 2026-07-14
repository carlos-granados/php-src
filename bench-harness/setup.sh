#!/usr/bin/env bash
#
# One-time environment setup for the benchmark harness.
# Run from the php-src root:  bench-harness/setup.sh
#
# valgrind + pkg-config + build toolchain are already present; this installs
# what is missing and then builds the pinned release binaries. composer
# (+ curl/unzip) is needed only for Pass 2 (Psl/BCC) but is installed now so the
# environment is complete.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"

echo "=== apt: missing tooling ==="
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends curl unzip git ca-certificates >/dev/null
# perf is optional (needs host cap_add + perf_event_paranoid); best-effort.
apt-get install -y --no-install-recommends linux-perf >/dev/null 2>&1 || echo "  (linux-perf unavailable — perf profiling skipped)"

if ! command -v composer >/dev/null 2>&1; then
  echo "=== installing composer ==="
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

echo "=== sanity ==="
for t in valgrind pkg-config composer git; do
  printf '  %-12s %s\n' "$t" "$(command -v "$t" || echo MISSING)"
done
pkg-config --exists valgrind && echo "  valgrind.pc  $(pkg-config --modversion valgrind)" || { echo "valgrind.pc MISSING"; exit 1; }

echo "=== building pinned release binaries ==="
"$HERE/build.sh"

echo
echo "Setup complete. Next: bench-harness/run-baselines.sh"
