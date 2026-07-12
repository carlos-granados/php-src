--TEST--
Strict binding: dynamically dispatched naked generic call throws ArgumentCountError before the body runs
--FILE--
<?php
class Box<T> {
    public function __construct(public T $value) {}
}
function b<T>(T $value) { new Box::<T>($value); }

$fn = 'b';
try {
    $fn(42);
} catch (ArgumentCountError $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}

try {
    call_user_func('b', 42);
} catch (ArgumentCountError $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}
?>
--EXPECT--
ArgumentCountError: Too few generic type arguments to b(), 0 passed and exactly 1 expected
ArgumentCountError: Too few generic type arguments to b(), 0 passed and exactly 1 expected
