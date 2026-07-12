--TEST--
Errors: a forwarded TYPE_PARAMETER ref in a `new C::<T>()` turbofish resolves from the caller's explicit binding
--FILE--
<?php
// Companion to type_param_in_new_expression.phpt (`new T()`). Same shape, but
// the T sits inside a turbofish on `new C::<T>(...)` instead of being the
// class itself. Under strict binding the caller must always pin T, so the
// old "nothing pins T" failure mode is unreachable via static calls; a
// dynamic call fails up front with ArgumentCountError instead of producing
// a broken-refs monomorph that crashes far from the cause.
final readonly class Box<U = mixed> {
    public function __construct(public array $items = []) {}
}

function makeBox<T>(): Box {
    return new Box::<T>([]);
}

try {
    $fn = 'makeBox';
    $fn();
    echo "no error??\n";
} catch (Error $e) {
    echo "ok: " . $e->getMessage() . "\n";
}

// An explicit class binding forwards into the inner turbofish.
class Base {}
function makeBoxFromClass<T : Base>(): Box {
    return new Box::<T>([]);
}
$b = makeBoxFromClass::<Base>();
var_dump($b::class);

// Scalar bindings forward the same way.
$b = makeBox::<int>();
var_dump($b::class);
?>
--EXPECT--
ok: Too few generic type arguments to makeBox(), 0 passed and exactly 1 expected
string(9) "Box<Base>"
string(8) "Box<int>"
