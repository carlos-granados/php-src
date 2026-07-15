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
- `workloads/psl_pipeline.php` — a collection-heavy runner where the per-call
  generics cost surfaces; measured on the three cells the same way.

## Headline

- Real app (BCC): **tax +4.98%**, cost of generics ~0% (collections not hot).
- Collection-heavy runner: **tax +8.36%**, **cost of generics +6.28%**.

## Reproduce

```sh
bench-harness/build.sh            # release binaries with the Pass-2 extensions
bench-harness/pass2/setup.sh
bench-harness/pass2/measure.sh     # -> results/pass2-bcc.json
# collection-heavy runner (3 cells), e.g.:
#   valgrind --tool=callgrind <build>/sapi/cli/php -d error_reporting=0 \
#     bench-harness/pass2/workloads/psl_pipeline.php <autoload.php> 4000
```
