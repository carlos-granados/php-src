# bench-harness — reified-generics benchmark harness (Phase 4)

Reproducible instruction-count benchmarks comparing the `reify` branch against
its `master` merge-base, plus the monomorph-counter instrumentation.

All paths here are relative to the php-src root. Release builds are written to a
scratch dir outside the source tree (`$BUILDROOT`, default `$TMPDIR/bench-builds`
or `/tmp/bench-builds`) so they don't bloat `git status` or slow the build —
they're ephemeral and recreated by `build.sh`. The `results/*.json` outputs are
generated (and `.gitignore`d) — regenerate them with `run-baselines.sh`; the
curated findings are kept in the tracked `results/NOTES.md`.

## Metric: Callgrind instruction counts (Ir)

Wall-clock is unusable here (no `hyperfine`, and macOS Docker has no CPU
pinning). We use **Callgrind `Ir`** — deterministic and pinning-free, the same
method `php-src/benchmark/benchmark.php` already uses. A number like "+7% Ir" is
also a cleaner RFC figure than a noisy millisecond delta.

## Cold vs warm (the key distinction)

Production PHP runs with a **warm** opcache: code is compiled once and served
from SHM thereafter. We measure both phases via php-cgi's `-T` benchmark loop,
which integrates with Callgrind (`sapi/cgi/cgi_main.c`):

| phase | invocation | what Ir includes |
|-------|------------|------------------|
| **cold** | `php-cgi -T1` | one request **including compilation** (cache miss) |
| **warm** | `php-cgi -T<W>,1` | `W` untimed warmup requests compile+cache+JIT (Callgrind zeroed), then **one** timed request served from opcache |

**Warm is the headline** (that's how code runs in production); cold shows
first-hit cost. Because Ir is deterministic, one timed warm run is exact — `W`
only needs to reach steady state (opcache populated, JIT compiled).

> Requires binaries built **`--with-valgrind`** so the warmup phase emits
> `CALLGRIND_ZERO_STATS`. Without it the macros compile out and Callgrind counts
> every `-T` iteration (warm would read *higher* than cold). `build.sh` sets it.

## Workloads

- **canary** — `php-src/Zend/bench.php`: broad non-generic PHP. `reify − master`
  ≈ 0 in both phases is the evidence that generics impose **no tax on code that
  declares none** (Nikita's objection).
- **plain_request** — `workloads/plain_request.php`: a hand-written non-generic
  class/function workload. Runs on both builds; the apples-to-apples base for…
- **generic_request** — `workloads/generic_request.php`: the same workload using
  native generics (reify only). Compared against `plain_request` on **reify**
  (cost of *using* generics vs hand-writing) and on **master** (cost vs today's
  PHP). Its warm number captures the per-request monomorph-synthesis cost that
  opcache does **not** amortize (runtime monomorphs aren't persisted to SHM).

## Instrumentation: `zend_test_generics_stats(): array`

**Opt-in at compile time.** The counters are gated behind
`--enable-generics-stats` (configure) → the `ZEND_GENERICS_STATS` macro. Without
it the `EG(generics_*)` fields, every increment, and the whole
`zend_test_generics_stats()` function (arginfo, registration, and body) all
compile out, so **default/production builds carry zero instrumentation** — the
function is not even defined (`function_exists()` is false).
`build.sh` adds the flag to the **reify** build only. Because the increments sit
on cold paths (monomorph synthesis, cache install — never per-call), enabling
them does not perturb the warm per-call Ir measurements; the non-generic canary
never touches them.

Process-lifetime counters (backed by `EG(generics_*)` in `Zend/zend_globals.h`,
present on `reify` builds via `--enable-zend-test --enable-generics-stats`):

| counter | increments in | measures |
|---------|---------------|----------|
| `class_monomorphs` | `zend_synthesize_monomorph` (after the cache-miss check) | distinct class monomorphs synthesized into `EG(class_table)` — the **unbounded-growth vector** Nikita raised |
| `type_arg_tables` | `zend_type_arg_table_alloc` | runtime `zend_type_arg_table` allocations |
| `callsite_cache_entries` | the cache-store paths in `zend_build_or_get_cached_type_args` / `zend_get_or_synthesize_call_monomorph` | per-call-site type-arg/monomorph caches installed |

**Observed behaviour (itself a finding):** a `new Box::<int>()` bumps
`class_monomorphs`+`type_arg_tables`; a pure-passthrough generic **function**
call like `id::<int>($x)` bumps **nothing** — turbofish function calls of that
shape resolve from the compile-time turbofish args with **no runtime table**, so
the counters correctly read 0. `callsite_cache_entries` therefore stays 0 for
explicit-turbofish-heavy code (the concrete fast path) and moves for
non-turbofish/`= T`-default call sites. Class monomorph growth is the counter to
watch for the memory story; function-call-path counters will get more exercise
in Pass 2 (Psl `Vec`/`Dict`/`Iter`).

## Usage

From the php-src root:

```sh
bench-harness/build.sh
bench-harness/run-baselines.sh
```

`build.sh` builds `master` (ref `9498bc3ee13`) and `reify` (HEAD + the
uncommitted instrumentation, applied as a patch — not committed) as release
worktrees. `run-baselines.sh` writes `results/baseline.json` (cold/warm × jit ×
build Ir with deltas + `cost_of_generics`) plus the micro per-section JSON and
the counter smoke test.

Env knobs: `WARMUP` (default 20) warm-phase warmup requests; `BUILDROOT`
(default `$TMPDIR/bench-builds` or `/tmp/bench-builds`); `MASTER_REF`, `REIFY_REF`.

## Pass 2 (deferred): Psl + BackwardCompatibilityCheck

The real-world headline (BCC scanning psl / doctrine-orm with a generics-native
Psl) is gated on this foundation and on `composer` (installed by `setup.sh`).
Same cold/warm Ir method, plus peak RSS and the `zend_test_generics_stats()`
counts.
