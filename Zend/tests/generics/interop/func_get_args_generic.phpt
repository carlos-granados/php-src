--TEST--
Interop: func_get_args / func_num_args inside a generic frame report the value arguments, not type arguments
--FILE--
<?php
function collect<T>(int $a, string $b, ...$rest): array {
    return [func_num_args(), func_get_args()];
}

var_dump(collect::<int>(1, "x"));
var_dump(collect::<string>(1, "x", 2, 3));

// Inside a monomorph method too.
class Box<T> {
    public function m(int $a, int $b): array {
        return [func_num_args(), func_get_args()];
    }
}
var_dump((new Box::<int>())->m(10, 20));
?>
--EXPECT--
array(2) {
  [0]=>
  int(2)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    string(1) "x"
  }
}
array(2) {
  [0]=>
  int(4)
  [1]=>
  array(4) {
    [0]=>
    int(1)
    [1]=>
    string(1) "x"
    [2]=>
    int(2)
    [3]=>
    int(3)
  }
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  array(2) {
    [0]=>
    int(10)
    [1]=>
    int(20)
  }
}
