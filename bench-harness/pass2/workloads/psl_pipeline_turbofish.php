<?php
/**
 * Turbofish variant of psl_pipeline.php: identical pipeline, but every call to a
 * (converted) generic psl function carries explicit type arguments
 * (`Vec\map::<int, int, int>(…)`). Concrete turbofish is resolved at compile
 * time, so this measures the MONOMORPHISATION path — distinct from
 * psl_pipeline.php's defaulted-generic calls, which do runtime type-arg binding.
 *
 * Requires the converted (native-generic) psl. Same env/argv handling as
 * psl_pipeline.php so it runs under both CLI and php-cgi `-T`.
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
    $doubled  = Vec\map::<int, int, int>($data, static fn(int $n): int => $n * 2);
    $evens    = Vec\filter::<int>($doubled, static fn(int $n): bool => $n % 4 === 0);
    $labels   = Vec\map::<int, int, string>($evens, static fn(int $n): string => 'n' . $n);

    $dict     = Dict\associate::<string, int>($labels, $evens);
    $kept     = Dict\filter::<string, int>($dict, static fn(int $n): bool => $n > 100);
    $mapped   = Dict\map::<string, int, int>($kept, static fn(int $n): int => $n + 1);

    $acc += Iter\count::<int>($mapped);
    $acc += Iter\contains::<int>($evens, 200) ? 1 : 0;
}

echo "acc=$acc\n";
