--TEST--
Type args: never/void/null/mixed are currently accepted as type arguments (the engine treats a type argument as an opaque type name; semantic rejection is left to static analysis)
--FILE--
<?php
// NOTE: unlike a value/return-type position, the engine does not reject the
// "bottom" types here — a type argument is just an opaque type name that names
// a monomorph. Whether `never`/`void` should be rejected as type arguments is
// a static-analysis / RFC-design question; this test locks in current runtime
// behavior so a future rejection is a deliberate change.
class Box<T> {
    public function __construct(public mixed $v) {}
}

foreach (['never', 'void', 'null', 'mixed'] as $t) {
    $c = eval("return new Box::<$t>(1);");
    echo "$t => ", $c::class, "\n";
}
?>
--EXPECT--
never => Box<never>
void => Box<void>
null => Box<null>
mixed => Box<mixed>
