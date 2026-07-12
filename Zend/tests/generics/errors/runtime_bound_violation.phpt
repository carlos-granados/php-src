--TEST--
Errors: runtime type error when a turbofish argument violates the bound
--FILE--
<?php
class Animal {}
function f<T : Animal>(T $x): T { return $x; }

try {
    f::<int>(42);
    echo "no error\n";
} catch (TypeError $e) {
    echo "type error\n";
}
?>
--EXPECT--
type error
