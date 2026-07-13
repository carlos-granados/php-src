--TEST--
Extends-with-args: reified `instanceof Parent::<int>` through a forwarding generic parent (KNOWN GAP — interfaces reify transitively but parent classes do not yet)
--XFAIL--
Transitive reified instanceof is inconsistent between interfaces and parent
classes. For `class Derived<T> extends Base<T> implements Producer<T>`,
`new Derived::<int>() instanceof Producer::<int>` is TRUE (the synthesis walks
the ancestor chain and inserts substituted interface monomorphs into
interfaces[]), but `... instanceof Base::<int>` is FALSE: a monomorph extends
its template, so the substituted parent class Base<int> is never inserted into
the linear parent chain. Fixing this needs the synthesis to make substituted
parent-class monomorphs discoverable in the ancestry (an inheritance-model
change), tracked separately.
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
