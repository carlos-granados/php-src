--TEST--
Reification: T::class reflects the explicit turbofish binding; the substituted parameter type is enforced
--FILE--
<?php
class Foo { public string $kind = "foo"; }
class Bar { public string $kind = "bar"; }

function kind<T : object>(T $x): string {
    return T::class;
}

echo kind::<Foo>(new Foo()), "\n";
echo kind::<Bar>(new Bar()), "\n";

try {
    kind::<Foo>(new Bar());
} catch (TypeError $e) {
    echo "TypeError: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
Foo
Bar
TypeError: kind(): Argument #1 ($x) must be of type Foo, Bar given, called in %s on line %d
