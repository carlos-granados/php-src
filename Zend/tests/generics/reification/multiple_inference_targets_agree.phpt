--TEST--
Reification: T in multiple arg positions — one explicit binding checks every argument
--FILE--
<?php
class Foo {}
class Bar extends Foo {}

function pair<T : object>(T $a, T $b): string {
    return T::class;
}

// Both args are Foo.
var_dump(pair::<Foo>(new Foo(), new Foo()));

// Both args are Bar.
var_dump(pair::<Bar>(new Bar(), new Bar()));

// T pinned to Foo; a Bar in the second position satisfies it (subtype).
var_dump(pair::<Foo>(new Foo(), new Bar()));

// T pinned to Bar; a plain Foo does not satisfy it.
try {
    pair::<Bar>(new Foo(), new Bar());
} catch (TypeError $e) {
    echo "TypeError\n";
}
?>
--EXPECT--
string(3) "Foo"
string(3) "Bar"
string(3) "Foo"
TypeError
