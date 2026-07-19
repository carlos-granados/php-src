<?php

// A single generic class with several methods whose substituted arg_info
// all reduce to the same shape (one scalar T-return slot) once monomorphized
// -- the EG(subst_arg_info_cache) dedup case (see Zend/zend_globals.h): all
// three methods below end up sharing ONE substituted arg_info block after
// dedup, rather than each cloning its own.
//
// Preloaded so the template is ZEND_ACC_IMMUTABLE by the time the main
// script runs, making its runtime monomorph eligible for opcache SHM
// persistence (zend_monomorph_cache_add). Persisting a class whose methods
// share ONE dedup'd arg_info block used to crash outright: each method's
// persist call independently tries to zend_shared_memdup_put the SAME
// source pointer, and that function's internal xlat registration is
// add-new (asserts no prior entry for that pointer) -- the second (and
// third) method's persist call hit that assertion.
class MultiAccessor<T> {
    public function __construct(public T $value) {}
    public function first(): T { return $this->value; }
    public function last(): T { return $this->value; }
    public function current(): T { return $this->value; }
}

// Two unrelated templates sharing the same dedup'd shape too (the more
// typical cross-template case this feature targets).
class Box<T> {
    public function __construct(public T $value) {}
    public function get(): T { return $this->value; }
}
class Crate<T> {
    public function __construct(public T $item) {}
    public function fetch(): T { return $this->item; }
}

class_exists(MultiAccessor::class);
class_exists(Box::class);
class_exists(Crate::class);
