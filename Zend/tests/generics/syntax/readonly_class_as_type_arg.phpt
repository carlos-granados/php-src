--TEST--
Generic syntax: a readonly class works as a type argument and inside a generic container
--FILE--
<?php
readonly class Money {
    public function __construct(public int $cents) {}
}

class Box<T : object> {
    public function __construct(public T $value) {}
    public function kind(): string { return T::class; }
}

$b = new Box::<Money>(new Money(500));
var_dump($b::class);
var_dump($b->kind());
var_dump($b->value->cents);
var_dump($b instanceof Box::<Money>);

// A readonly generic class monomorphizes too.
readonly class Wrapper<T : object> {
    public function __construct(public T $item) {}
}
$w = new Wrapper::<DateTimeImmutable>(new DateTimeImmutable('2020-01-01'));
var_dump($w::class);
var_dump($w->item instanceof DateTimeImmutable);

// The wrapped readonly property stays readonly.
try {
    $b->value->cents = 1;
} catch (Error $e) {
    echo "readonly enforced\n";
}
?>
--EXPECT--
string(10) "Box<Money>"
string(5) "Money"
int(500)
bool(true)
string(26) "Wrapper<DateTimeImmutable>"
bool(true)
readonly enforced
