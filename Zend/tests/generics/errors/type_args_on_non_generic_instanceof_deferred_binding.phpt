--TEST--
Type arguments on a non-generic class in `instanceof` are a compile-time error even when class binding is deferred
--FILE--
<?php
// See type_args_on_non_generic_catch_deferred_binding.phpt: a conditionally-
// declared class is never early-bound, so at the point the instanceof
// expression below compiles, "Plain" is not yet registered under its plain
// name in the compiler's class table. Before the fix this silently returned
// bool(false) instead of raising -- instanceof and catch share the same
// pre-erasure type-args validation.
if (true) {
    class Plain {}
}
var_dump(new Plain() instanceof Plain::<int>);
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
