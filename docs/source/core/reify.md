# Next steps in reify implementation

In this RFC https://wiki.php.net/rfc/bound_erased_generic_types Seifeddine Gmati
proposed bound-erased generics for PHP. The RFC did not pass mainly because people
thought that, if later on reified generics were added on top of this, there would be
BC breaks for code that was not type-checked and then started to be type-checked.
To try to avoid this objection, Rob Landers implemented an experimental reified
version of generics on top of Seifeddine's proposal.

There was very little discussion about this proposal because Seifeddine put the RFC to vote
very soon after Rob sent it. I think that this was a very valid proposal that
should have been explored in more detail. What I have tried to do is to
bring this proposal forward and make it complete.

This work has been done with heavy help from Anthropic's LLM tools, mainly using
Claude Code with the latest models. I am hoping that people will look at this work
and judge it on its merits, without disregarding it due to the tools used.
Linus Torvalds has very recently said: "*AI is a tool, just like other tools we use.
And it's clearly a useful one.*" and I couldn't agree more.

## What was done

1. **Removed type inference**. The branch originally tried to guess a generic function's
   type parameters from the values passed at the call site. This was flagged as unsafe (it
   caused a real crash under certain call patterns) and confusing. Replaced with a strict rule
   following Seifeddine's suggestion: every generic call must either use explicit turbofish
   syntax (`Box::<int>`) or fall back to a declared default — nothing is ever guessed from
   argument values. This was the first and most foundational change; everything after builds on it.


2. **Correctness hardening**. Ran the engine through its full test suite, memory-safety
   tools (Valgrind, AddressSanitizer), thread-safety checks, and a fuzzer, specifically
   targeting the new generics code paths. Found and fixed about a dozen real bugs: memory
   leaks, use-after-free errors, a reference-counting bug that could crash the process,
   and several edge cases in how closures, generators, and inheritance interact with generics.


3. **Expanded test coverage**. Roughly doubled the generics-specific test suite, adding
   coverage for opcache preloading, the JIT, async/fiber code, reflection, and various syntax
   edge cases — closing gaps the original branch hadn't tested at all.


4. **Made the JIT work with generics**. Originally, the JIT skipped any code involving
   generics, meaning generic code always ran in the slower interpreted mode.
   Did the engine work needed to let the JIT compile and optimize generic code too, including
   several rounds of fixing crashes that surfaced once that door was opened.


5. **Built a real-world benchmark harness**. Rather than relying on synthetic microbenchmarks,
   set up measurement against actual applications: a static-analysis tool (BCC) scanning
   a real PHP codebase and a simulated process — both with a generics-supporting standard
   library (Psl) converted to use the new native generics, and another simulated process using
   the popular doctrine/collections library, also converted to use the new native generics.
   This gave an honest, reproducible way to answer "how much does this cost in practice."


6. **Multiple rounds of performance tuning**. Using that benchmark harness, iteratively
   found and fixed the biggest cost centers: caching repeated work instead of redoing it
   on every call, optimizing how the JIT handles generic function calls in hot loops, and
   trimming per-call bookkeeping overhead in the engine's core call-dispatch path.


7. **Memory optimization**. Looked at how much extra memory generics-aware code uses
   compared to ordinary PHP, and reduced it by sharing data structures (type information,
   cached call metadata) across functions and closures instead of duplicating them
   per-instance, wherever it was safe to do so.


8. **Final measurement and cleanup**. Ran one last full pass across all three
   real-world benchmarks to get a clean, final set of numbers.

## Current status

This is what the current implementation adds to PHP:

**Core syntax**. Classes, interfaces, traits, functions, and methods can declare
type parameters:

```php
class Box<T> {
    public function __construct(public T $value) {}
}

function identity<T>(T $value): T { return $value; }
```

**Explicit instantiation ("turbofish")**. Unlike a purely erased generics design,
callers must say what type they mean, either explicitly or via a declared default
— nothing is guessed from the values passed in:

```php
$box = new Box::<string>("hello");
$result = identity::<int>(42);
```

**Bounds**. A type parameter can be constrained to a class, interface, union, or
intersection, with a default that must itself satisfy the bound:

```php
class Container<T : Countable = ArrayObject> { ... }
```

**Variance**. Type parameters can be marked `in` (contravariant, e.g. accepted as
an argument) or `out` (covariant, e.g. returned as a result), and the engine enforces
that a type parameter is only used in a position consistent with its declared variance
— this is checked at compile time.

**Reification, not erasure** — this is what Rob's proposal added on top of Seifeddine's
one. The type binding isn't just a compile-time annotation that vanishes at runtime
the way it does in some other languages' generics. When new `Box::<int>()` runs,
the engine actually creates a specialized, runtime version of that class (a "monomorph")
with `int` baked in, and enforces the bound at every call — so passing the wrong type
into a bound generic method genuinely throws a `TypeError`, not just a static-analysis
warning. This is what makes it meaningfully different from PHP's existing docblock-only
generics conventions.

**Implementation approach**. Classes are monomorphized in a similar way to Gina's substitution
approach - a monomorphized class shares as much memory as possible with the templated class,
mainly holding its substitutions. This happens during runtime, only when it cannot be
done at compile time.

Functions/methods get bound in call frames and share the substituted types from their
parent (class, outer closure, etc.).

**Type parameters flow through the whole language surface**, not just simple cases:
- Nested generics (`Box<Pair<int, string>>`)
- Inheritance and interfaces (`class IntBox extends Box<int> implements Comparable<T>`)
- Traits (`use SomeTrait<T>`)
- `self`, `static`, and `parent` resolve correctly inside a generic class body
- Closures and arrow functions declared inside a generic scope capture the enclosing
  type binding, so they can still reference the outer `T` correctly when called later
- Generators and Fibers preserve their generic bindings across `suspend`/`resume`
- Reflection can introspect a monomorphized class/function's concrete type arguments
- Works with opcache preloading and persists correctly across requests
- The JIT can compile and optimize generic and monomorphized code, not just plain code

**What it deliberately does not do**: infer a type parameter from the value you pass
at a call site. Every generic call is either explicit (`::<Type>`) or falls back to a
declared default (which is great for BC) — this was a deliberate safety change from
the original design, since inference from runtime values was found to be unsound in
some call patterns. This can be tackled at a later stage, as adding type inference should not result in BC breaks.

## Testing status

**Full engine test suite** — 22,694 tests (plain interpreter, no opcache):
- 18,150 passed, 4,526 skipped, 8 expected-fail, 4 warnings (all pre-existing XFAIL-passes or flaky-retry-passes, not reify-related)
- 6 failures, all environmental, none touching generics or reify code

**Generics + opcache/JIT-tracing suite** — 1,444 tests (the 521 generics-specific
tests plus the full opcache test suite, run with opcache and the tracing JIT both enabled):
- 1,427 passed, 16 skipped, 1 expected-fail, 0 failures, 0 warnings

**What's been run across the whole effort**

- Full suite across plain, opcache, JIT-tracing, and JIT-function-mode configurations
  — clean except the environmental failures above.
- AddressSanitizer + UndefinedBehaviorSanitizer across the full 7,000+-test suite — clean.
- Valgrind (memory-safety) across the generics suite, reflection, and opcache — clean,
  with one documented pre-existing harmless artifact (uninitialized padding bytes in
  a file-cache serialization path, confirmed present before any of this work started).
- Thread-safety (ZTS) build on generics + opcache subsets — no thread-safety bugs found.
- Fuzzing — 24-72 hour runs targeting the parser and generic call syntax specifically,
  seeded with the generics test corpus; zero crashes in the final run after earlier-found
  bugs were fixed.
- Real-workload regression checks at every engine change (closures, generators, reflection)
  — all clean on the final committed state.

**Bottom line**: the generics feature is memory-safe, thread-safe, sanitizer-clean,
and fuzz-tested, with zero known reify-attributable test failures anywhere in the suite.
The only failures in the entire 22,694-test run are pre-existing environmental issues
unrelated to this branch.

## The benchmark harness

Purpose: give an honest, reproducible answer to "how much does reified generics cost," on
both synthetic and real-world PHP code — replacing hand-wavy estimates with actual measurements.

### Measurement approach

- **Environment**: Ran on a macOS M1 laptop within a Docker container. This should not
  be meaningful for the obtained results as the metric used should be independent of
  the environment where the test was run.


- **Metric**: Callgrind instruction count (`Ir`), not wall-clock time. The environment has
  no hyperfine, and Docker on macOS cannot pin CPUs, so wall-clock timing would be noisy
  and unreliable. Instruction counts are deterministic — the same binary run twice gives
  the exact same number — which makes small percentage differences trustworthy.


- **Cold vs. warm measurement**. Every workload is measured two ways: cold (one request
  including compilation — first-hit cost) and warm (opcache pre-populated, JIT compiled,
  one timed request from cache — the production-representative number). Warm is the
  headline figure.


- **Two independently-built PHP binaries**: one from the `master` branch at the exact
  commit `reify` diverged from, one from `reify` itself — built as isolated release binaries
  (not the developer's debug build), so the comparison is apples-to-apples.

### Two layers of workloads

1. **Synthetic canary + micro-benchmarks** — a broad non-generic PHP script plus a
   hand-written class/function workload compared against an equivalent version using
   native generics, to isolate the pure cost of the feature in a controlled setting.

2. **Real-world applications** — the more important layer, since synthetic benchmarks
   can be misleading:
- A maintained fork of `BackwardCompatibilityCheck` (a real static-analysis tool)
  scanning a real codebase (`nikic/php-parser`) — a whole, unmodified application.
- `doctrine/collections`, a widely used real library, converted to use native generics.
- Psl (a PHP standard-library-extension package), converted the same way, since
  it's what the BCC tool itself depends on internally.

Each of these is measured three ways — original `master`, running unmodified on `reify`
(does having generics exist in the engine but go unused cost anything?), and running the
generics-converted version (what does using the feature cost?) — across five
opcache/JIT configurations.

## Benchmark results

Three real-world workloads, each measured across five opcache/JIT configurations,
comparing plain PHP (`master`) against `reify` with generics unused (*the tax*) and
against `reify` with generics actually used (*cost of generics*).

You can find the detailed results [here](../../../bench-harness/results/NOTES.md).

Summarized results:

**The tax** — cost of generics being in the engine, even unused

|             workload              | range across configs                                              |
|-----------------------------------|-------------------------------------------------------------------|
| bcc (whole real app)              | +3.5% to +3.8% — tight, consistent, barely affected by JIT on/off |
| doctrine (collections library)    | +2.6% to +6.0%                                                    |
| psl (direct function-call driver) | +1.5% to +6.5%                                                    |

Best real-world estimate: ~3.5–3.8%, from bcc — since it's a whole, unmodified application rather than a
synthetic driver, it's the most trustworthy number, and notably it barely moves between configurations.

This *tax* comes from checks placed in several hot paths of the engine that are necessary to process and validate generics, even when they are unused.

**Cost of actually using generics** (on top of the tax)

| workload |                 range across configs                  |
|----------|-------------------------------------------------------|
| bcc      | ~0% (Psl is a small slice of the app's real hot path) |
| doctrine | +0.2% (JIT off) up to +9.4% (JIT on, cold)            |
| psl      | +4.6% to +10.8%                                       |

The pattern is consistent across both collection-heavy workloads: cost is near-zero
without the JIT, and rises sharply under the JIT, because the JIT optimizes ordinary
PHP code well but doesn't yet optimize the specialized runtime versions of generic
classes/functions the same way — that's a known limitation, not a design flaw,
and the clearest concrete target for further engine work.

This also shows that even in generics-heavy code, the actual generic checks are
usually a small part of the whole work that is done by the PHP engine.

**Bottom line**

Reified generics cost real applications roughly 3.5–4% even when unused, and an
additional, JIT-dependent amount when actually used — currently as high as ~9–15%
in JIT-warm collection-heavy code, the main area a future optimization pass
(better JIT support for the specialized generic code paths) would target.

## Memory usage benchmark findings

**Headline**: memory overhead is small and not alarming. The same 3-workload × 5-config matrix
used for the instruction-count benchmarks was re-run measuring peak RSS instead —
the tax and cost-of-generics numbers came out ~0.3-5%, mostly under 3%, across the board.
On raw memory footprint alone, reified generics don't look concerning.

## Further thoughts

First of all, I would like to say that the full merit of this work must be attributed to
Seifeddine and Rob. They are the ones that created the foundations on which this work is built,
and I would like to express my deepest gratitude for their previous efforts. My work has
just been a little push to try to get this across the line.

I believe that, even though all tests are passing and the main functionality seems to
work correctly, I am sure that there must be edge cases which have not yet been
fully considered and crashes or bugs which are just waiting to be discovered. This
work needs a lot of scrutiny and a lot of further testing.

Also, though I am happy with the results of the benchmark, I would like to add more
use cases to it to make sure that we are capturing a large part of the possible
usages of this functionality. I would love to hear suggestions on what other code
cases could be added to the benchmark.

Finally, though I don't believe that this work is yet ready for a full RFC, if people
think that this is the right direction, and we continue working on improving the
implementation, we will at some point reach a status where an RFC should be considered.
If any of the original authors, Seifeddine and/or Rob, or any other person from the
PHP community wants to pick this up, I am happy to leave this into someone else's hands,
providing any needed assistance. Otherwise, I am happy to continue working on this myself.



