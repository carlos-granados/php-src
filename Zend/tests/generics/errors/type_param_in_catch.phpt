--TEST--
catch (T $e) silently does not match when T's binding is not a class
--FILE--
<?php
function f<T>(): void {
    // T is bound to a scalar (int), which is not a class. catch (T $e)
    // is therefore "catch nothing"; the thrown exception propagates out.
    try { throw new Exception("boom"); } catch (T $e) { echo "caught\n"; }
}
try {
    f::<int>();
} catch (Throwable $e) {
    echo "outer: ", $e->getMessage(), "\n";
}
?>
--EXPECT--
outer: boom
