--TEST--
Async: `yield from` between two generic generators keeps each frame's own T binding
--FILE--
<?php
function inner<T : object>(): Generator {
    yield "inner:" . T::class;
    yield "inner:" . T::class;
}
function outer<T : object>(): Generator {
    yield "outer:" . T::class;
    yield from inner::<Exception>();
    yield "outer:" . T::class;
}

foreach (outer::<DateTime>() as $v) {
    echo $v, "\n";
}
?>
--EXPECT--
outer:DateTime
inner:Exception
inner:Exception
outer:DateTime
