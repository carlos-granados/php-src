--TEST--
Async: a generic generator iterated inside a Fiber preserves both frames' T bindings across suspends
--FILE--
<?php
function items<T : object>(): Generator {
    yield T::class;
    Fiber::suspend("mid:" . T::class);
    yield T::class;
}

$fiber = new Fiber(function () {
    $out = [];
    foreach (items::<DateTime>() as $v) {
        $out[] = $v;
    }
    return $out;
});

echo $fiber->start(), "\n";      // "mid:DateTime" (generator body suspended the fiber)
$ret = $fiber->resume();
var_dump($fiber->getReturn());
?>
--EXPECT--
mid:DateTime
array(2) {
  [0]=>
  string(8) "DateTime"
  [1]=>
  string(8) "DateTime"
}
