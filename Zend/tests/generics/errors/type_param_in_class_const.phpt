--TEST--
Errors: bare function-level type parameter used in `T::class` errors at runtime when the binding is not a class
--FILE--
<?php
function f<T>(): string {
    return T::class;
}
f::<int>();
?>
--EXPECTF--
Fatal error: Cannot resolve generic type parameter T at runtime: no binding was supplied and its bound is not a class in %s on line %d
