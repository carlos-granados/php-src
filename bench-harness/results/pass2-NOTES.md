# Pass 2 findings — real-world psl consumer (Callgrind Ir)

Metric = Callgrind instruction counts (deterministic; run-to-run noise ~0.01%).
Setup: `bench-harness/pass2/setup.sh`; measurement: `pass2/measure.sh` (BCC) and
the driver below. Data: `pass2-bcc.json`, `pass2-driver.json`.

## Design (and why it looks like this)

The original plan (Seifeddine's proposal) was "convert Psl → BCC's runtime uses
psl → measure BCC." Modern **Roave BCC dropped psl**, so it can't drive this.
The maintained fork **`ondrejmirtes/backward-compatibility-check`** still depends
on **psl `2.0.4`** (~104 call sites: Str/Dict/Vec/Iter/Type) — the original
design preserved. It runs on our php-8.6-dev binaries after patching
`ondrejmirtes/better-reflection` for 8.6's now-typed reflection constants
(`public const int …`). Scan target = **nikic/php-parser** (flat, dependency-free,
large API → lots of psl exercise, trivial in-process composer install).

"Conversion" = psl's `@template`-documented functions get native, **defaulted**
type parameters (`function map<Tk = mixed, Tv = mixed, T = mixed>(…)`), so psl's
existing call sites work unchanged while every call goes through the reify
generic machinery. Applied to Vec/Dict/Iter (82 functions).

## Result 1 — whole real application (BCC scanning php-parser, ~7.3B Ir), 5 configs

Warm = `opcache.file_cache` pre-populated by one run, then the measured run reads
opcodes from disk. (JIT is not persisted to file_cache, so for this run-once CLI
tool JIT re-compiles within the measured run.) `data: pass2-bcc-matrix.json`.

| config | master | reify | generic | tax | gen-cost |
|--------|-------:|------:|--------:|----:|---------:|
| Opcache OFF (JIT off)     | 6.929B | 7.273B | 7.274B | +4.97% | +0.01% |
| Opcache on, JIT off, cold | 6.741B | 7.124B | 7.091B | +5.69% | −0.46% |
| Opcache on, JIT off, warm | 6.166B | 6.490B | 6.491B | +5.25% | +0.01% |
| Opcache on, JIT on, cold  | 7.211B | 7.292B | 7.581B | +1.12% | +3.96% |
| **Opcache on, JIT on, warm** | **6.940B** | **7.292B** | **7.613B** | **+5.08%** | **+4.40%** |

**Without JIT, converting BCC's psl collections to generics costs ~0%** — the
Vec/Dict/Iter calls are a tiny slice of a scan dominated by reflection and Str
(non-generic). **But with JIT on it costs ~4%**: JIT optimizes the plain psl
collection functions (near-free) yet skips the monomorphs, so the generic calls
become relatively expensive — enough to add ~4% to the whole-application total
even though generics are a small part of the workload. (JIT-cold's low +1.12%
tax is an artefact: JIT-compile overhead is added to both builds and unamortised
in a run-once tool. file_cache warming works — jitoff warm 6.166B < cold 6.741B.)

## Result 2 — collection-heavy runner, 5 opcache/JIT configs (`psl_pipeline.php`)

A targeted data pipeline where Vec/Dict/Iter dominate, so the per-call cost
surfaces. Measured via php-cgi `-T` (warm = `-T5,1`, Callgrind-zeroed warmup;
cold = `-T1`), 1000 iters/request. `data: pass2-matrix.json`.

`generic` = defaulted generics (call sites unchanged); `turbofish` = explicit
`::<...>` at the call sites (monomorphisation path).

| config | master | reify | generic | turbofish | tax | gen-cost | tf-cost |
|--------|-------:|------:|--------:|----------:|----:|---------:|--------:|
| Opcache OFF (JIT off)       | 677.0M | 728.1M | 766.3M | 766.4M | +7.54% | +5.26% | +5.26% |
| Opcache on, JIT off, cold   | 674.6M | 724.6M | 763.6M | 785.8M | +7.41% | +5.37% | +8.45% |
| Opcache on, JIT off, warm   | 512.1M | 558.7M | 597.0M | 597.0M | +9.09% | +6.86% | +6.86% |
| Opcache on, JIT on, cold    | 656.3M | 708.0M | 747.3M | 721.9M | +7.88% | +5.54% | +1.96% |
| **Opcache on, JIT on, warm** | **443.0M** | **463.3M** | **519.7M** | **519.6M** | **+4.59%** | **+12.17%** | **+12.15%** |

(tax = reify-plain vs master; gen-cost / tf-cost = defaulted / turbofish vs reify-plain.)

**Turbofish (explicit monomorphisation) costs the same as defaulted generics in
every warm config** — the +12% JIT-warm penalty is identical. Because psl
functions are **autoloaded**, the callee isn't statically known at the call site,
so concrete turbofish can *not* be resolved at compile time (it can only when the
callee is in the same compilation unit); it falls back to the same runtime
type-arg binding as the defaulted path (~1 type-arg table per call, either way).
So explicit type arguments are **not** a runtime win for autoloaded generic
functions. The only differences are in the noisier cold cells (turbofish +2.9%
worse at JIT-off/cold from extra compile-time resolution, −3.4% at JIT-on/cold).

**The production config (JIT on, warm) is where the cost of using generics
doubles — to +12.17%.** JIT compiles the plain psl hot loop cleanly (master
677M→443M, reify-plain 728M→463M), but the generic version can't be JIT'd as
well because monomorphs share the template op_array and are skipped by the JIT
(766M→520M only). So the *relative* cost of using generics balloons exactly when
JIT is warm — the same effect Pass 1 saw at the micro level. Conversely the
*tax* (generics unused) narrows to +4.59% warm+JIT, because JIT optimizes the
hot path for both builds.

## Synthesis (RFC-relevant)

- **The "tax" (reify overhead with generics UNUSED)** is ~7–9% on this
  call-dense workload across configs, narrowing to **+4.59% in the production
  JIT-warm config**; on a broad real app (BCC) it is **~5%**, and on Pass 1's
  synthetic canary **~2%**. So the unused-generics tax scales with call density
  and sits around +5% for real code.
- **The cost of USING native generics is dominated by whether JIT is on**, because
  JIT optimizes the plain code but skips monomorphs:
  - *JIT off:* ~0% (BCC, collections a small slice) to ~5–7% (collection-heavy).
  - *JIT on (production):* **~4% even in a whole real app (BCC)** where generics
    are a small part, rising to **+12% when generic collections dominate**.
- The JIT-on generics penalty — visible in **both** the whole app and the
  collection-heavy runner — is the single clearest pointer to **Phase 5 item 7
  (re-enable JIT for monomorphs)**. The always-on tax (~5% on real code) points
  at the per-call type-arg-table alloc (no caching for defaulted calls) and
  call/link overhead.

## Caveats

- psl 2.0.4 emits many php-8.4+ deprecation notices on 8.6-dev (silenced with
  `error_reporting=0` during measurement; constant across cells).
- BCC's in-process composer install runs under Callgrind, but the scan target is
  dependency-free so its Ir contribution is minimal and identical across cells.
- Conversion covers Vec/Dict/Iter (the clearly-parameterized collection
  modules); Str is non-generic (not converted); Type (classes) left for a
  deeper pass.
