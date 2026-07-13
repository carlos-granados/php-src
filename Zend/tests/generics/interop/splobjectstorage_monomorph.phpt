--TEST--
Interop: SplObjectStorage distinguishes instances of different monomorphs of the same template
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $v) {}
}

$s = new SplObjectStorage();
$i = new Box::<int>(1);
$j = new Box::<string>("x");
$s[$i] = "int-box";
$s[$j] = "str-box";

var_dump(count($s));
var_dump(isset($s[$i]), isset($s[$j]));
var_dump($s[$i], $s[$j]);

// A fresh instance of the same monomorph is a distinct object.
$i2 = new Box::<int>(2);
var_dump(isset($s[$i2]));

unset($s[$i]);
var_dump(count($s), isset($s[$i]), isset($s[$j]));
?>
--EXPECT--
int(2)
bool(true)
bool(true)
string(7) "int-box"
string(7) "str-box"
bool(false)
int(1)
bool(false)
bool(true)
