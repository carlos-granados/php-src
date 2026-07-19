# Pass 2d — real-world trio re-run after JIT-traced loops, composite generic enforcement, and the preload crash fix (2026-07-19)

Re-run of the full Pass-2c matrix (psl function-level generics, doctrine/collections
class-level generics, BCC whole-app scan) after landing four commits on top of the
Pass-2c build:

- `f572ad0ea41` "optimise generic function templates"
- `b5abce65832` "optimise generic function returns"
- `9f9b2e89f76` "optimise generic method calls in JIT-traced loops" — narrowed two
  compile-time JIT exclusions so a non-generic hot loop calling into a generic
  monomorph method (e.g. `foreach (...) { $collection->add($user); }`) can actually
  trace and inline under `opcache.preload`.
- `5b9ea0f164d` "enforce composite generic types in parameters and returns" — closed a
  correctness gap (composite types like `Collection<T>` were unchecked at parameter/
  return boundaries), not a performance change, but re-run here since it landed in the
  same window and touches the same substitution machinery this matrix exercises.
- `1c5832f0afc` "fix preload crash for generic monomorphs" — required for the
  `opcache.preload`-eligible cells in this matrix to run at all.

Master binary unchanged (`9498bc3ee13`). Metric = Callgrind Ir (php-cgi `-T`; warm =
`-T20,1` steady-state for the psl/doctrine drivers; BCC warmed via
`opcache.file_cache`, re-populated per warm cell). Data: `pass2d-psl-matrix.json`,
`pass2d-doctrine.json`, `pass2d-bcc-matrix.json`. Baseline for comparison:
`pass2c-*.json` + `pass2c-NOTES.md`.

**Scope note:** this pass measured the defaulted-call-site variant only (call sites
unchanged, generics resolved via defaulted type params) — the turbofish variant
(`psl_pipeline_turbofish.php` / `doctrine_pipeline_turbofish.php`) was not re-run this
time, so the `tf-cost` column from Pass 2c has no update here.

## Result 1 — psl pipeline, FUNCTION-level generics (Vec/Dict/Iter, 2000 iters/request)

| config | master | reify-plain | defaulted | tax | gen-cost |
|--------|-------:|------------:|----------:|----:|---------:|
| Opcache OFF (JIT off) | 1219.8M | 1284.7M | 1365.4M | +5.32% | +6.28% |
| Opcache on, JIT off, cold | 1190.2M | 1268.8M | 1334.5M | +6.60% | +5.18% |
| Opcache on, JIT off, warm | 1022.3M | 1081.1M | 1162.7M | +5.76% | +7.54% |
| Opcache on, JIT on, cold | 1148.4M | 1077.2M | 1212.6M | −6.20% | +12.57% |
| **Opcache on, JIT on, warm** | **884.2M** | **872.3M** | **924.7M** | **−1.35%** | **+6.01%** |

JIT-warm tax has gone negative here (reify-plain now *beats* master by 1.35%,
vs +4.42% tax at Pass 2c) — plausibly this pass's loop-traced-into-monomorph work
letting more of the surrounding non-generic driver code trace as well, though this
specific workload has no class-level generics for that fix to touch directly; could
also be cold/warm JIT noise (Pass 2c's own notes flag JIT-cold as this workload's
noisiest config, and this pass's JIT-cold tax is also negative at −6.20%). Gen-cost
at JIT-warm (+6.01%) sits between Pass 2c's defaulted (+3.93%) and turbofish
(+8.44%) figures. Not deeply investigated this pass — worth a dedicated re-run with
tighter noise control if the negative-tax result needs to be trusted for an RFC
number.

## Result 2 — doctrine/collections, CLASS-level generics (2000 iters/request)

| config | master | reify-plain | defaulted | tax | gen-cost |
|--------|-------:|------------:|----------:|----:|---------:|
| Opcache OFF (JIT off) | 535.5M | 566.5M | 568.4M | +5.80% | +0.34% |
| Opcache on, JIT off, cold | 504.5M | 529.4M | 535.7M | +4.93% | +1.18% |
| Opcache on, JIT off, warm | 456.7M | 485.0M | 486.7M | +6.20% | +0.33% |
| Opcache on, JIT on, cold | 440.1M | 464.6M | 509.0M | +5.56% | +9.56% |
| **Opcache on, JIT on, warm** | **386.4M** | **409.5M** | **423.2M** | **+5.96%** | **+3.35%** |

**JIT-warm gen-cost improved (+10.94% at Pass 2c → +3.35% here)** — consistent
with this session's class-monomorph-method JIT work (the "optimise generic method
calls in JIT-traced loops" fix), which specifically targets the monomorph
method-dispatch gap Pass 2c identified as doctrine's residual cost. It did not
close all the way to the ~0.2-1.1% previously recorded for a narrower isolated
repro after the earlier T-free-shared-method JIT fix — this driver's workload
shape (and whether `opcache.preload` is actually engaged for its monomorphs in
this measurement) may differ from that repro; not reconciled this pass. Tax is
essentially unchanged (+5.80% → +5.96%, within noise), as expected since none of
this session's work targeted the always-on tax.

## Result 3 — BCC whole real application (scanning nikic/php-parser v5.7.0→v5.8.0)

| config | master | reify-plain | generic | tax | gen-cost |
|--------|-------:|------------:|--------:|----:|---------:|
| Opcache OFF (JIT off) | 6.929B | 7.171B | 7.182B | +3.49% | +0.16% |
| Opcache on, JIT off, cold | 6.797B | 7.050B | 7.050B | +3.72% | +0.00% |
| Opcache on, JIT off, warm | 6.166B | 6.398B | 6.399B | +3.75% | +0.02% |
| Opcache on, JIT on, cold | 7.200B | 7.191B | 7.480B | −0.12% | +4.01% |
| **Opcache on, JIT on, warm** | **6.940B** | **7.191B** | **7.192B** | **+3.63%** | **+0.01%** |

Essentially unchanged from Pass 2c (tax ~3.5-3.8% in every config, JIT-warm gen-cost
back down near 0%, matching the fact that psl collections are a tiny slice of this
whole-app workload and doctrine isn't exercised by it at all). The JIT-cold cell's
negative tax (−0.12%) is within this config's known noise band, same caveat as
Pass 2c.

## Synthesis

- **Class-level generics (doctrine) JIT-warm cost improved substantially**
  (+10.94% → +3.35%) — the direct, measured payoff of this session's
  class-monomorph-method JIT-tracing fix, on the real-world workload Pass 2c
  flagged as the next highest-value target. Did not fully close to the ~0.2-1.1%
  seen in an earlier isolated repro; gap not reconciled this pass.
- **Function-level generics (psl) JIT-warm tax went negative** (−1.35%) with
  gen-cost landing between Pass 2c's defaulted and turbofish figures — plausible
  but not confirmed as a genuine win versus JIT-cold noise; flagged for a
  dedicated re-run rather than trusted outright.
- **Whole-app (BCC) numbers are stable versus Pass 2c** — expected, since none
  of this session's fixes target BCC's actual workload shape (mostly psl at a
  small slice, no doctrine at all).
- Composite generic type enforcement (the correctness fix landed alongside the
  performance work) is not expected to and did not measurably move any of
  these numbers — included here only because it shares substitution machinery
  with the workloads this matrix exercises.

## Reproduction

Runner: `/tmp/run_matrix.sh` (one-shot, written for this pass; not checked in —
mirrors `pass2c-NOTES.md`'s underlying command shapes: `psl_pipeline.php` /
`doctrine_pipeline.php` via `php-cgi -T`, BCC via `opcache.file_cache`-warmed
single-shot CLI). Builds: reify rebuilt at commit `1c5832f0afc`, release,
worktree under `/root/bench-builds/reify-src`; master binary reused unchanged
from earlier passes (`/root/bench-builds/master-src`, `9498bc3ee13`). Workload
source trees reused unchanged from `/tmp/pass2` (psl-orig/psl-converted,
coll-orig/coll-converted, coll-proj, mbcc-repo, phpparser target repo).
