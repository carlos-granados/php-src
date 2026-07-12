--TEST--
Strict binding: first-class callable creation without turbofish is a compile error when type args are required
--FILE--
<?php
function id<T>(T $x): T { return $x; }
$f = id(...);
?>
--EXPECTF--
Fatal error: Creating a first-class callable for generic function id() requires explicit type arguments, e.g. id::<...>(...); 1 required in %s on line %d
