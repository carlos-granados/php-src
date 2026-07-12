--TEST--
Recursive bounds: forward reference where the target has a non-generic class bound
--FILE--
<?php
class Foo {}
class Bar {}
function f<T: U, U: Foo>(T $x): U { return $x; }
var_dump(f::<Foo, Foo>(new Foo()));
try {
    f::<Bar, Foo>(new Bar());
} catch (TypeError $e) {
    echo "type error\n";
}
?>
--EXPECTF--
object(Foo)#%d (0) {
}
type error
