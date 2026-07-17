--TEST--
Reflection getClosure on a generic callee with required type parameters errors at creation
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
    (new ReflectionFunction('f'))->getClosure();
} catch (ReflectionException $e) {
    echo $e->getMessage(), "\n";
}
try {
    (new ReflectionMethod('C', 'm'))->getClosure(new C);
} catch (ReflectionException $e) {
    echo $e->getMessage(), "\n";
}
try {
    (new ReflectionMethod('C', 'sm'))->getClosure();
} catch (ReflectionException $e) {
    echo $e->getMessage(), "\n";
}

// Defaulted-only type parameters stay allowed and resolve like a naked call.
var_dump((new ReflectionFunction('g'))->getClosure()(3));
var_dump((new ReflectionMethod('C', 'd'))->getClosure(new C)("ok"));

// A ReflectionFunction over a bound turbofish FCC closure returns it as-is.
$fc = f::<int>(...);
var_dump((new ReflectionFunction($fc))->getClosure()(7));
?>
--EXPECT--
Cannot create closure for generic function f(): requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. f::<...>(...)
Cannot create closure for generic function C::m(): requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. C::m::<...>(...)
Cannot create closure for generic function C::sm(): requires 1 explicit type argument; create the closure with a first-class callable instead, e.g. C::sm::<...>(...)
int(3)
string(2) "ok"
int(7)
