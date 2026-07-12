--TEST--
Erasure: unbounded T accepts anything (mixed)
--FILE--
<?php
function id<T>(T $x): T { return $x; }
var_dump(id::<int>(42));
var_dump(id::<string>("hello"));
var_dump(id::<mixed>(null));
var_dump(id::<array>([1, 2]));
?>
--EXPECT--
int(42)
string(5) "hello"
NULL
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
