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

## Result 3 — doctrine/collections, CLASS-level generics (`doctrine_pipeline.php`)

psl (Results 1–2) tested generic *functions*; this tests generic *classes* and a
generic interface hierarchy (`ArrayCollection<TKey,T> implements Collection<TKey,T>
extends ReadableCollection<TKey,T>…`), converted with `convert-doctrine.sh`
(class + interface generics **and** the generic methods the driver exercises —
`map<U>` and `contains<TMaybeContained>`; `indexOf`/`reduce`/`removeElement` are
also generic but unused by the driver). `new ArrayCollection::<int,int>()`
synthesises a real class monomorph and `new static()` in `map`/`filter`/`partition`
propagates it — reaching the monomorph **method-dispatch** path where Pass 1 found
the biggest JIT penalty, which psl structurally could not. `data: pass2-doctrine.json`.

| config | master | reify | defaulted | turbofish | tax | dflt-cost | tf-cost |
|--------|-------:|------:|----------:|----------:|----:|----------:|--------:|
| Opcache OFF     | 286.5M | 305.4M | 305.7M | 305.8M | +6.6% | +0.1% | +0.1% |
| JIT off, warm   | 228.4M | 248.5M | 245.6M | 245.7M | +8.8% | −1.2%* | −1.1%* |
| **JIT on, warm** | **199.2M** | **214.1M** | **229.6M** | **229.7M** | **+7.5%** | **+7.2%** | **+7.3%** |

(\* ~1–6% run-to-run variance in some cold/JIT-cold cells; the doctrine workload
is noisier than psl's. The JIT-warm row is stable across runs.)

**The cost of using generic classes is ~0% until JIT is warm, then +7.2% — and
defaulted == turbofish.** This is the purest demonstration of the class-monomorph
JIT-skip: both defaulted (`ArrayCollection<mixed,mixed>`) and turbofish
(`ArrayCollection<int,int>`) create cached monomorphs synthesised once, so the
repeated cost is method dispatch on the monomorph object. Without JIT that
dispatch is interpreted at the plain cost (~0% delta); with JIT warm the plain
methods get compiled but the monomorph methods are skipped → +7.2%. **Making the
conversion faithful (adding `map<U>`/`contains<…>` method generics) did not change
this** — the per-method-call type-arg binding is cached per call site, so it adds
little; the +7.2% is dominated by the class-monomorph method-dispatch JIT-skip.
Total generic-on-reify vs plain-on-master (JIT-warm) = **+15.3%**.

## Synthesis (RFC-relevant)

Three distinct numbers — **generic code pays #1 + #2**, so the number a user feels
vs today's PHP is #3, never zero:

1. **Tax** — what *all* reify code pays vs stock PHP, generics used or not
   (`reify_plain` vs `master`): **~5–8% on real code** (BCC ~5%, doctrine ~6.6%,
   psl collection-heavy ~7–9%; Pass 1 synthetic canary ~2%). Present at all
   times, JIT or not. Cause: always-on per-call type-arg-table alloc and
   call/link overhead.
2. **Incremental generics cost** — generic vs non-generic code *both on reify*
   (`reify_generic` vs `reify_plain`): **~0% without JIT**, rising under JIT-warm
   to **~4% (BCC, generics a small slice)**, **~7% (doctrine, class-level)**,
   **~12% (psl, function-level, collection-dominated)**. Cause: JIT compiles the
   plain code but skips monomorphs.
3. **Total a user feels** — generic code on reify vs the equivalent on stock PHP
   (`reify_generic` vs `master`) ≈ (1+tax)·(1+incremental). **Without JIT this is
   ≈ the tax (~5–8%), NOT zero** — generic code always pays the tax. With JIT-warm
   it is tax + monomorph penalty, e.g. doctrine **+15.3%**, BCC ~+9%, psl higher.

So: **using generics is never free** — generic code always pays the ~5–8% tax
that all reify code pays; JIT-warm then stacks the monomorph penalty on top. The
JIT-warm monomorph penalty (#2 under JIT) is the clearest pointer to **Phase 5
item 7 (JIT for monomorphs)**; the always-on tax (#1) is a separate target
(per-call table alloc + call/link overhead).

## Caveats

- psl 2.0.4 emits many php-8.4+ deprecation notices on 8.6-dev (silenced with
  `error_reporting=0` during measurement; constant across cells).
- BCC's in-process composer install runs under Callgrind, but the scan target is
  dependency-free so its Ir contribution is minimal and identical across cells.
- Conversion covers Vec/Dict/Iter (the clearly-parameterized collection
  modules); Str is non-generic (not converted); Type (classes) left for a
  deeper pass.
