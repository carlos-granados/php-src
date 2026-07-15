<?php
/**
 * Targeted psl-collection runner: a realistic data pipeline that hammers the
 * Vec/Dict/Iter functions converted to native generics, so the per-call cost of
 * using generics is actually exercised (unlike a whole BCC scan, where these
 * calls are a tiny slice of the work).
 *
 * Same code runs on master (plain psl), reify-unconverted (plain psl) and
 * reify-converted (generic psl) — call sites are identical because the
 * conversion uses defaulted type parameters. Bounded loop so it completes under
 * Callgrind in reasonable time.
 *
 * CLI:      php psl_pipeline.php <autoload.php> [iterations]
 * php-cgi:  PSL_AUTOLOAD=<autoload.php> PSL_ITERS=<n> php-cgi -T<w>,<r> psl_pipeline.php
 *
 * Works under both SAPIs (php-cgi's `-T` re-runs the whole script per request,
 * which is how the warmed/not-warmed cells are measured; composer's autoload and
 * psl's function files use require_once, so re-entry is safe).
 */
declare(strict_types=1);

$args = $GLOBALS['argv'] ?? [];
$autoload = getenv('PSL_AUTOLOAD') ?: ($args[1] ?? null);
require $autoload;

use Psl\Vec;
use Psl\Dict;
use Psl\Iter;

$iterations = (int) (getenv('PSL_ITERS') ?: ($args[2] ?? 4000));

$data = range(1, 200);

$acc = 0;
for ($i = 0; $i < $iterations; $i++) {
    // Vec pipeline: map -> filter -> map
    $doubled  = Vec\map($data, static fn(int $n): int => $n * 2);
    $evens    = Vec\filter($doubled, static fn(int $n): bool => $n % 4 === 0);
    $labels   = Vec\map($evens, static fn(int $n): string => 'n' . $n);

    // Dict pipeline: build an assoc, filter, map values
    $dict     = Dict\associate($labels, $evens);
    $kept     = Dict\filter($dict, static fn(int $n): bool => $n > 100);
    $mapped   = Dict\map($kept, static fn(int $n): int => $n + 1);

    // Iter reductions
    $acc += Iter\count($mapped);
    $acc += Iter\contains($evens, 200) ? 1 : 0;
}

echo "acc=$acc\n";
