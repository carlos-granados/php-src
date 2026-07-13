--TEST--
Interop: a monomorph method backtrace reports the template scope in 'class' (shared method body); the monomorph is reachable via the frame 'object'
--FILE--
<?php
// A monomorph shares its methods with the template, so the method's scope —
// and therefore debug_backtrace()['class'] / getTrace()['class'] — is the
// template name, not the canonical monomorph name. The exact runtime type is
// still available through the backtrace frame's 'object' entry.
class Box<T> {
    public function where(): array {
        $frame = debug_backtrace()[0];
        return [$frame['class'], $frame['function'], $frame['object']::class];
    }
}

$b = new Box::<int>();
var_dump($b->where());

class Thrower<T> {
    public function boom(): void { throw new RuntimeException("x"); }
}
try {
    (new Thrower::<int>())->boom();
} catch (RuntimeException $e) {
    $t = $e->getTrace()[0];
    var_dump($t['class'], $t['function']);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "Box"
  [1]=>
  string(5) "where"
  [2]=>
  string(8) "Box<int>"
}
string(7) "Thrower"
string(4) "boom"
