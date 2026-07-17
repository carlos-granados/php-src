--TEST--
Turbofish first-class callable over an existing closure value captures the binding
--FILE--
<?php
declare(strict_types=1);

$c = function <T>(T $x): string { return T::class; };

// A new closure is created; the binding is captured and enforced.
$gd = $c::<DateTime>(...);
var_dump($gd === $c);
var_dump($gd(new DateTime));
try { $gd(new ArrayObject); } catch (TypeError $e) { echo "enforced: ", $e->getMessage(), "\n"; }

// Independent bindings from the same source closure.
$ge = $c::<Exception>(...);
var_dump($ge(new Exception));
var_dump($gd(new DateTime));

// The source closure stays unbound.
try { $c(new DateTime); } catch (ArgumentCountError $e) { echo "source unbound: ", $e->getMessage(), "\n"; }

// A naked FCC over the closure is the identity; it can be bound later.
$h = $c(...);
var_dump($h === $c);
var_dump($h::<DateTime>(new DateTime));

// Rebinding an already-bound closure yields a third, independent closure.
$re = $gd::<Exception>(...);
var_dump($re(new Exception));
var_dump($gd(new DateTime));

// $this and scope survive the capture.
class W {
    public function make(): Closure {
        return function <T>(T $x): array { return [T::class, get_class($this)]; };
    }
}
$m = (new W)->make()::<DateTime>(...);
var_dump($m(new DateTime));

// Turbofish through the __invoke trampoline is rejected (it is not generic).
try {
    $t = $c->__invoke::<DateTime>(...);
} catch (ArgumentCountError $e) {
    echo "trampoline: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
bool(false)
string(8) "DateTime"
enforced: {closure:%s:%d}(): Argument #1 ($x) must be of type DateTime, ArrayObject given
string(9) "Exception"
string(8) "DateTime"
source unbound: Too few generic type arguments to {closure:%s:%d}(), 0 passed and exactly 1 expected
bool(true)
string(8) "DateTime"
string(9) "Exception"
string(8) "DateTime"
array(2) {
  [0]=>
  string(8) "DateTime"
  [1]=>
  string(1) "W"
}
trampoline: Too many generic type arguments to Closure::__invoke(), 1 passed and exactly 0 expected
