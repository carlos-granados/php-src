--TEST--
Runtime: two unrelated class templates monomorphized with the same binding share substituted arg_info content correctly, including through closures and inheritance
--FILE--
<?php
// Box<T> and Crate<T> are unrelated templates but produce byte-identical
// substituted arg_info shapes for get()/fetch() when both are bound to the
// same concrete type -- the case EG(subst_arg_info_cache) (see
// Zend/zend_globals.h) is designed to dedup.
class Box<T> {
    public function __construct(public T $value) {}
    public function get(): T { return $this->value; }
}
class Crate<T> {
    public function __construct(public T $item) {}
    public function fetch(): T { return $this->item; }
}

// A generic parent + child (real inheritance, not just template-to-monomorph)
// also hits the same dedup path via zend_maybe_substitute_inherited_method.
class Base<T> {
    protected T $stored;
    public function __construct(T $v) { $this->stored = $v; }
    public function peek(): T { return $this->stored; }
}
class Child<T> extends Base<T> {
    public function reveal(): T { return $this->peek(); }
}

$a = new Box::<int>(5);
$b = new Crate::<int>(7);
$c = new Box::<string>('hi');
$d = new Child::<int>(9);

var_dump($a->get());
var_dump($b->fetch());
var_dump($c->get());
var_dump($d->peek());
var_dump($d->reveal());

// Reflection must see each function's own correct signature, not a
// conflated one, despite the shared underlying content.
$rm = new ReflectionMethod($a, 'get');
var_dump($rm->getReturnType()->getName());
$rm = new ReflectionMethod($b, 'fetch');
var_dump($rm->getReturnType()->getName());

// A closure over a monomorph method must keep working after the source
// object is collected (borrowed reference into the shared cache, not an
// owned one -- see the ZEND_ACC2_GENERIC_ARGINFO_SHARED handling in
// Zend/zend_closures.c).
$closureA = $a->get(...);
$closureB = $b->fetch(...);
unset($a, $b);
gc_collect_cycles();
var_dump($closureA());
var_dump($closureB());
?>
--EXPECT--
int(5)
int(7)
string(2) "hi"
int(9)
int(9)
string(3) "int"
string(3) "int"
int(5)
int(7)
