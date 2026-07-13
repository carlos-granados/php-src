--TEST--
Interop: cloning a monomorph instance keeps its class and __clone sees the bound T
--FILE--
<?php
class Box<T : object> {
    public T $v;
    public function __construct(T $v) { $this->v = $v; }
    public function __clone() { echo "clone of ", T::class, "\n"; }
    public function kind(): string { return T::class; }
}

$a = new Box::<DateTime>(new DateTime('2020-01-01'));
$b = clone $a;

var_dump($b::class);
var_dump($b->kind());
var_dump($a::class === $b::class);   // same monomorph
var_dump($a !== $b);                 // distinct instances
var_dump($a->v === $b->v);           // shallow clone shares the object property
?>
--EXPECT--
clone of DateTime
string(13) "Box<DateTime>"
string(8) "DateTime"
bool(true)
bool(true)
bool(true)
