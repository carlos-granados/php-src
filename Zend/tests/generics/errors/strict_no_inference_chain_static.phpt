--TEST--
Strict binding: chained naked generic calls are rejected at compile time (no inference exists)
--FILE--
<?php
/* Regression test for the inference-chaining crash reported during the
 * bound-erased generics RFC discussion: under partial inference, whether
 * a(42) was safe depended on whether b()'s body used T, and the chained
 * binding read uninitialized memory. With inference removed, the naked
 * call site is rejected before any of that can happen. */
class Box<T> {
    public function __construct(public T $value) {}
}
function b<T>(T $value) { new Box::<T>($value); }
function a<T>(T $value) { b($value); }
a(42);
?>
--EXPECTF--
Fatal error: Calling generic function b() requires explicit type arguments, e.g. b::<...>(); 1 required in %s on line %d
