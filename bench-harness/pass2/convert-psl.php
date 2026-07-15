<?php
/**
 * Convert psl's PHPDoc-generic functions to native reified generics, in place.
 *
 * psl expresses generics as `@template` docblock annotations over erased native
 * signatures (iterable/Closure/array). This injects real, DEFAULTED type
 * parameters derived from those annotations:
 *
 *     @template Tk / @template Tv / @template T
 *     function map(iterable $it, Closure $fn): array
 *   becomes
 *     function map<Tk = mixed, Tv = mixed, T = mixed>(iterable $it, Closure $fn): array
 *
 * Defaults (`= mixed`) mean psl's thousands of existing call sites keep working
 * WITHOUT turbofish, while every call now goes through the reify generic
 * machinery (type-arg binding per call) — which is exactly the runtime cost the
 * Pass 2 "cost of using generics" measurement isolates. Signatures and behaviour
 * are otherwise unchanged (all params mixed).
 *
 * Usage: php convert-psl.php <psl-src-Psl-dir> <Module> [<Module> ...]
 */
declare(strict_types=1);

$src = $argv[1] ?? null;
$modules = array_slice($argv, 2);
if ($src === null || !$modules) {
    fwrite(STDERR, "usage: php convert-psl.php <psl/src/Psl> <Module>...\n");
    exit(1);
}

$converted = 0;
$skippedNoTemplate = 0;
$skippedNoMatch = 0;
foreach ($modules as $mod) {
    foreach (glob("$src/$mod/*.php") as $file) {
        $code = file_get_contents($file);

        // Collect @template names in declaration order (drop any `of <bound>`).
        preg_match_all('/@template\s+([A-Za-z_][A-Za-z0-9_]*)/', $code, $m);
        $names = array_values(array_unique($m[1]));
        if (!$names) {
            $skippedNoTemplate++;
            continue;
        }

        $fname = basename($file, '.php');
        $params = implode(', ', array_map(static fn(string $n): string => "$n = mixed", $names));

        // Inject type params into `function <fname>(` (first occurrence only).
        $pattern = '/\bfunction\s+' . preg_quote($fname, '/') . '\s*\(/';
        $new = preg_replace($pattern, "function {$fname}<{$params}>(", $code, 1, $count);
        if ($count > 0) {
            file_put_contents($file, $new);
            $converted++;
        } else {
            $skippedNoMatch++;
        }
    }
}

echo "converted={$converted} skipped_no_template={$skippedNoTemplate} skipped_no_match={$skippedNoMatch}\n";
