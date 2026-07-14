#!/usr/bin/env bash
#
# Build the two pinned RELEASE binaries used for benchmarking, WITHOUT touching
# the user's in-tree --enable-debug dev build.
#
#   - master : merge-base 9498bc3ee13 (clean, no generics)  -> $BUILDROOT/master-src
#   - reify  : current reify HEAD + the uncommitted monomorph-counter            .
#              instrumentation (applied as a patch, not committed)  -> $BUILDROOT/reify-src
#
# Each is a detached git worktree of the php-src root, so the primary working
# tree and its debug objects are left completely untouched. Builds live under
# $BUILDROOT (fast, ephemeral); results are written in-tree under bench-harness/
# by run-baselines.sh.
#
# Run from the php-src root:  bench-harness/build.sh [master|reify|both]
set -euo pipefail

# php-src root, derived from this script's location (bench-harness/build.sh).
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILDROOT="${BUILDROOT:-${TMPDIR:-/tmp}/bench-builds}"
MASTER_REF="${MASTER_REF:-9498bc3ee13}"
REIFY_REF="${REIFY_REF:-reify}"
JOBS="$(nproc)"

# Release flags. Symmetric across both builds for a fair instruction-count
# comparison; opcache (hence JIT) required; zend_test provides
# zend_test_generics_stats() on the reify build; --with-valgrind is REQUIRED so
# php-cgi's `-T warmup,repeat` emits the CALLGRIND_ZERO_STATS client request that
# excludes warmup (compile+JIT) from the counted runs — without it the warm/cold
# split silently collapses (callgrind counts every -T iteration).
CONFIGURE_FLAGS="--disable-debug --enable-opcache --enable-zend-test --enable-cgi --enable-mbstring --with-valgrind"
# --enable-generics-stats compiles in the monomorph counters; only meaningful on
# reify (master has no generics and no such configure flag), so it is added per
# build below, not to the shared flags.
REIFY_EXTRA_FLAGS="--enable-generics-stats"

# Tracked files carrying the (uncommitted) instrumentation. --binary so the
# generated *_arginfo.h byte changes apply cleanly in the fresh worktree.
INSTRUMENTATION_FILES=(
  configure.ac
  Zend/zend_globals.h
  Zend/zend_inheritance.c
  Zend/zend_opcode.c
  Zend/zend_compile.c
  ext/zend_test/test.c
  ext/zend_test/test.stub.php
  ext/zend_test/test_arginfo.h
  ext/zend_test/test_decl.h
  ext/zend_test/test_legacy_arginfo.h
)

build_one() {
  local name="$1" ref="$2" apply_instrumentation="$3" extra_flags="${4:-}"
  local dir="$BUILDROOT/${name}-src"

  echo "=== [$name] worktree @ $ref -> $dir ==="
  git -C "$SRC" worktree remove --force "$dir" 2>/dev/null || rm -rf "$dir"
  git -C "$SRC" worktree add --detach "$dir" "$ref"

  if [[ "$apply_instrumentation" == "yes" ]]; then
    echo "=== [$name] applying uncommitted instrumentation patch ==="
    git -C "$SRC" diff --binary HEAD -- "${INSTRUMENTATION_FILES[@]}" \
      | git -C "$dir" apply --index
  fi

  echo "=== [$name] buildconf + configure ==="
  ( cd "$dir" && ./buildconf --force >/dev/null 2>&1 && \
      ./configure $CONFIGURE_FLAGS $extra_flags >/tmp/${name}-configure.log 2>&1 )
  echo "=== [$name] make -j$JOBS ==="
  ( cd "$dir" && make -j"$JOBS" >/tmp/${name}-build.log 2>&1 )

  echo "=== [$name] built: $("$dir"/sapi/cli/php -v | head -1) ==="
  if "$dir"/sapi/cli/php -v | head -1 | grep -q DEBUG; then
    echo "ERROR: $name is a DEBUG build"; exit 1
  fi
}

# Optional target selection: master | reify | both (default both).
TARGET="${1:-both}"
mkdir -p "$BUILDROOT"
[[ "$TARGET" == both || "$TARGET" == master ]] && build_one master "$MASTER_REF" no
[[ "$TARGET" == both || "$TARGET" == reify  ]] && build_one reify  "$REIFY_REF"  yes "$REIFY_EXTRA_FLAGS"

echo
echo "All release binaries built:"
echo "  master : $BUILDROOT/master-src/sapi/cli/php"
echo "  reify  : $BUILDROOT/reify-src/sapi/cli/php"
