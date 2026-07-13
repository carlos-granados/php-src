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
