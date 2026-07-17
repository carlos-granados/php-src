--TEST--
Opcache reset: runtime monomorphs survive an opcache_reset() (they live in the per-request class table, not SHM)
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
--EXTENSIONS--
opcache
--FILE--
<?php
class Box<T : object> {
    public function __construct(public T $value) {}
    public function kind(): string { return T::class; }
}

$a = new Box::<DateTime>(new DateTime());
var_dump($a::class, $a->kind());

// Reset opcache mid-request. Already-synthesized monomorphs must keep working
// and new ones must still synthesize afterward.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

var_dump($a->kind());                  // still valid after reset
$b = new Box::<Exception>(new Exception("x"));   // new monomorph after reset
var_dump($b::class, $b->kind());
var_dump($a instanceof Box::<DateTime>);
var_dump($b instanceof Box::<Exception>);
?>
--EXPECT--
string(13) "Box<DateTime>"
string(8) "DateTime"
string(8) "DateTime"
string(14) "Box<Exception>"
string(9) "Exception"
bool(true)
bool(true)
