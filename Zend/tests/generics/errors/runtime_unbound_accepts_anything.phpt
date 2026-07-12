--TEST--
Errors: an explicit mixed binding accepts any value
--FILE--
<?php
function f<T>(T $x): T { return $x; }

var_dump(f::<mixed>(42));
var_dump(f::<mixed>("foo"));
var_dump(f::<mixed>(null));
var_dump(f::<mixed>(new stdClass));
echo "no errors\n";
?>
--EXPECT--
int(42)
string(3) "foo"
NULL
object(stdClass)#1 (0) {
}
no errors
