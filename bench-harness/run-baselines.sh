#!/usr/bin/env bash
#
# Step 4.0 baselines. Primary metric = Callgrind instruction counts (Ir):
# deterministic, pinning-free. Every cell is measured in TWO phases:
#
#   cold = php-cgi -T1        : one request, compilation included (cache miss).
#   warm = php-cgi -T<W>,1    : W untimed warmup requests compile+cache+JIT, then
#                               ONE instrumented request runs from opcache SHM.
#                               (Ir is deterministic, so a single timed run is
#                               exact; W only needs to reach steady state.)
#
# The warm number is the headline (production runs warm); cold shows first-hit
# cost. Requires binaries built WITH --with-valgrind (see build.sh) so the -T
# warmup phase is excluded via CALLGRIND_ZERO_STATS.
#
# Workloads:
#   canary        Zend/bench.php        broad non-generic; master vs reify -> tax
#   plain_request plain_request.php     non-generic twin; master vs reify + base
#                                       for the generic twin
#   generic_request generic_request.php generic version (reify only); compare to
#                                       plain_request on reify and on master
#
# Each applicable {build} x {jit off/on} x {cold/warm}. Run from the php-src
# root after build.sh:  bench-harness/run-baselines.sh
set -euo pipefail

# php-src root, derived from this script's location.
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILDROOT="${BUILDROOT:-${TMPDIR:-/tmp}/bench-builds}"
WARMUP="${WARMUP:-20}"
declare -A CGI=( [master]="$BUILDROOT/master-src/sapi/cgi/php-cgi" [reify]="$BUILDROOT/reify-src/sapi/cgi/php-cgi" )
declare -A CLI=( [master]="$BUILDROOT/master-src/sapi/cli/php" [reify]="$BUILDROOT/reify-src/sapi/cli/php" )
REIFY_CLI="${CLI[reify]}"
OUT=$SRC/bench-harness/results
mkdir -p "$OUT"

for b in master reify; do
  [[ -x "${CGI[$b]}" ]] || { echo "Missing ${CGI[$b]} — run build.sh first"; exit 1; }
  "${CGI[$b]}" -v 2>/dev/null | grep -q DEBUG && { echo "ERROR: $b is a DEBUG build"; exit 1; }
done

# ir <build> <phase:cold|warm> <jit:off|on> <script...>
ir() {
  local build="$1" phase="$2" jit="$3"; shift 3
  local targ; [[ "$phase" == warm ]] && targ="${WARMUP},1" || targ="1"
  local jitopt; [[ "$jit" == on ]] && jitopt="tracing" || jitopt="disable"
  valgrind --tool=callgrind --callgrind-out-file=/tmp/cg.out -- \
    "${CGI[$build]}" -T"$targ" \
    -d max_execution_time=0 -d opcache.enable=1 -d opcache.jit="$jitopt" \
    -d opcache.jit_buffer_size=128M -d opcache.validate_timestamps=0 \
    "$@" >/dev/null 2>/tmp/cg.err
  grep -oE 'Collected : [0-9]+' /tmp/cg.err | grep -oE '[0-9]+' | head -1
}

declare -A R   # R[workload,build,jit,phase] = Ir
measure() {     # <label> <script> <builds space-separated>
  local label="$1" script="$2" builds="$3"
  for build in $builds; do
    for jit in off on; do
      for phase in cold warm; do
        local v; v=$(ir "$build" "$phase" "$jit" "$script")
        R[$label,$build,$jit,$phase]="$v"
        printf '  %-16s %-7s jit=%-3s %-4s Ir=%s\n' "$label" "$build" "$jit" "$phase" "$v"
      done
    done
  done
}

echo "=== canary: Zend/bench.php (non-generic) ==="
measure canary "$SRC/Zend/bench.php" "master reify"
echo "=== plain_request (non-generic twin) ==="
measure plain "$SRC/bench-harness/workloads/plain_request.php" "master reify"
echo "=== generic_request (reify only) ==="
measure generic "$SRC/bench-harness/workloads/generic_request.php" "reify"

echo "=== micro: bench_reification.php --json on reify (Phase-5 per-section ref) ==="
"$REIFY_CLI" -d opcache.enable_cli=1 -d opcache.jit=disable "$SRC/bench/bench_reification.php" --json > "$OUT/micro-reify-jitoff.json" 2>/dev/null || echo "  (micro jit-off skipped)"
"$REIFY_CLI" -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=128M "$SRC/bench/bench_reification.php" --json > "$OUT/micro-reify-jiton.json" 2>/dev/null || echo "  (micro jit-on skipped)"

echo "=== generics_stats smoke (reify) ==="
GEN_STATS=$("$REIFY_CLI" -r '
class Box<T> { public function __construct(public T $v) {} }
function id<T>(T $x): T { return $x; }
new Box::<int>(1); new Box::<string>("x"); new Box::<int>(2);
id::<int>(5); id::<int>(6); id::<string>("y");
echo json_encode(zend_test_generics_stats());' 2>/dev/null | tail -1)
NONGEN_STATS=$("$REIFY_CLI" -r '
class Plain { public int $x = 1; } new Plain(); strlen("hi");
echo json_encode(zend_test_generics_stats());' 2>/dev/null | tail -1)
echo "  generic:     $GEN_STATS"
echo "  non-generic: $NONGEN_STATS"

# Assemble baseline.json using the reify binary.
export GEN_STATS NONGEN_STATS WARMUP OUTFILE="$OUT/baseline.json"
{
  for k in "${!R[@]}"; do echo "$k=${R[$k]}"; done
} > /tmp/ir_pairs.txt
"$REIFY_CLI" -r '
$R = [];
foreach (file("/tmp/ir_pairs.txt", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
    [$k,$v] = explode("=", $line, 2);
    [$wl,$build,$jit,$phase] = explode(",", $k);
    $R[$wl][$phase]["jit_$jit"][$build] = (int)$v;
}
// attach deltas where both master and reify exist
foreach ($R as $wl=>&$phases) foreach ($phases as $phase=>&$jits) foreach ($jits as $jk=>&$cell) {
    if (isset($cell["master"], $cell["reify"])) {
        $cell["reify_minus_master"] = $cell["reify"] - $cell["master"];
        $cell["delta_pct"] = $cell["master"] ? round(100*($cell["reify"]-$cell["master"])/$cell["master"],3) : null;
    }
}
unset($phases,$jits,$cell);
// cost-of-generics: generic vs plain, per phase/jit, on reify (and vs master plain)
$cog = [];
foreach (["cold","warm"] as $phase) foreach (["jit_off","jit_on"] as $jk) {
    $g = $R["generic"][$phase][$jk]["reify"] ?? null;
    $pr = $R["plain"][$phase][$jk]["reify"] ?? null;
    $pm = $R["plain"][$phase][$jk]["master"] ?? null;
    if ($g !== null) $cog[$phase][$jk] = [
        "generic_reify" => $g,
        "vs_plain_reify_pct" => $pr ? round(100*($g-$pr)/$pr,3) : null,
        "vs_plain_master_pct" => $pm ? round(100*($g-$pm)/$pm,3) : null,
    ];
}
$out = [
  "metric" => "callgrind_ir",
  "phases" => ["cold"=>"php-cgi -T1 (compile included)","warm"=>"php-cgi -T".getenv("WARMUP").",1 (from opcache)"],
  "builds" => ["master"=>"9498bc3ee13","reify"=>"reify-HEAD+instrumentation"],
  "workloads" => $R,
  "cost_of_generics" => $cog,
  "generics_stats_smoke" => [
    "generic_workload" => json_decode(getenv("GEN_STATS"), true),
    "non_generic_workload" => json_decode(getenv("NONGEN_STATS"), true),
  ],
];
file_put_contents(getenv("OUTFILE"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
' 2>/dev/null

echo
echo "Wrote $OUT/baseline.json"
cat "$OUT/baseline.json"
