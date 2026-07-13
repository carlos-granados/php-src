--TEST--
Generic syntax: turbofish combines with named and spread arguments
--FILE--
<?php
function mk<T>(int $a, int $b, int $c = 0): array {
    return [$a, $b, $c];
}

// Named args in any order, with turbofish.
var_dump(mk::<int>(b: 2, a: 1));
var_dump(mk::<int>(a: 1, b: 2, c: 3));

// Spread + turbofish.
$args = [10, 20, 30];
var_dump(mk::<int>(...$args));

// Spread of named args + turbofish.
$named = ['c' => 9, 'a' => 7, 'b' => 8];
var_dump(mk::<int>(...$named));

// new with turbofish + named args.
class Pair<A, B> {
    public function __construct(public mixed $left, public mixed $right) {}
}
$p = new Pair::<int, string>(right: "r", left: 1);
var_dump($p::class, $p->left, $p->right);
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(0)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
array(3) {
  [0]=>
  int(7)
  [1]=>
  int(8)
  [2]=>
  int(9)
}
string(16) "Pair<int,string>"
int(1)
string(1) "r"
