--TEST--
Reification: a bound is never an implicit call-site binding; turbofish resolves T explicitly
--FILE--
<?php
class Base { public string $kind = "base"; }
class Derived extends Base { public string $kind = "derived"; }

function makeDefault<T : Base>(): T {
    return new T();
}

// The bound does not stand in for a missing binding: a dynamic naked call
// fails up front (a static one is a compile error).
try {
    $fn = 'makeDefault';
    $fn();
} catch (ArgumentCountError $e) {
    echo $e->getMessage(), "\n";
}

// Explicit bindings resolve T, constrained by the bound.
var_dump(makeDefault::<Base>()->kind);
var_dump(makeDefault::<Derived>()->kind);
?>
--EXPECT--
Too few generic type arguments to makeDefault(), 0 passed and exactly 1 expected
string(4) "base"
string(7) "derived"
