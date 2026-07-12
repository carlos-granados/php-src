--TEST--
Variance: function-origin type parameters checked uniformly with class-origin
--FILE--
<?php
function take<in T>(T $x): int { return 0; }
function make<out T>(): T { return null; }
function transform<in I, out O>(I $input): O { return null; }

$closure_take = function <in T = mixed>(T $x): int { return 0; };
$closure_make = function <out T = mixed>(): T { return null; };
$arrow_take   = fn<in T = mixed>(T $x): int => 0;
$arrow_make   = fn<out T = mixed>(): T => null;

class A<out T = mixed> {
    public function map<in A_, out B>(A_ $a): B { return null; }
}

take::<int>(42);
make::<mixed>();
transform::<int, mixed>(1);
$closure_take(7);
$closure_make();
$arrow_take(7);
$arrow_make();
(new A)->map::<int, mixed>(42);
echo "ok\n";
?>
--EXPECT--
ok
