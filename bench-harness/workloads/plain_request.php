<?php
/* Non-generic twin of generic_request.php: what you would hand-write today
 * without generics. Runs on BOTH master and reify, so it is the apples-to-apples
 * baseline for the generic version's cold/warm cost. Keep the two files
 * structurally identical except for the generics. */

class IntBox {
    public function __construct(public int $v) {}
    public function get(): int { return $this->v; }
}
class IntStrPair {
    public function __construct(public int $a, public string $b) {}
}
function id_int(int $x): int { return $x; }

$acc = 0;
for ($i = 0; $i < 5000; $i++) {
    $b = new IntBox($i);
    $acc += $b->get();
    $p = new IntStrPair($i, "x");
    $acc += id_int($p->a);
}
