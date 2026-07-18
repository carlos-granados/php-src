# Pass 2c — real-world trio re-run after the non-generic-tax optimizations (2026-07-18)

Re-run of the full Pass-2b matrix (psl function-level generics, doctrine/collections
class-level generics, BCC whole-app scan) after landing two Phase 5 performance
items on top of the Pass-2b (SHM-monomorph) build:

- `38e52f1122d` "optimise non generic tax" — `ZEND_VERIFY_GENERIC_ARGUMENTS`
  fast-path: bails before computing arity/cache-slot/args-box for any call
  site with no turbofish and no generic callee (Phase 5 item 2).
- `6109c75fb98` "more optimisations to non generic tax" — naked call sites no
  longer emit a `VERIFY_GENERIC_ARGUMENTS` opcode at all; the check folds
  inline into `DO_FCALL`/`DO_UCALL`/`DO_FCALL_BY_NAME` (Phase 5 item 2b),
  with a matching JIT-side fix.

Master binary unchanged (`9498bc3ee13`). Metric = Callgrind Ir (php-cgi `-T`;
warm = `-T5,1` with Callgrind-zeroed warmup, cold = `-T1`; BCC warmed via
`opcache.file_cache`, re-populated per warm cell because the reify binary
changed). Data: `pass2c-psl-matrix.json`, `pass2c-doctrine.json`,
`pass2c-bcc-matrix.json`. Baseline for comparison: `pass2b-*.json` +
`pass2b-NOTES.md`.

## Result 1 — psl pipeline, FUNCTION-level generics (Vec/Dict/Iter, 1000 iters/request)

| config | master | reify-plain | defaulted | turbofish | tax | gen-cost | tf-cost |
|--------|-------:|------------:|----------:|----------:|----:|---------:|--------:|
| Opcache OFF (JIT off) | 677.0M | 710.8M | 751.7M | 749.9M | +4.99% | +5.75% | +5.50% |
| Opcache on, JIT off, cold | 696.5M | 729.0M | 770.7M | 768.8M | +4.68% | +5.72% | +5.45% |
| Opcache on, JIT off, warm | 512.1M | 541.7M | 582.8M | 580.8M | +5.77% | +7.59% | +7.23% |
| Opcache on, JIT on, cold | 617.2M | 639.4M | 656.8M | 674.9M | +3.60% | +2.71% | +5.55% |
| **Opcache on, JIT on, warm** | **416.6M** | **435.0M** | **452.1M** | **471.8M** | **+4.42%** | **+3.93%** | **+8.44%** |

**JIT-warm tax fell from +11.59% to +4.42%; generics (defaulted) cost fell from
+7.76% to +3.93%.** This is the direct, measured payoff of this session's two
optimizations on the workload that stresses per-call dispatch overhead
hardest (a tight Vec/Dict/Iter pipeline). Turbofish cost is now consistently
above defaulted cost across JIT-on configs (+8.44% vs +3.93% at JIT-warm) —
turbofish forces the per-call-site cached-args path rather than the
defaulted-only inline fast path added this session; worth a closer look in a
future pass.

## Result 2 — doctrine/collections, CLASS-level generics (1000 iters/request)

| config | master | reify-plain | defaulted | turbofish | tax | gen-cost | tf-cost |
|--------|-------:|------------:|----------:|----------:|----:|---------:|--------:|
| Opcache OFF (JIT off) | 286.5M | 302.5M | 303.5M | 303.6M | +5.57% | +0.34% | +0.38% |
| Opcache on, JIT off, cold | 271.6M | 286.8M | 287.8M | 287.9M | +5.58% | +0.37% | +0.38% |
| Opcache on, JIT off, warm | 228.4M | 242.6M | 243.4M | 243.5M | +6.20% | +0.34% | +0.37% |
| Opcache on, JIT on, cold | 246.8M | 259.5M | 278.0M | 275.6M | +5.11% | +7.15% | +6.23% |
| **Opcache on, JIT on, warm** | **193.3M** | **204.5M** | **226.9M** | **228.3M** | **+5.80%** | **+10.94%** | **+11.64%** |

**Tax fell (+8.18% → +5.80% at JIT-warm)** — the always-on call-dispatch
optimizations apply uniformly to class-level generic code too, even though
neither optimization directly targeted method dispatch. **The generics cost
itself is essentially unchanged (+10.88% → +10.94%)** — expected: this
session's work targeted call-dispatch overhead (VERIFY / DO_FCALL), not
monomorph *method* dispatch under JIT, which remains the known residual
Phase-5 target for class-level generics (documented in `pass2b-NOTES.md`).

## Result 3 — BCC whole real application (scanning nikic/php-parser v5.7.0→v5.8.0)

| config | master | reify-plain | generic | tax | gen-cost |
|--------|-------:|------------:|--------:|----:|---------:|
| Opcache OFF (JIT off) | 6.929B | 7.170B | 7.171B | +3.48% | +0.01% |
| Opcache on, JIT off, cold | 6.797B | 7.048B | 7.049B | +3.70% | +0.01% |
| Opcache on, JIT off, warm | 6.167B | 6.398B | 6.399B | +3.75% | +0.01% |
| Opcache on, JIT on, cold | 7.247B | 7.502B | 7.535B | +3.52% | +0.43% |
| **Opcache on, JIT on, warm** | **6.939B** | **7.193B** | **7.535B** | **+3.66%** | **+4.75%** |

**Whole-app tax improved meaningfully across every config (~5.0-5.4% →
~3.5-3.8%)** — confirming the always-on optimizations pay off even in a real
application where generics-touching code (psl collections) is a tiny slice
of total call volume. JIT-warm generics cost (+4.29% → +4.75%) is within this
workload's known run-to-run noise band (BCC is a run-once CLI process; JIT
configs here have moved by a similar margin between runs with an unchanged
binary in prior passes — see `pass2b-NOTES.md`'s JIT-cold noise note).

## Synthesis

- **The always-on non-generic tax — the dominant cost component identified in
  Pass 2b — is substantially reduced across all three real-world workloads**:
  psl JIT-warm 11.6% → 4.4%, doctrine JIT-warm 8.2% → 5.8%, BCC whole-app
  ~5.2% → ~3.7%. This directly validates the two Phase 5 items landed this
  session (VERIFY fast-path + DO_FCALL/DO_UCALL/DO_FCALL_BY_NAME fold).
- **Function-level generics (psl) also got materially cheaper to use**
  (JIT-warm generics cost 7.76% → 3.93%) — the call-dispatch overhead this
  session targeted was a real component of the per-call generics cost, not
  just the always-on tax.
- **Class-level generics (doctrine) cost is unchanged (~10.9-11.6%
  JIT-warm)** — the monomorph *method*-dispatch JIT gap (Pass 2b's
  identified residual target) is untouched by this session's work, as
  expected, and remains the next highest-value Phase 5 target for
  class-heavy generic code.
- **Whole-app (BCC) generics cost is unchanged within noise (~0% without
  JIT, ~+4.3-4.8% JIT-on)** — consistent with BCC's run-once nature, which
  cannot exhibit the SHM/trace-persistence wins visible in a repeated-request
  workload like the psl driver.

## Reproduction

Runner scripts: `/tmp/run_psl_matrix.sh`, `/tmp/run_doctrine_matrix.sh`,
`/tmp/run_bcc_matrix.sh` (one-shot, written for this pass; not checked in —
see `pass2b-NOTES.md`'s Reproduction section for the underlying command
shapes, identical here). Builds: `bench-harness/build.sh reify` (master
binary reused unchanged from the Pass 2b run; reify rebuilt at commit
`6109c75fb98`, release, `--with-valgrind`, worktree under
`/tmp/bench-builds`). Workload source trees reused unchanged from
`/tmp/pass2` (psl-orig/psl-converted, coll-orig/coll-converted, mbcc-repo,
phpparser target repo).
