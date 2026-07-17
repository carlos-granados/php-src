--TEST--
Runtime: VERIFY_GENERIC_ARGUMENTS' early non-generic fast path is correct alongside real generic calls
--FILE--
<?php
declare(strict_types=1);

// Polymorphic method calls (no turbofish, unknown callee at compile time)
// take VERIFY_GENERIC_ARGUMENTS' speculative path regardless of whether
// generics appear anywhere else in the program. Interleave calls to
// non-generic methods/functions with calls to generic ones (including
// through the same polymorphic call site across different runtime types)
// to make sure the added early bail-out (opline->extended_value == 0) never
// diverges from the pre-existing slower fast path it shortcuts.

interface Tick { public function tick(): int; }

class PlainA implements Tick { public function tick(): int { return 1; } }
class PlainB implements Tick { public function tick(): int { return 2; } }

class GenBox<T> {
    public function __construct(public T $value) {}
    public function get(): T { return $this->value; }
}

function callTick(Tick $t): int { return $t->tick(); }

$objs = [new PlainA(), new PlainB(), new PlainA()];
foreach ($objs as $o) {
    var_dump(callTick($o));
}

// A generic call site right next to non-generic polymorphic calls.
$bi = new GenBox::<int>(42);
$bs = new GenBox::<string>("hi");
var_dump($bi->get());
var_dump($bs->get());

// Bound violation on a real generic call still raises correctly even after
// many non-generic speculative calls warmed the same VM handler.
function bounded<T : Tick>(T $x): int { return $x->tick(); }
var_dump(bounded::<PlainA>(new PlainA()));
try {
    bounded::<PlainB>(new PlainA());
} catch (TypeError $e) {
    echo "caught: ", $e->getMessage(), "\n";
}

// Re-run the plain polymorphic calls again after generics were exercised,
// to catch any cache-slot cross-contamination between call sites.
foreach ($objs as $o) {
    var_dump(callTick($o));
}
?>
--EXPECTF--
int(1)
int(2)
int(1)
int(42)
string(2) "hi"
int(1)
caught: bounded(): Argument #1 ($x) must be of type PlainB, PlainA given, called in %s on line %d
int(1)
int(2)
int(1)
