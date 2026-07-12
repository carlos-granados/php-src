--TEST--
Strict binding: a turbofish first-class callable captures T and the body can use it
--FILE--
<?php
function classOfT<T : object>(): string {
    return T::class;
}

$dt = classOfT::<DateTime>(...);
$ex = classOfT::<Exception>(...);
echo $dt(), "\n";
echo $ex(), "\n";
/* Same creation site executed twice with different type arguments must
 * not share a cached closure. */
function make(string $which): Closure {
    if ($which === 'dt') {
        return classOfT::<DateTime>(...);
    }
    return classOfT::<Exception>(...);
}
echo make('dt')(), "\n";
echo make('ex')(), "\n";
echo make('dt')(), "\n";
?>
--EXPECT--
DateTime
Exception
DateTime
Exception
DateTime
