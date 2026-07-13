--TEST--
Reflection: a monomorph's class hierarchy — parent is the template, interface/subclass relations resolve
--FILE--
<?php
interface Shape {}
class Base<T> implements Shape {}
class Derived<T> extends Base<T> {}

$rc = new ReflectionClass("Derived<int>");

// A monomorph synthesized from a generic template extends the *template*, not
// the substituted parent monomorph: the parent chain is Derived -> Base
// (templates). The substituted parent binding is exposed through the dedicated
// getGenericArgumentsForParentClass() API, not getParentClass().
var_dump($rc->getName());
var_dump($rc->getParentClass()->getName());
var_dump($rc->getParentClass()->getParentClass()->getName());

// Plain (erased) subclass/interface relations resolve through the chain.
var_dump($rc->implementsInterface('Shape'));
var_dump($rc->isSubclassOf('Base'));
var_dump(in_array('Shape', $rc->getInterfaceNames(), true));

// ReflectionObject on an instance reports the monomorph name.
$obj = new Derived::<int>();
$ro = new ReflectionObject($obj);
var_dump($ro->getName());
var_dump($ro->isInstance($obj));

// Cross-monomorph: an int instance is not an instance of the string monomorph.
$rcStr = new ReflectionClass("Derived<string>");
var_dump($rcStr->isInstance($obj));
?>
--EXPECT--
string(12) "Derived<int>"
string(7) "Derived"
string(4) "Base"
bool(true)
bool(true)
bool(true)
string(12) "Derived<int>"
bool(true)
bool(false)
