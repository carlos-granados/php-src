<?php
/**
 * doctrine/collections driver (defaulted): builds ArrayCollections and calls
 * map/filter/partition/slice/contains in a bounded loop. The class-level analog
 * of psl_pipeline.php — exercises CLASS monomorphisation and method dispatch on
 * monomorph objects (where Pass 1 found the biggest JIT-warm penalty).
 *
 * Plain call sites (`new ArrayCollection(...)`): runs on plain doctrine (master/
 * reify-plain) and on converted doctrine (reify, where it yields the
 * <mixed,mixed> monomorph). See doctrine_pipeline_turbofish.php for the
 * explicit-monomorphisation variant.
 *
 * Env/argv (same as psl_pipeline.php): PSL_AUTOLOAD, PSL_ITERS.
 */
declare(strict_types=1);

$args = $GLOBALS['argv'] ?? [];
$autoload = getenv('PSL_AUTOLOAD') ?: ($args[1] ?? null);
require $autoload;

use Doctrine\Common\Collections\ArrayCollection;

$iterations = (int) (getenv('PSL_ITERS') ?: ($args[2] ?? 4000));

$data = range(1, 200);

$acc = 0;
for ($i = 0; $i < $iterations; $i++) {
    $c        = new ArrayCollection($data);
    $doubled  = $c->map(static fn(int $n): int => $n * 2);
    $evens    = $doubled->filter(static fn(int $n): bool => $n % 4 === 0);
    [$big, $small] = $evens->partition(static fn(int $k, int $n): bool => $n > 100);
    $sliced   = $evens->slice(0, 5);

    $acc += $evens->count();
    $acc += $big->count();
    $acc += count($sliced);
    $acc += $c->contains(100) ? 1 : 0;
}

echo "acc=$acc\n";
