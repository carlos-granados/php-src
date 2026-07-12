--TEST--
Strict binding: explicit turbofish forwarding chains an outer T through calls and new
--FILE--
<?php
class Box<T> {
    public function __construct(public T $value) {}
}
function b<T>(T $value): Box<T> { return new Box::<T>($value); }
function a<T>(T $value): Box<T> { return b::<T>($value); }

$i = a::<int>(42);
var_dump(get_class($i));
var_dump($i instanceof Box::<int>);
var_dump($i instanceof Box::<string>);

$s = a::<string>("hi");
var_dump(get_class($s));
var_dump($s instanceof Box::<string>);
var_dump($s instanceof Box::<int>);
?>
--EXPECT--
string(8) "Box<int>"
bool(true)
bool(false)
string(11) "Box<string>"
bool(true)
bool(false)
