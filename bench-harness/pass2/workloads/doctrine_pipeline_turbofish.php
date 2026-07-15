<?php
/**
 * Turbofish variant of doctrine_pipeline.php: the top-level instantiation carries
 * explicit type args (`new ArrayCollection::<int, int>(...)`), which synthesises
 * the ArrayCollection<int,int> class monomorph. The internal `new static()` in
 * map/filter/partition propagates that monomorph through the whole chain, so this
 * measures real class monomorphisation end to end.
 *
 * Requires the converted (native-generic) doctrine/collections. Env/argv as
 * doctrine_pipeline.php.
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
    $c        = new ArrayCollection::<int, int>($data);
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
