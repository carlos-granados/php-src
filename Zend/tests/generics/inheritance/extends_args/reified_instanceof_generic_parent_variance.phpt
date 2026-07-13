--TEST--
Extends-with-args: reified instanceof through a forwarding generic parent respects the parent's variance markers
--FILE--
<?php
class Animal {}
class Dog extends Animal {}

// Covariant parent: Container<Dog> is a Container<Animal>.
class Container<out T> {}
class Repo<T> extends Container<T> {}

$r = new Repo::<Dog>();
var_dump($r instanceof Container::<Dog>);      // exact
var_dump($r instanceof Container::<Animal>);   // covariant widening
var_dump($r instanceof Container::<Cat>);      // unrelated

// Invariant parent: no widening.
class Cell<T> {}
class Holder<T> extends Cell<T> {}
$h = new Holder::<Dog>();
var_dump($h instanceof Cell::<Dog>);           // exact
var_dump($h instanceof Cell::<Animal>);        // invariant -> false

// Contravariant parent: Sink<Animal> is a Sink<Dog>.
class Sink<in T> {}
class Drain<T> extends Sink<T> {}
$d = new Drain::<Animal>();
var_dump($d instanceof Sink::<Animal>);        // exact
var_dump($d instanceof Sink::<Dog>);           // contravariant narrowing
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
