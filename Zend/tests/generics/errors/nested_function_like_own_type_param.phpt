--TEST--
Errors: closure with its own (differently-named) type parameter cannot be nested inside another generic function-like scope
--DESCRIPTION--
A function-like's own type parameters and an enclosing function-like scope's
captured type parameters are both identified at runtime by a flat
(origin, index) pair, so a closure's own first type parameter and an outer
function's captured first type parameter would otherwise be indistinguishable
when binding a call to the closure -- silently letting the outer binding
satisfy the inner slot instead of raising the missing-turbofish error it
should. Since the two parameters here have different names ("T" vs "U"), the
older same-name shadow check would not have caught this; the nesting itself is
what's forbidden.
--FILE--
<?php
function outer<T>() {
    return function <U>(U $x): U { return $x; };
}
?>
--EXPECTF--
Fatal error: A function, method, or closure with its own type parameters cannot be declared inside another generic function, method, or closure in %s on line %d
