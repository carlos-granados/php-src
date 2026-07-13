--TEST--
Interop: WeakMap keyed by monomorph instances tracks identity and releases entries on GC
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $v) {}
}

$wm = new WeakMap();
$a = new Box::<int>(1);
$b = new Box::<string>("x");
$c = new Box::<int>(2);   // same monomorph class as $a, distinct instance

$wm[$a] = "a";
$wm[$b] = "b";
$wm[$c] = "c";

var_dump(count($wm));
var_dump($wm[$a], $wm[$b], $wm[$c]);

// Dropping one live reference removes exactly its entry.
unset($b);
gc_collect_cycles();
var_dump(count($wm));
var_dump(isset($wm[$a]), $wm[$c]);
?>
--EXPECT--
int(3)
string(1) "a"
string(1) "b"
string(1) "c"
int(2)
bool(true)
string(1) "c"
