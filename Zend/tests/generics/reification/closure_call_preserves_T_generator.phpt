--TEST--
Reification: Closure::call propagates the captured T-table for a generator closure too
--DESCRIPTION--
Closure::call() has a separate code path for closures whose body is itself a
generator (ZEND_ACC_GENERATOR): it builds a genuinely new closure object via
zend_create_closure() instead of borrowing the source's captured-table pointer
the way the non-generator branch and clone/bindTo do. That new closure did not
have the source's captured T-table applied to it, so a generator closure
created inside a generic frame and invoked via ->call() ran with type_args ==
NULL, losing enforcement of any outer T-ref its body resolved.
--FILE--
<?php
class Holder {
    public mixed $store = null;
}

class A {}
class B extends A {}

function maker<T>(): Closure {
    return function (T $x) {
        $this->store = $x;
        yield get_class($x);
    };
}

$f = maker::<A>();
$h = new Holder;

// Positive: A and its subclass B both satisfy T = A.
foreach ($f->call($h, new A) as $v) { var_dump($v); }
foreach ($f->call($h, new B) as $v) { var_dump($v); }

// Negative: a value that doesn't satisfy T fires with the resolved T,
// instead of the "no binding was supplied" internal error the missing
// capture used to produce.
class Unrelated {}
try {
    foreach ($f->call($h, new Unrelated) as $v) {}
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(1) "A"
string(1) "B"
rejected: %s must be of type A, Unrelated given%S
