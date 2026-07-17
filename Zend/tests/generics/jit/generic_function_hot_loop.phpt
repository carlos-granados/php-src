--TEST--
JIT: a generic function with turbofish called in a hot loop produces correct results (JIT'd caller, per-frame T binding)
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=tracing
opcache.jit_buffer_size=64M
--FILE--
<?php
function id<T>(T $x): T { return $x; }
function add<T>(int $a, int $b): int { return $a + $b; }

$acc = 0;
for ($i = 0; $i < 100000; $i++) {
    $acc += id::<int>($i) - id::<int>($i);   // net zero, but exercises the hot generic call
    $acc = add::<int>($acc, 1);
}
var_dump($acc);

// Alternating turbofish types in the loop must not let the JIT alias bindings.
$s = "";
for ($i = 0; $i < 50000; $i++) {
    $s = id::<string>("a");
}
var_dump($s);
?>
--EXPECT--
int(100000)
string(1) "a"
