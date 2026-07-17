--TEST--
Turbofish on dynamic callees: bindings are enforced, not just parsed
--FILE--
<?php
declare(strict_types=1);

function f<T>(T $x): T { return $x; }
class C { public function m<T>(T $x): T { return $x; } }

// Closure: wrong argument type against the bound T
$c = function <T>(T $x): T { return $x; };
try { $c::<string>(1); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// Closure: type argument violating a declared bound
$b = function <T : Countable>(T $x): int { return count($x); };
try { $b::<int>(1); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($b::<ArrayObject>(new ArrayObject([1, 2])));

// Closure: no turbofish and no default -> ArgumentCountError
try { $c(1); } catch (ArgumentCountError $e) { echo $e->getMessage(), "\n"; }

// Callable string: wrong argument type against the bound T
$fn = 'f';
try { $fn::<string>(2); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// Method via object variable: wrong argument type against the bound T
$o = new C;
try { $o->m::<string>(3); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
{closure:%s:%d}(): Argument #1 ($x) must be of type string, int given
Type argument 1 to call {closure:%s:%d}() does not satisfy the bound Countable on parameter T, int given
int(2)
Too few generic type arguments to {closure:%s:%d}(), 0 passed and exactly 1 expected
f(): Argument #1 ($x) must be of type string, int given, called in %s on line %d
C::m(): Argument #1 ($x) must be of type string, int given, called in %s on line %d
