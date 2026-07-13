--TEST--
JIT (function mode): generic calls and monomorph instanceof in a hot loop remain correct
--XFAIL--
Known Phase-5 JIT bug: function-mode JIT mis-compiles some generic function
calls with concrete turbofish into a by-name lookup of the function-monomorph
name (e.g. "unwrap<int>"), which does not exist as a named function (function
monomorphs are per-frame), raising "Call to undefined function unwrap<int>()".
Tracing JIT and the interpreter handle the same code correctly. Belongs to the
JIT-for-generics workstream (Phase 5).
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=64M
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $v) {}
}
function unwrap<T>(Box $b): mixed { return $b->v; }

$bi = new Box::<int>(10);
$bs = new Box::<string>("z");

$sum = 0;
$hits = 0;
for ($i = 0; $i < 80000; $i++) {
    $sum += unwrap::<int>($bi);
    if ($bi instanceof Box::<int>) $hits++;
    if ($bs instanceof Box::<int>) $hits--;   // never true
}
var_dump($sum);
var_dump($hits);
var_dump(unwrap::<string>($bs));
?>
--EXPECT--
int(800000)
int(80000)
string(1) "z"
