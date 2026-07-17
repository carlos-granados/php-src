#!/bin/sh
# Config-matrix runner for the reified-generics test suite.
#
# Each opcache/JIT config is run twice: once on the default settings and once
# with opcache.file_update_protection=0. run-tests.php writes the .php test
# files immediately before executing them, so under the DEFAULT 2s protection
# window opcache compiles but never CACHES them — the "opcache" and "JIT"
# cells silently exercise only the uncached path (function-mode JIT compiles
# nothing at all for uncached scripts). Both paths matter: the uncached path
# covers per-request/arena monomorph lifetimes, the cached path covers SHM
# persistence and the code the JIT actually compiles. A whole family of
# function-JIT bugs (silent wrong-type acceptance on cached scripts) was
# invisible until the cached cells were added.
#
# Usage: Zend/tests/generics/run-matrix.sh [test-path ...]
#   (defaults to Zend/tests/generics; run from the php-src root or anywhere)

set -u

cd "$(dirname "$0")/../../.." || exit 1
PHP=${PHP:-sapi/cli/php}
TESTS=${*:-Zend/tests/generics}
FAILED=0

run() {
    desc=$1; shift
    printf '=== %s\n' "$desc"
    out=$("$PHP" run-tests.php -q -j8 -P "$@" $TESTS 2>&1)
    printf '%s\n' "$out" | grep -E '^Tests (failed|passed|leaked|warned)'
    if ! printf '%s\n' "$out" | grep -qE '^Tests failed +: +0 '; then
        printf '%s\n' "$out" | grep -E 'phpt\]$' | grep -v PASS
        FAILED=1
    fi
}

OPC="-d opcache.enable_cli=1"
JITT="$OPC -d opcache.jit=tracing -d opcache.jit_buffer_size=64M"
JITF="$OPC -d opcache.jit=function -d opcache.jit_buffer_size=64M"
FUP0="-d opcache.file_update_protection=0"

run "plain (no opcache)"
run "opcache, uncached path"        $OPC
run "opcache, cached path"          $OPC $FUP0
run "tracing JIT, uncached path"    $JITT
run "tracing JIT, cached path"      $JITT $FUP0
run "function JIT, uncached path"   $JITF
run "function JIT, cached path"     $JITF $FUP0

exit $FAILED
