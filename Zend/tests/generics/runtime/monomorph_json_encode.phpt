--TEST--
Monomorph: json_encode serializes public properties (the canonical class name is not part of JSON)
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $value, public string $label = "b") {}
}

$b = new Box::<int>(42);
echo json_encode($b), "\n";

$s = new Box::<string>("hi", "s");
echo json_encode($s), "\n";

// JsonSerializable on a generic template.
class Wrap<T> implements JsonSerializable {
    public function __construct(private mixed $v) {}
    public function jsonSerialize(): array { return ['wrapped' => $this->v]; }
}
echo json_encode(new Wrap::<float>(1.5)), "\n";
?>
--EXPECT--
{"value":42,"label":"b"}
{"value":"hi","label":"s"}
{"wrapped":1.5}
