--TEST--
Turbofish on dynamic callees: closure values, callable strings, dynamic member names
--FILE--
<?php
declare(strict_types=1);

function f<T>(T $x): T { return $x; }
class Box<T> { public function __construct(public T $v) {} }
class C {
    public function m<T>(T $x): T { return $x; }
    public static function sm<T>(T $x): T { return $x; }
}

// Closure value invoked with turbofish
$c = function <T>(T $x): T { return $x; };
var_dump($c::<int>(1));

// T is reified inside the closure body
$r = function <T>(mixed $x): bool { return $x instanceof T; };
var_dump($r::<Box>(new Box::<int>(0)));

// Parenthesised expression callee
var_dump(($c)::<int>(2));

// Array element callee
$arr = [$c];
var_dump($arr[0]::<int>(3));

// Callable string in a variable
$fn = 'f';
var_dump($fn::<int>(4));

// Method call on an object variable
$o = new C;
var_dump($o->m::<int>(5));

// Nullsafe method call
var_dump($o?->m::<int>(6));

// Dynamic method name
$name = 'm';
var_dump($o->$name::<int>(7));

// Static call through a class-name string
$cls = 'C';
var_dump($cls::sm::<int>(8));

// Dynamic static method name
$sname = 'sm';
var_dump($cls::$sname::<int>(9));

// FCC with turbofish, invoked later as a plain closure
$g = f::<int>(...);
var_dump($g(10));
?>
--EXPECT--
int(1)
bool(true)
int(2)
int(3)
int(4)
int(5)
int(6)
int(7)
int(8)
int(9)
int(10)
