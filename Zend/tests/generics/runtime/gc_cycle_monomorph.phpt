--TEST--
Runtime: cyclic references through monomorph instances are collected by the cycle GC
--FILE--
<?php
class Node<T> {
    public ?Node $next = null;
    public function __construct(public mixed $v) {}
}

gc_collect_cycles();
$before = gc_status()['collected'] ?? 0;

// Build a cycle across two distinct monomorphs and drop the external refs.
for ($i = 0; $i < 100; $i++) {
    $a = new Node::<int>(1);
    $b = new Node::<string>("x");
    $a->next = $b;
    $b->next = $a;   // cycle
    unset($a, $b);
}

$collected = gc_collect_cycles();
var_dump($collected > 0);           // the cycles were reclaimed
var_dump($a ?? 'gone');

// A self-cycle on a single monomorph instance.
$n = new Node::<int>(1);
$n->next = $n;
unset($n);
var_dump(gc_collect_cycles() > 0);
?>
--EXPECT--
bool(true)
string(4) "gone"
bool(true)
