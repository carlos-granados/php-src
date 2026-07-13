--TEST--
Extends-with-args: reified `instanceof Parent::<int>` reifies transitively through a forwarding generic parent, consistently with interfaces
--FILE--
<?php
interface Producer<out T> { public function make(): T; }
class Base<T> implements Producer<T> { public function make(): T {} }
class Derived<T> extends Base<T> {}

$o = new Derived::<int>();

// Reified interface instanceof reifies transitively (works today).
var_dump($o instanceof Producer::<int>);   // true
var_dump($o instanceof Producer::<string>); // false

// Reified parent-class instanceof SHOULD reify transitively too.
var_dump($o instanceof Base::<int>);        // should be true
var_dump($o instanceof Base::<string>);     // false

// Erased relations always hold.
var_dump($o instanceof Base);
var_dump($o instanceof Producer);
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
