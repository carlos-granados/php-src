--TEST--
Reification: a closure created inside a generic frame shares the frame's cached type-arg table (not a fresh clone) when that table is cache-owned, but still clones correctly when rebound
--FILE--
<?php
class A {}
class B {}

// Explicit turbofish call: hits the per-call-site cache (owner_external=true,
// see Zend/zend_compile.h). A closure created in the body -- the common
// `array_map(static fn($v) => ..., $x)` idiom -- shares that cached table
// instead of deep-cloning it on every call.
function outerTurbofish<T : object>(): Closure {
    return static fn(): string => T::class;
}

$c1 = outerTurbofish::<A>();
var_dump($c1());

// Naked (defaulted) call: hits the per-function defaults cache
// (defaults_cache_slot, also owner_external=true). Calling the SAME generic
// function repeatedly must keep resolving correctly even though the
// underlying table is now shared across every closure created from every
// call to it.
function idClosure<T = mixed>(): Closure {
    return static fn(mixed $v): mixed => $v;
}
$id1 = idClosure();
$id2 = idClosure();
var_dump($id1(1), $id2('two'));

// Rebinding via Closure::bind/bindTo re-captures FROM a closure's own
// captured_type_args. That table must NOT be treated as shareable (even
// though it's `persisted`, it's owned exclusively by the source closure) --
// otherwise two closures would silently alias one table with nothing left
// to release it once the original is gone. This must still work correctly,
// including after the original closure is destroyed.
$c2 = Closure::bind($c1, null);
var_dump($c2());
$c3 = $c1->bindTo(null);
var_dump($c3());
unset($c1);
gc_collect_cycles();
var_dump($c2());
var_dump($c3());

// Generators still capture their own independent, non-shared copy (see
// zend_vm_def.h) -- unaffected by this change, still correct after the
// creating frame is long gone.
function genTurbofish<T : object>(): Generator {
    yield T::class;
    yield T::class;
}
$g = genTurbofish::<B>();
foreach ($g as $v) {
    var_dump($v);
}
?>
--EXPECT--
string(1) "A"
int(1)
string(3) "two"
string(1) "A"
string(1) "A"
string(1) "A"
string(1) "A"
string(1) "B"
string(1) "B"
