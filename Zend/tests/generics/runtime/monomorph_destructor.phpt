--TEST--
Runtime: __destruct on monomorph instances runs, and T resolves inside the destructor
--FILE--
<?php
class Res<T : object> {
    public function __construct(public string $id) {}
    public function __destruct() {
        echo "destruct ", $this->id, " (", T::class, ")\n";
    }
}

$a = new Res::<DateTime>("a");
$b = new Res::<Exception>("b");

echo "before unset\n";
unset($a);
echo "between\n";
unset($b);
echo "after unset\n";

// Instances alive at shutdown are destructed in the engine's shutdown sweep.
$c = new Res::<DateTime>("c");
echo "end of script\n";
?>
--EXPECT--
before unset
destruct a (DateTime)
between
destruct b (Exception)
after unset
end of script
destruct c (DateTime)
