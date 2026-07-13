--TEST--
Monomorph: var_export emits the canonical class name (KNOWN LIMITATION: the output is not re-evaluable because `Name<...>` is not valid class-reference syntax)
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

// KNOWN LIMITATION: unlike ordinary classes, the var_export output of a
// monomorph is NOT round-trippable via eval() — the canonical name embeds
// `<int>`, which the parser reads as comparison operators in class-name
// position. This test locks in the current behavior so a future fix (e.g.
// emitting turbofish-compatible reconstruction) is a deliberate change.
try {
    eval('return ' . $code . ';');
    echo "eval: OK\n";
} catch (ParseError $e) {
    echo "eval: ParseError (not round-trippable)\n";
}

// __set_state IS reachable via a dynamic class-name string holding the
// canonical monomorph name, which is how unserialize/var_export consumers
// would reconstruct it in practice.
$cls = 'Point<int>';
$p2 = $cls::__set_state(['x' => 3, 'y' => 4]);
var_dump($p2::class, $p2->x, $p2->y);
?>
--EXPECTF--
\Point<int>::__set_state(array(
   'x' => 3,
   'y' => 4,
))
eval: ParseError (not round-trippable)
string(10) "Point<int>"
int(3)
int(4)
