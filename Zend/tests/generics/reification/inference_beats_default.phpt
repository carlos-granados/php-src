--TEST--
Reification: declared defaults fill unpinned slots; argument values never bind T
--FILE--
<?php
// A has a declared default (`mixed`). Calling foo(new Foo()) binds A to the
// default — never to the argument's runtime class. There is no value-directed
// inference.
class Foo {}
class Bar {}
class Sub extends Bar {}

class Box<T> {
    public function __construct(public T $value) {}
}

function foo<A = mixed>(A $a): array {
    return func_get_type_args();
}

// The default, not the argument's class.
var_dump(foo(new Foo()));
var_dump(foo(new Bar()));

// Explicit turbofish wins over the default: A is pinned to Bar even though
// the argument's runtime class is Sub (which satisfies the Bar binding).
var_dump(foo::<Bar>(new Sub()));

// Every unpinned slot takes its default, whether or not it appears as a
// value-parameter type.
function pick<A = mixed, U = Foo>(A $a): array {
    return func_get_type_args();
}
var_dump(pick(new Bar()));

// Partial turbofish: A is given explicitly, B takes its default even though
// a value argument of class Foo is present in B's position.
function two<A, B = mixed>(A $a, B $b): array {
    return func_get_type_args();
}
var_dump(two::<Bar>(new Bar(), new Foo()));

// Return type Box<A> reifies to the default binding. The forwarded turbofish
// `new Box::<A>` resolves A against it.
function wrap<A = mixed>(A $a): Box<A> {
    return new Box::<A>($a);
}
$b = wrap(new Foo());
var_dump($b::class);
?>
--EXPECT--
array(1) {
  ["A"]=>
  string(5) "mixed"
}
array(1) {
  ["A"]=>
  string(5) "mixed"
}
array(1) {
  ["A"]=>
  string(3) "Bar"
}
array(2) {
  ["A"]=>
  string(5) "mixed"
  ["U"]=>
  string(3) "Foo"
}
array(2) {
  ["A"]=>
  string(3) "Bar"
  ["B"]=>
  string(5) "mixed"
}
string(10) "Box<mixed>"
