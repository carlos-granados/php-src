<?php

// Generic templates preloaded into opcache SHM (immutable). Runtime
// monomorph synthesis must work against these read-only templates.

class Box<T> {
    public function __construct(public T $value) {}
    public function get(): T { return $this->value; }
    public function kind(): string { return T::class; }
}

interface Producer<out T> {
    public function make(): T;
}

class Holder<T : object> {
    public T $thing;
    public function __construct(T $thing) { $this->thing = $thing; }
}

function identity<T>(T $v): T { return $v; }

// A generic PARENT and generic CHILD where the child inherits a T-free
// method unchanged (count/isEmpty never reference T, so
// zend_maybe_substitute_inherited_method shares the parent's op_array
// rather than cloning it -- see the "identity-substitution sharing"
// verification). Preloading both ends of such a hierarchy together
// (mirroring a real composer-autoloaded class like
// Doctrine\Common\Collections\ArrayCollection extending
// AbstractLazyCollection) used to abort opcache preload outright:
// preload_register_trait_methods iterated both classes' function_tables
// and tried to register the SAME shared (inherited, un-cloned) method's
// refcount pointer as an opcache xlat entry twice.
class GenericBase<T = mixed> {
    protected array $items = [];
    public function add(T $x): void { $this->items[] = $x; }
    public function count(): int { return count($this->items); }
}

class GenericChild<T = mixed> extends GenericBase<T> {
    public function isEmpty(): bool { return $this->count() === 0; }
}
