# Pass 2 — real-world psl consumer benchmark

Measures reify's cost on a **real** psl-using project, in two dimensions:
the **tax** (reify overhead with generics unused) and the **cost of using
generics** (psl collections converted to native generics).

Consumer = **`ondrejmirtes/backward-compatibility-check`** (a maintained BCC fork
that still depends on **psl 2.0.4**), scanning **nikic/php-parser**. See
`../results/pass2-NOTES.md` for the full rationale, the php-8.6 patches, and the
numbers.

## Pieces

- `setup.sh` — stands up the environment: Debian php8.2 (Composer runner), the
  BCC fork installed from its lock, the better-reflection 8.6 patch, the
  php-parser scan target, warmed Composer cache. (Prereq: `../build.sh` built the
  release binaries — they now include openssl/curl/bcmath/intl/sodium.)
- `convert-psl.php` — rewrites psl's `@template` functions to native, defaulted
  generics in place (call sites unchanged).
- `measure.sh` — the BCC cell (master / reify-unconverted / reify-converted).
- `workloads/psl_pipeline{,_turbofish}.php` — collection-heavy psl runner
  (function-level generics), defaulted and turbofish variants.
- `convert-doctrine.sh` + `workloads/doctrine_pipeline{,_turbofish}.php` — the
  **class-level** counterpart: doctrine/collections converted to native generics
  (class + interface hierarchy + generic methods `map<U>`/`contains<…>`), driven
  by an ArrayCollection map/filter/partition loop.

All the collection-heavy runners are measured across the 5 opcache/JIT configs
(see `../results/pass2-NOTES.md`), with 4 cells each: master, reify-plain,
reify-generic (defaulted), reify-generic (turbofish/monomorphised).

## Headline

Three numbers; **generic code pays tax + incremental** (see `../results/pass2-NOTES.md`):

- **Tax** (all reify code, generics used or not): **~5–8% on real code**, always.
- **Incremental** cost of generics over non-generic reify code: **~0% without JIT**,
  then JIT-warm **~4% (BCC), ~7.2% (doctrine, class-level), ~12% (psl,
  function-level)** — the JIT skipping monomorphs.
- **Total vs stock PHP** = tax + incremental: **≈ the tax (~5–8%) without JIT (not
  zero!)**, and e.g. **doctrine +15.3% JIT-warm**.

Using generics is never free — it always pays the tax; JIT-warm stacks the
monomorph penalty on top. The monomorph penalty points at **Phase 5 item 7 (JIT
for monomorphs)**; the tax is a separate always-on cost.

## Reproduce

```sh
bench-harness/build.sh            # release binaries with the Pass-2 extensions
bench-harness/pass2/setup.sh
bench-harness/pass2/measure.sh     # -> results/pass2-bcc.json
# collection-heavy runner (3 cells), e.g.:
#   valgrind --tool=callgrind <build>/sapi/cli/php -d error_reporting=0 \
#     bench-harness/pass2/workloads/psl_pipeline.php <autoload.php> 4000
```
