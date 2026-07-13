--TEST--
Interop: == and === across monomorphs — different type arguments are different classes
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $v) {}
}

$i1 = new Box::<int>(1);
$i2 = new Box::<int>(1);
$i3 = new Box::<int>(2);
$s1 = new Box::<string>("1");

// Same monomorph class, equal properties.
var_dump($i1 == $i2);
// Same monomorph class, different property.
var_dump($i1 == $i3);
// Same instance identity.
var_dump($i1 === $i1);
var_dump($i1 === $i2);
// Different monomorph classes are never ==, even with loosely-equal payloads.
var_dump($i1 == $s1);
var_dump($i1 === $s1);
// get_debug_type reflects the monomorph name.
var_dump(get_debug_type($i1), get_debug_type($s1));
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
bool(false)
string(8) "Box<int>"
string(11) "Box<string>"
