--TEST--
JIT: a monomorph method called in a hot loop stays correct (monomorphs are excluded from JIT; the JIT'd caller must still dispatch correctly)
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
class Counter<T> {
    private int $n = 0;
    public function tick(int $by): int { return $this->n += $by; }
    public function value(): int { return $this->n; }
}

$ci = new Counter::<int>();
$cs = new Counter::<string>();   // distinct monomorph, distinct state

for ($i = 0; $i < 100000; $i++) {
    $ci->tick(1);
    if ($i % 2 === 0) { $cs->tick(2); }
}
var_dump($ci->value());
var_dump($cs->value());
var_dump($ci::class, $cs::class);
var_dump($ci instanceof Counter::<int>, $ci instanceof Counter::<string>);
?>
--EXPECT--
int(100000)
int(100000)
string(12) "Counter<int>"
string(15) "Counter<string>"
bool(true)
bool(false)
