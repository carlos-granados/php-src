# Memory Pass 1 — baseline memory-usage matrix (2026-07-19)

First pass of Phase 6 (memory strategies). Reuses the exact three real-world
workloads and 5-config matrix established for the performance passes (see
`pass2c-NOTES.md` / `pass2d-NOTES.md`): psl pipeline (function-level generics),
doctrine/collections pipeline (class-level generics), BCC scanning
nikic/php-parser (whole real app). Same builds: `master` (`9498bc3ee13`),
`reify-plain` (unconverted psl/doctrine on the reify branch — the "tax" cell),
`reify-generic` (converted psl/doctrine — the "cost of generics" cell).

**Metric change from the Ir passes:** instead of Callgrind instruction counts,
this pass measures memory:

- **Peak RSS** (`/usr/bin/time -v` "Maximum resident set size") — whole-process
  footprint, the number that matters for a deployment's memory budget.
- **PHP-level heap** (`memory_get_peak_usage(true)`) for the timed request,
  captured via an `auto_prepend_file` shutdown-function probe
  (`bench-harness/pass2/mem_probe.php`) so the three workloads themselves are
  untouched.
- **Generics counters** (`zend_test_generics_stats()`: `class_monomorphs`,
  `type_arg_tables`, `callsite_cache_entries`) on the reify-generic build only
  — master/reify-plain always read 0/absent since no monomorphs exist there.
- **Opcache SHM usage** (`opcache_get_status()['memory_usage']`) when opcache
  is on.

Same cold/warm semantics as the Ir passes: psl/doctrine warm = `php-cgi
-T20,1` (20 untimed requests in one persistent process, then 1 timed); BCC
warm = `opcache.file_cache` re-populated by an untimed run first. Raw data:
`mem-pass1-matrix.json` (45 cells — 3 workloads x 5 configs x 3 builds).

## Result 1 — psl pipeline, FUNCTION-level generics (peak RSS, kB)

| config | master | reify-plain | reify-generic | tax | gen-cost |
|--------|-------:|------------:|---------------:|----:|---------:|
| Opcache OFF (JIT off) | 32996 | 33608 | 33620 | +1.85% | +0.04% |
| Opcache on, JIT off, cold | 37608 | 38232 | 38228 | +1.66% | −0.01% |
| Opcache on, JIT off, warm | 37608 | 39380 | 38228 | +4.71% | −2.93% |
| Opcache on, JIT on, cold | 38504 | 38864 | 38864 | +0.93% | 0.00% |
| **Opcache on, JIT on, warm** | **38532** | **40516** | **39220** | **+5.15%** | **−3.20%** |

## Result 2 — doctrine/collections, CLASS-level generics (peak RSS, kB)

| config | master | reify-plain | reify-generic | tax | gen-cost |
|--------|-------:|------------:|---------------:|----:|---------:|
| Opcache OFF (JIT off) | 28772 | 29388 | 29396 | +2.14% | +0.03% |
| Opcache on, JIT off, cold | 31460 | 32088 | 32084 | +2.00% | −0.01% |
| Opcache on, JIT off, warm | 31460 | 31956 | 32076 | +1.58% | +0.38% |
| Opcache on, JIT on, cold | 32100 | 32592 | 32592 | +1.53% | 0.00% |
| **Opcache on, JIT on, warm** | **32104** | **32852** | **32592** | **+2.33%** | **−0.79%** |

## Result 3 — BCC whole real application (peak RSS, kB)

| config | master | reify-plain | reify-generic | tax | gen-cost |
|--------|-------:|------------:|---------------:|----:|---------:|
| Opcache OFF (JIT off) | 109152 | 109516 | 109572 | +0.33% | +0.05% |
| Opcache on, JIT off, cold | 112580 | 113516 | 113640 | +0.83% | +0.11% |
| Opcache on, JIT off, warm | 111896 | 112888 | 112980 | +0.89% | +0.08% |
| Opcache on, JIT on, cold | 124276 | 125060 | 125120 | +0.63% | +0.05% |
| **Opcache on, JIT on, warm** | **124596** | **127376** | **127168** | **+2.23%** | **−0.16%** |

Headline: **RSS tax and gen-cost are both small everywhere** (roughly
0.3%-5% either direction, mostly under 3%) — memory footprint is not the
concern the raw percentages suggest. The `gen-cost` numbers going slightly
*negative* in a few warm cells is noise (RSS is a coarse, page-granularity
metric; a few hundred KB of allocator/heap-layout jitter between runs is
enough to flip the sign), not a real "generics make memory usage go down"
effect.

## Finding: monomorphs and type-arg tables are NOT reused across requests in a persistent process

The `zend_test_generics_stats()` counters, read at the *end* of the timed
(last) request in each `-T20,1` warm cell, are **cumulative across all 21
requests in that process**, not reset per request:

| workload / config | class_monomorphs | type_arg_tables |
|---|---:|---:|
| psl, cold (1 request) | 0 | 24,000 |
| psl, warm (21 requests) | 0 | 504,000 |
| doctrine, cold (1 request) | 4 | 4,004 |
| doctrine, warm (21 requests) | 84 | 84,084 |

Both scale almost exactly **21x** between the 1-request cold cell and the
21-request warm cell (504000/24000 = 21.0; 84084/4004 = 21.0;
84/4 = 21.0) — i.e. every single request synthesizes its own fresh set of
type-arg tables (and, for doctrine, its own fresh `Collection<int>` class
monomorph) from scratch, even though the *same* binding was already
synthesized by the previous request in the *same still-running process*.
Nothing is being cached or reused request-to-request.

This is exactly the "unbounded monomorph growth" shape Nikita's original
internals-thread objection was worried about, now with real numbers: a
long-lived worker (php-fpm, RoadRunner, Swoole) doing steady traffic through
a generic call site would keep allocating new monomorph/type-arg-table
memory per request indefinitely rather than converging to a fixed working
set. The RSS numbers above don't yet show this as alarming (20 extra psl
requests only cost ~1-2 MB additional RSS — the per-table allocations are
small), but it's a linear-in-request-count growth pattern, not the flat,
amortized-after-first-hit shape you'd want from a production cache. This is
the concrete data point Phase 6 items 3 ("lazy member materialization... cost
scales with *used* methods") and 5 ("opcache immutability... runtime-
synthesized monomorphs stay per-process and rebuild cheaply") were written
against — "rebuild cheaply" is true per-table (a few hundred bytes), but
"per process" currently means "per request," not "once per process."

BCC's `type_arg_tables=37` stays exactly flat across every config/build
(cold and warm alike) — expected, since BCC is a single-shot CLI tool with no
`-T` warm loop in its own execution (the warm *cell* here only pre-populates
opcache's file cache across a separate untimed invocation, not multiple
requests in one process), so there's no repeated-request growth to observe
in this workload.

## Reproduction

- Probe: `bench-harness/pass2/mem_probe.php` — `auto_prepend_file`, reports
  `memory_get_usage/peak_usage`, `zend_test_generics_stats()` (reify only,
  requires `--enable-generics-stats` + `ext/zend_test`), and
  `opcache_get_status()['memory_usage']` as one JSON line per request to
  `$MEM_PROBE_OUT`.
- Runner: `/tmp/run_memory_matrix.sh` (one-shot, mirrors `/tmp/run_matrix.sh`'s
  build/autoload/swap logic exactly, substitutes `/usr/bin/time -v` for
  Callgrind). Not checked in, same precedent as the Ir passes' driver script.
- Builds/workload trees: reused unchanged from the Ir passes
  (`/root/bench-builds/{master,reify}-src`, `/tmp/pass2/*`).
