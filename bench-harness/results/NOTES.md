# Step 4.0 baseline findings (Callgrind Ir)

Builds: `master` = `9498bc3ee13` (reify merge-base), `reify` = HEAD + counter
instrumentation. Both release, `--with-valgrind`. Metric = Callgrind
instruction counts (deterministic). Data: `baseline.json`. Warmup for warm
phase = 5. See `../README.md` for method.

## 1. Tax on code that declares NO generics (canary = Zend/bench.php)

Identical non-generic PHP, reify vs master:

| phase | JIT | reify − master |
|-------|-----|----------------|
| cold | off | **+1.62%** |
| cold | on  | **+2.05%** |
| warm | off | **+1.60%** |
| warm | on  | **+2.02%** |

**Not zero.** The reify branch adds ~1.6–2% instructions even to code that
declares no generics (extra work on shared call/link paths). This is small but
real and reproducible — it directly informs Nikita's "zero cost for non-generic
code" objection and should be stated honestly in the RFC (and is a Phase 5/6
target: get the untaxed path back toward 0%).

## 2. Cost of USING generics (generic_request vs its hand-written twin)

Same workload written with native generics vs plain classes:

| phase | JIT | vs plain on reify | vs plain on master (today's PHP) |
|-------|-----|-------------------|----------------------------------|
| cold | off | +3.3% | +7.5% |
| cold | on  | +8.7% | +12.7% |
| warm | off | +7.8% | +10.4% |
| **warm** | **on** | **+25.1%** | **+27.3%** |

**Warm + JIT is the production case and the worst case.** JIT makes the plain
version much cheaper (it JITs cleanly) but barely helps the generic version
(monomorphs share the template op_array and are skipped by JIT), so the relative
gap widens to ~25%. This is the single clearest pointer to **Phase 5 item 7
(re-enable JIT for monomorphs)** — the headline optimization opportunity. Note
also that warm does NOT amortise the generic cost the way it amortises
compilation, because runtime monomorphs are re-synthesized each request (not
persisted to SHM).

## 3. Cold vs warm shape

Compilation dominates cold: e.g. plain_request jit-on drops 26.6M (cold) → 8.3M
(warm); generic_request 30.0M → 10.5M. Warm is ~3x cheaper and is the number
that matters for deployed code.

## 4. Monomorph counters

`new Box::<int>()` → `class_monomorphs`+1, `type_arg_tables`+1. Non-generic code
→ all 0. Pure-passthrough generic function calls allocate no runtime table (all
0) — an efficiency finding, not a gap. See README instrumentation table.
