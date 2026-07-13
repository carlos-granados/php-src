--TEST--
Async: a Fiber whose body is a closure created in a generic frame resolves the captured T across suspend/resume
--FILE--
<?php
function makeFiber<T : object>(): Fiber {
    return new Fiber(function () {
        $before = T::class;
        Fiber::suspend($before);
        return T::class;
    });
}

$f = makeFiber::<DateTime>();
echo $f->start(), "\n";        // suspended value: T resolved inside the fiber
$f->resume();
echo $f->getReturn(), "\n";    // return value: T still resolved after resume

$g = makeFiber::<Exception>();
echo $g->start(), "\n";
$g->resume();
echo $g->getReturn(), "\n";
?>
--EXPECT--
DateTime
DateTime
Exception
Exception
