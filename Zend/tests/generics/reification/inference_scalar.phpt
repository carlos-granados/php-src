--TEST--
Reification: scalar and array type arguments reify via turbofish; naked calls take the default
--FILE--
<?php
function tparams<T = mixed>(T $a): array { return func_get_type_args(); }

var_dump(tparams::<int>(1)["T"]);
var_dump(tparams::<string>("foo")["T"]);
var_dump(tparams::<float>(1.5)["T"]);
var_dump(tparams::<bool>(true)["T"]);
var_dump(tparams::<bool>(false)["T"]);
var_dump(tparams::<array>([1, 2])["T"]);
var_dump(tparams::<stdClass>(new stdClass())["T"]);
/* No turbofish: T is the declared default, regardless of the value. */
var_dump(tparams(1)["T"]);
var_dump(tparams(new stdClass())["T"]);
?>
--EXPECT--
string(3) "int"
string(6) "string"
string(5) "float"
string(4) "bool"
string(4) "bool"
string(5) "array"
string(8) "stdClass"
string(5) "mixed"
string(5) "mixed"
