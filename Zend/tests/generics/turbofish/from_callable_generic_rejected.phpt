--TEST--
Closure::fromCallable on a generic callee with required type parameters errors at creation
--FILE--
<?php
declare(strict_types=1);

function f<T>(T $x): T { return $x; }
function g<T = int>(T $x): T { return $x; }
class C {
    public function m<T>(T $x): T { return $x; }
    public static function sm<T>(T $x): T { return $x; }
    public function d<T = string>(T $x): T { return $x; }
}

// Required type parameter: rejected at creation, not at invocation.
try {
    Closure::fromCallable('f');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Same for instance and static methods.
try {
    Closure::fromCallable([new C, 'm']);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
try {
    Closure::fromCallable('C::sm');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Defaulted-only type parameters stay allowed and resolve like a naked call.
$gd = Closure::fromCallable('g');
var_dump($gd(3));
$dd = Closure::fromCallable([new C, 'd']);
var_dump($dd("ok"));

// A closure created with turbofish FCC round-trips through fromCallable.
$fc = f::<int>(...);
$fc2 = Closure::fromCallable($fc);
var_dump($fc2(7));
?>
--EXPECT--
Failed to create closure from callable: generic function f() requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. f::<...>(...)
Failed to create closure from callable: generic function C::m() requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. C::m::<...>(...)
Failed to create closure from callable: generic function C::sm() requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. C::sm::<...>(...)
int(3)
string(2) "ok"
int(7)
