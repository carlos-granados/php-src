--TEST--
Async: send() into a generic generator and a T-typed return value are both bound to the turbofish T
--FILE--
<?php
function collector<T : object>(): Generator {
    $seen = [];
    while (true) {
        $x = yield T::class;
        if ($x === null) break;
        $seen[] = get_class($x);
    }
    return $seen;
}

$g = collector::<Countable>();
echo $g->current(), "\n";          // Countable (the yielded T::class)
$g->send(new ArrayObject([1]));    // ArrayObject is Countable
$g->send(new SplStack());          // SplStack is Countable
$g->send(null);                    // stop
var_dump($g->getReturn());
?>
--EXPECT--
Countable
array(2) {
  [0]=>
  string(11) "ArrayObject"
  [1]=>
  string(8) "SplStack"
}
