#!/usr/bin/env bash
#
# Pass 2 measurement: Callgrind Ir of a real-world psl consumer (Mirtes BCC)
# scanning nikic/php-parser, across three cells:
#
#   master              : baseline (reify's merge-base)
#   reify_unconverted   : reify branch, psl as-is  -> TAX on real non-generic code
#   reify_converted     : reify branch, psl Vec/Dict/Iter converted to native
#                         generics (call sites unchanged via defaulted type
#                         params) -> COST OF USING GENERICS
#
# Deltas: (reify_unconverted - master) = real-world tax;
#         (reify_converted - reify_unconverted) = cost of using generics.
#
# Run from php-src root after setup.sh:  bench-harness/pass2/measure.sh
set -euo pipefail

WORK="${PASS2_WORK:-/tmp/pass2}"
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BUILDROOT="${BUILDROOT:-${TMPDIR:-/tmp}/bench-builds}"
OUT="$SRC/bench-harness/results"
mkdir -p "$OUT"
export COMPOSER_CACHE_DIR="$WORK/composer-cache"

BIN="$WORK/mbcc-repo/bin/roave-backward-compatibility-check"
PSL="$WORK/mbcc-repo/vendor/azjezz/psl/src/Psl"
CMD=(roave-backwards-compatibility-check:assert-backwards-compatible --from=v5.7.0 --to=v5.8.0)

# ir <build> — Callgrind Ir of one full BCC scan (opcache off = single cold CLI run)
ir() {
  local cli="$BUILDROOT/$1-src/sapi/cli/php"
  ( cd "$WORK/phpparser" && valgrind --tool=callgrind --callgrind-out-file=/tmp/p2-$1.out -- \
      "$cli" -d error_reporting=0 -d display_errors=0 -d opcache.enable_cli=0 \
      "$BIN" "${CMD[@]}" >/dev/null 2>/tmp/p2-$1.err )
  grep -oE 'Collected : [0-9]+' /tmp/p2-$1.err | grep -oE '[0-9]+' | head -1
}

use_psl() { rm -rf "$PSL" && cp -r "$WORK/$1" "$PSL"; }  # $1 = psl-orig | psl-converted

# Build the converted psl variant if absent.
if [ ! -d "$WORK/psl-converted" ]; then
  use_psl psl-orig
  php "$SRC/bench-harness/pass2/convert-psl.php" "$PSL" Vec Dict Iter >/dev/null
  cp -r "$PSL" "$WORK/psl-converted"
fi

echo "=== master (baseline) ==="
use_psl psl-orig
MASTER=$(ir master)
echo "=== reify, unconverted psl (tax) ==="
REIFY_U=$(ir reify)
echo "=== reify, converted psl (cost of generics) ==="
use_psl psl-converted
REIFY_C=$(ir reify)
use_psl psl-orig   # leave pristine

php -r '
$m=(int)$argv[1]; $ru=(int)$argv[2]; $rc=(int)$argv[3];
$pct=fn($a,$b)=>$b? round(100*($a-$b)/$b,3):null;
$out=[
  "metric"=>"callgrind_ir",
  "workload"=>"ondrejmirtes/backward-compatibility-check scanning nikic/php-parser v5.7.0->v5.8.0",
  "note"=>"psl on BCC hot path; converted = Vec/Dict/Iter native generics (defaulted, call sites unchanged)",
  "ir"=>["master"=>$m,"reify_unconverted"=>$ru,"reify_converted"=>$rc],
  "real_world_tax_pct"=>$pct($ru,$m),
  "cost_of_using_generics_pct"=>$pct($rc,$ru),
  "converted_vs_master_pct"=>$pct($rc,$m),
];
file_put_contents(getenv("OUTFILE"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
' "$MASTER" "$REIFY_U" "$REIFY_C" OUTFILE="$OUT/pass2-bcc.json"

echo; echo "Wrote $OUT/pass2-bcc.json"; cat "$OUT/pass2-bcc.json"
