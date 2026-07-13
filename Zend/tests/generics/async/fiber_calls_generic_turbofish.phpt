--TEST--
Async: a generic call with turbofish made from inside a Fiber body binds and resolves T correctly
--FILE--
<?php
class Box<T> {
    public function __construct(public T $value) {}
}
function wrap<T>(T $v): Box<T> {
    return new Box::<T>($v);
}

$fiber = new Fiber(function () {
    $a = wrap::<int>(1);
    Fiber::suspend($a::class);
    $b = wrap::<string>("x");
    return $b::class;
});

echo $fiber->start(), "\n";       // Box<int>
echo $fiber->resume(), "\n";      // (resume return value is unused here)
echo $fiber->getReturn(), "\n";   // Box<string>
?>
--EXPECT--
Box<int>

Box<string>
