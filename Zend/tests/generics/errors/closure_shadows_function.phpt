--TEST--
Errors: closure with its own type parameter cannot be declared inside a generic function
--FILE--
<?php
function f<T>(): void {
    $cl = function <T>(): void {};
}
?>
--EXPECTF--
Fatal error: A function, method, or closure with its own type parameters cannot be declared inside another generic function, method, or closure in %s on line %d
