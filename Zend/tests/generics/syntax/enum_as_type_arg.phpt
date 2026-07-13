--TEST--
Generic syntax: an enum used as a type argument monomorphizes and resolves like any class
--FILE--
<?php
enum Suit: string {
    case Hearts = 'H';
    case Spades = 'S';
}

class Box<T : object> {
    public function __construct(public T $value) {}
    public function kind(): string { return T::class; }
}

$b = new Box::<Suit>(Suit::Hearts);
var_dump($b::class);
var_dump($b->kind());
var_dump($b->value === Suit::Hearts);
var_dump($b instanceof Box::<Suit>);
var_dump($b instanceof Box::<Box>);

// A wrong enum-typed value is rejected against the reified T.
enum Rank { case Ace; }
try {
    new Box::<Suit>(Rank::Ace);
} catch (TypeError $e) {
    echo "TypeError\n";
}
?>
--EXPECT--
string(9) "Box<Suit>"
string(4) "Suit"
bool(true)
bool(true)
bool(false)
TypeError
