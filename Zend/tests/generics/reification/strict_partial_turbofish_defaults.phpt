--TEST--
Strict binding: partial turbofish pins leading slots, declared defaults fill the rest
--FILE--
<?php
class Foo {}
class Bar {}

function pair<A, B = Bar>(A $a, B $b): array {
    return [A::class, B::class];
}

/* Both pinned explicitly. */
var_dump(pair::<Foo, Foo>(new Foo, new Foo));
/* B falls back to its declared default. */
var_dump(pair::<Foo>(new Foo, new Bar));
?>
--EXPECT--
array(2) {
  [0]=>
  string(3) "Foo"
  [1]=>
  string(3) "Foo"
}
array(2) {
  [0]=>
  string(3) "Foo"
  [1]=>
  string(3) "Bar"
}
