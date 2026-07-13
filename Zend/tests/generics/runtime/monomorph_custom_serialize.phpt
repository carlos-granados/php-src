--TEST--
Monomorph: __serialize/__unserialize round-trips through the canonical monomorph name
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $value) {}
    public function __serialize(): array { return ['v' => $this->value]; }
    public function __unserialize(array $data): void { $this->value = $data['v']; }
}

$b = new Box::<int>(7);
$s = serialize($b);
echo $s, "\n";

$b2 = unserialize($s);
var_dump($b2::class);
var_dump($b2 instanceof Box::<int>);
var_dump($b2->value);
var_dump($b::class === $b2::class);
?>
--EXPECTF--
O:8:"Box<int>":1:{s:1:"v";i:7;}
string(8) "Box<int>"
bool(true)
int(7)
bool(true)
