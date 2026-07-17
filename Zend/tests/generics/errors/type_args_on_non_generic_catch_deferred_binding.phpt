--TEST--
Type arguments on a non-generic class in `catch` are a compile-time error even when class binding is deferred
--FILE--
<?php
// A conditionally-declared class is never early-bound (its declaration is
// deferred to a runtime ZEND_DECLARE_CLASS opcode regardless of opcache
// configuration), so at the point the catch clause below compiles, "MyErr"
// is not yet registered in the compiler's class table under its plain name.
// The "must be generic" check must still fire instead of silently letting
// this parse as valid and never actually catching the exception.
if (true) {
    class MyErr extends Exception {}
}
try {
    throw new MyErr('boom');
} catch (MyErr<int> $e) {
}
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class MyErr in %s on line %d
