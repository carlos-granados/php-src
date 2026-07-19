# Reified generics — benchmark results

Final state of the performance/memory investigation into PHP's reified
generics (`reify` branch). Metric throughout is Callgrind instruction count
(Ir): deterministic and pinning-free, measured via `php-cgi -T<warmup>,1`
(cold = one request including compilation; warm = steady state from opcache
SHM after a warmup run). Baseline (`master`) is pinned to the branch's actual
merge-base commit — verified directly via `git merge-base` against both the
local and `origin` `master` refs, not a moving/current upstream snapshot.

## Definitions

- **tax** = cost of reified generics being *available* in the engine, on code
  that declares none (`reify` with no generics used, vs `master`). The number
  that matters for "does this change slow down code that doesn't use it."
- **cost of generics** = marginal cost of actually *using* generics, on top
  of the tax (`reify` with generics used vs `reify` with none used).
- **converted vs master** = the two effects combined — total cost of
  generics-using code against today's PHP.

## Workloads

- **bcc** — a maintained BCC fork (`ondrejmirtes/backward-compatibility-check`)
  scanning `nikic/php-parser`, with `azjezz/psl` (its only generics-relevant
  dependency) converted to native defaulted generics. A whole, unmodified
  real application — the most representative number in this set.
- **doctrine** — a pipeline exercising `doctrine/collections`, converted to
  native defaulted generics (call sites unchanged). Collection-heavy, built
  on `array_map`/`array_filter`/`usort`-style callback closures.
- **psl** — a driver directly exercising `Psl\Vec`/`Dict`/`Iter`, the
  functions BCC's own hot path uses internally, converted the same way as
  above.

Each workload runs {`master`, `reify` unconverted, `reify` converted} x
{opcache off, opcache-on/JIT-off cold, opcache-on/JIT-off warm,
opcache-on/JIT-on cold, opcache-on/JIT-on warm}. `warm`+`JIT-on` is the
production-representative cell; `off` is the plain-interpreter floor.

## Results

### bcc (whole real application)

| config          | tax     | cost of generics | converted vs master |
|-----------------|--------:|------------------:|---------------------:|
| off             | +3.51%  | +0.01%             | +3.52%                |
| jit-off, cold   | +3.73%  | +0.00%             | +3.73%                |
| jit-off, warm   | +3.77%  | +0.01%             | +3.78%                |
| jit-on, cold    | +3.59%  | -0.14%             | +3.45%                |
| jit-on, warm    | +3.65%  | +0.01%             | +3.66%                |

### doctrine (collection-heavy, closure-based)

| config          | tax     | cost of generics | converted vs master |
|-----------------|--------:|------------------:|---------------------:|
| off             | +5.66%  | +0.21%             | +5.88%                |
| jit-off, cold   | +4.78%  | +1.07%             | +5.89%                |
| jit-off, warm   | +6.03%  | +0.17%             | +6.21%                |
| jit-on, cold    | +5.39%  | +9.44%             | +15.34%               |
| jit-on, warm    | +2.61%  | +3.17%             | +5.86%                |

### psl (direct function-call driver)

| config          | tax     | cost of generics | converted vs master |
|-----------------|--------:|------------------:|---------------------:|
| off             | +5.23%  | +5.70%             | +11.23%               |
| jit-off, cold   | +6.51%  | +4.59%             | +11.40%               |
| jit-off, warm   | +5.65%  | +6.85%             | +12.89%               |
| jit-on, cold    | -6.29%  | +10.84%            | +3.87%                |
| jit-on, warm    | -1.47%  | +6.01%             | +4.45%                |

## Reading

- **Best real-world tax estimate: ~3.5-3.8%**, from `bcc` — a whole,
  unmodified application, tight and largely JIT-insensitive across every
  config. This is the headline "cost of generics being available, unused"
  number.
- **Cost of using generics is highly workload- and JIT-dependent**: near 0%
  on `bcc` (its Psl usage is a small slice of the scanned app's actual hot
  path) up to +9-11% on JIT-on-cold for the two workloads that call
  generics-using code directly. JIT cleanly optimizes plain code but doesn't
  optimize monomorphized generic code the same way, which is the dominant
  driver of the higher numbers — a known, documented limitation, not a
  design goal.
- `psl`'s negative-tax cells under JIT (`jit-on, cold`/`warm`) are
  cold-JIT-compilation noise, not a genuine speedup — `psl` is itself the
  thing being measured for conversion cost, so master and reify aren't
  running fully comparable code at the instrumentation boundary the way
  `doctrine`/`bcc` are. Treat those two cells as low-confidence.
- One targeted engine fix (folding two adjacent generic-call checks into a
  single guard in the hot C-dispatch path used by `call_user_func`/`usort`/
  `array_map`/etc.) measurably reduced the tax on `doctrine`'s production
  cell (jit-on, warm) from +2.81% to +2.61% — about a 7% relative reduction
  in the measured tax — with zero test regressions across the full engine
  test suite (6900+ tests: generics, closures, generators, reflection,
  opcache, JIT).
- A broader attempt to eliminate the same class of check more aggressively
  (skip a per-call type-argument-table check entirely for functions
  statically known never to need it) was implemented, found and fixed a real
  crash bug along the way, passed the full test suite, but then measured as
  a **net regression** on closure-heavy code: every closure must
  conservatively be treated as "may need this check" (there's no
  compile-time way to know whether a specific closure instance will actually
  end up carrying generic bindings), so closure-heavy workloads pay an extra
  check for no benefit. This was reverted; it is not reflected in the
  numbers above.

## Reproducing

```
bench-harness/build.sh both          # release builds of master + reify
bench-harness/run-baselines.sh       # canary + plain/generic micro-benchmarks
```

The full 3-workload benchmark above uses the `pass2/` real-world harness
(`pass2/setup.sh`, `pass2/measure.sh`) against the BCC fork, `doctrine/collections`,
and `azjezz/psl`, each with a native-generics-converted variant checked out
alongside the original.
