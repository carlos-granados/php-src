--TEST--
Monomorph: var_export emits a re-evaluable ('Name<...>')::__set_state(...) form that round-trips
--FILE--
<?php
class Point<T> {
    public function __construct(public mixed $x = 0, public mixed $y = 0) {}
    public static function __set_state(array $props): static {
        $p = new static();
        $p->x = $props['x'];
        $p->y = $props['y'];
        return $p;
    }
}

$p = new Point::<int>(3, 4);
$code = var_export($p, true);
echo $code, "\n";

// The canonical monomorph name embeds `<int>`, which is not valid in a bare
// class-reference position, so var_export wraps it as a parenthesised string
// literal — a valid, re-evaluable class reference.
$p2 = eval('return ' . $code . ';');
var_dump($p2::class, $p2->x, $p2->y, $p2 instanceof Point::<int>);

// A plain (non-generic) class keeps the classic \Name::__set_state form.
class Plain {
    public $a = 1;
    public static function __set_state(array $p): static { $o = new static(); $o->a = $p['a']; return $o; }
}
echo var_export(new Plain(), true), "\n";

// __set_state is also reachable via a dynamic class-name string.
$cls = 'Point<int>';
$p3 = $cls::__set_state(['x' => 7, 'y' => 8]);
var_dump($p3::class, $p3->x, $p3->y);
?>
--EXPECTF--
('Point<int>')::__set_state(array(
   'x' => 3,
   'y' => 4,
))
string(10) "Point<int>"
int(3)
int(4)
bool(true)
\Plain::__set_state(array(
   'a' => 1,
))
string(10) "Point<int>"
int(7)
int(8)
