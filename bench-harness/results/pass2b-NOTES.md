# Pass 2b — real-world trio re-run on the SHM-monomorph build (2026-07-17)

Re-run of the full Pass-2 matrix (psl function-level generics, doctrine/collections
class-level generics, BCC whole-app scan) after landing:

- `381f790e1f3` "JIT monomorphs" — tracing JIT compiles runtime monomorphs
  (opcode detach + runtime trace counters).
- `e611eded8e9` "Persist monomorphs to SHM" — runtime monomorphs (class + fn)
  persist into opcache SHM (inheritance-cache-style); JIT counters/extensions/
  compiled traces share the SHM lifetime; structurally fixes the multi-request
  tracing-JIT crash (php-cgi -T500 clean, was a deterministic segfault at ~10).

Master binary unchanged (`9498bc3ee13`). Metric = Callgrind Ir (php-cgi `-T`;
warm = `-T5,1` with Callgrind-zeroed warmup, cold = `-T1`; BCC warmed via
`opcache.file_cache`, re-populated per warm cell because the binary changed).
Data: `pass2b-psl-matrix.json`, `pass2b-doctrine.json`, `pass2b-bcc-matrix.json`.
Baseline for comparison: `pass2-*.json` + `pass2-NOTES.md` (pre-JIT-mono build).

## Methodology bug found (and fixed) in the original Pass-2 warm cells

The matrix scripts copy a fresh psl/doctrine source tree before every cell.
`opcache.file_update_protection` (default 2s) refuses to cache files modified
less than 2s ago — and under Callgrind's ~50x slowdown, whether the early
warmup requests fall inside that window depends on wall-clock timing (machine
load). When they do, files aren't cached, hot counters reset per request, and a
**~26M-Ir compile burst lands inside the measured request** — bimodally.

Evidence: prior psl master JIT-warm cell = 443.0M; the true fully-warm number
is **416.6M** (verified 3 runs, identical to the instruction). The prior
reify-plain cell (463.3M) did *not* carry the burst — so the prior "+4.59% tax
at JIT-warm" compared a contaminated master against a clean reify and was an
artifact; the true prior tax was already ~+11.2%.

**Protocol fix (used for all warm rows below): `sleep 3` after the source swap,
and never run two matrices concurrently.** With the fix every re-measured cell
is deterministic (×3 identical; worst repeat deviation 0.017% from residual
timestamp-revalidation stats). Cold and opcache-off cells are unaffected
(single request — compile is included by definition) and reproduced the prior
numbers, except the always-noisy JIT-cold cells.

## Result 1 — psl pipeline, FUNCTION-level generics (Vec/Dict/Iter, 1000 iters/request)

| config | master | reify-plain | defaulted | turbofish | tax | gen-cost | tf-cost |
|--------|-------:|------------:|----------:|----------:|----:|---------:|--------:|
| Opcache OFF (JIT off) | 677.0M | 728.1M | 766.3M | 766.4M | +7.54% | +5.26% | +5.26% |
| Opcache on, JIT off, cold | 674.6M | 724.6M | 763.6M | 781.0M | +7.41% | +5.37% | +7.78% |
| Opcache on, JIT off, warm | 512.1M | 558.7M | 597.1M | 597.1M | +9.09% | +6.87% | +6.87% |
| Opcache on, JIT on, cold | 656.3M | 708.1M | 748.3M | 748.0M | +7.89% | +5.68% | +5.64% |
| **Opcache on, JIT on, warm** | **416.6M** | **464.9M** | **500.9M** | **500.8M** | **+11.59%** | **+7.76%** | **+7.74%** |

(tax = reify-plain vs master; gen/tf-cost = defaulted/turbofish vs reify-plain.)

**The JIT-for-monomorphs work delivered on function-level generics: JIT-warm
generics cost fell +12.17% → +7.76%** (defaulted == turbofish, as before —
autoloaded callees can't resolve turbofish at compile time). reify-plain itself
is unchanged vs the prior build (463.3M → 464.9M, +0.34%), i.e. the JIT changes
added no measurable cost to plain code on this workload.

## Result 2 — doctrine/collections, CLASS-level generics (1000 iters/request)

| config | master | reify-plain | defaulted | turbofish | tax | gen-cost | tf-cost |
|--------|-------:|------------:|----------:|----------:|----:|---------:|--------:|
| Opcache OFF (JIT off) | 286.5M | 305.4M | 305.7M | 305.8M | +6.58% | +0.11% | +0.14% |
| Opcache on, JIT off, cold | 273.2M | 291.2M | 291.7M | 291.9M | +6.58% | +0.16% | +0.22% |
| Opcache on, JIT off, warm | 228.4M | 245.3M | 245.5M | 245.6M | +7.38% | +0.11% | +0.15% |
| Opcache on, JIT on, cold | 262.5M | 279.8M | 280.3M | 321.2M | +6.56% | +0.18% | +14.82%* |
| **Opcache on, JIT on, warm** | **193.3M** | **209.1M** | **231.8M** | **231.9M** | **+8.18%** | **+10.88%** | **+10.93%** |

**Class-level generics did NOT improve — the clean JIT-warm cost is +10.9%**
(the prior +7.24% reading was burst-contaminated on its baseline cells; the
defaulted cell itself genuinely rose ~1% (229.6M → 231.8M)). The split shows
where the gap lives: JIT-on improves plain doctrine by 14.8% (245.3M → 209.1M)
but the generic variant by only 5.6% (245.5M → 231.8M). Monomorph method traces
now compile and persist, but capture far less of the JIT win than the plain
twin — **monomorph method dispatch under tracing JIT is the clearest remaining
Phase-5 target.**

\* Turbofish JIT-cold (+14.8%, was +6.2%) is the new one-request cost of
monomorph SHM-persist + mono-trace JIT compilation, un-amortized in a single
request; a cold-start cost that repeat requests don't pay. JIT-cold remains the
noisiest config.

## Result 3 — BCC whole real application (scanning nikic/php-parser v5.7.0→v5.8.0)

| config | master | reify-plain | generic | tax | gen-cost |
|--------|-------:|------------:|--------:|----:|---------:|
| Opcache OFF (JIT off) | 6.929B | 7.273B | 7.274B | +4.97% | +0.01% |
| Opcache on, JIT off, cold | 6.742B | 7.092B | 7.134B | +5.20% | +0.59% |
| Opcache on, JIT off, warm | 6.167B | 6.491B | 6.491B | +5.25% | +0.00% |
| Opcache on, JIT on, cold | 7.194B | 7.585B | 7.586B | +5.44% | +0.01% |
| **Opcache on, JIT on, warm** | **6.941B** | **7.294B** | **7.608B** | **+5.10%** | **+4.29%** |

**Essentially unchanged (+4.40% → +4.29% at JIT-warm), and expectedly so**: BCC
is a run-once single process — SHM persistence amortizes nothing across runs,
and mono-trace JIT compile cost lands inside the measured run, offsetting most
of the execution win. The SHM work's real wins for this shape of tool are crash
safety and (in server SAPIs) cross-request/process monomorph + trace reuse,
which a run-once CLI tool cannot exhibit by construction. Tax steady at ~5% in
every config. (JIT-cold: reify-plain rose ~4% vs the prior run while the
generic cell is unchanged, making the two cells equal; JIT-cold was already
flagged as the noisiest config — master alone moved −0.25% with an identical
binary.)

## Synthesis

- **Function-level generics (psl): JIT-for-monomorphs + SHM trace persistence
  works** — JIT-warm generics cost down from +12.2% to **+7.8%**.
- **Class-level generics (doctrine): no improvement (+10.9% clean)** — the
  monomorph *method-dispatch* JIT gap is the remaining performance problem.
- **Whole-app (BCC): unchanged** (~5% tax, +4.3% with JIT) — structural wins
  don't show in run-once tools.
- **The always-on tax is now the dominant cost component** (~5–9% across
  configs, +11.6% at psl JIT-warm) — larger than the generics increment itself
  on two of the three workloads. Next highest-value work is Phase 5 items 2–3
  (per-call type-arg-table allocation, VERIFY fast path / call-link overhead),
  not further monomorph-JIT tuning — except for the doctrine-style method
  dispatch gap above.
- Multi-request stability under tracing JIT (the reason for the SHM work) is
  covered outside these tables: php-cgi -T500 single-process clean under
  `opcache.protect_memory=1` (previously a deterministic segfault at ~10
  requests), SHM byte-flat across requests, traces compiled once and reused.

## Reproduction

Runner scripts are one-shot (documented in the JSON files' `protocol_fix`
fields): per cell, swap the psl/doctrine source variant in, `sleep 3`, then
`valgrind --tool=callgrind [--smc-check=all if JIT] -- php-cgi -T<5,1|1>
-d error_reporting=0 -d display_errors=0 -d opcache.enable=<0|1>
-d opcache.enable_cli=<0|1> -d opcache.jit=<disable|tracing>
-d opcache.jit_buffer_size=128M <driver>` and read `Collected : <Ir>`.
Drivers: `bench-harness/pass2/workloads/{psl,doctrine}_pipeline[_turbofish].php`
(`PSL_AUTOLOAD`, `PSL_ITERS=1000`). BCC: `pass2/measure.sh` cell structure with
per-cell `opcache.file_cache` repopulation. Builds: `bench-harness/build.sh`
(release, `--with-valgrind`, worktrees under `/tmp/bench-builds`).
