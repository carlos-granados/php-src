--TEST--
Opcache preload: runtime monomorph synthesis works against a preloaded (immutable SHM) generic template
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/preload_generic.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
// Box is preloaded and lives in read-only SHM. Synthesizing Box<int> and
// Box<string> at runtime must not mutate the immutable template.
$i = new Box::<int>(42);
$s = new Box::<string>("hi");

var_dump($i::class);
var_dump($s::class);
var_dump($i->get());        // value round-trips (scalar binding)
var_dump($s->get());
var_dump($i instanceof Box);
var_dump($i instanceof Box::<int>);
var_dump($i instanceof Box::<string>);

// Class-level T::class in a method body of a preloaded template resolves the
// runtime monomorph's binding (only meaningful for a class binding).
$d = new Box::<DateTime>(new DateTime());
var_dump($d->kind());

// A preloaded generic function still binds per call.
var_dump(identity::<int>(7));

// A preloaded bounded/interface template monomorphizes too.
$h = new Holder::<DateTime>(new DateTime());
var_dump($h::class);
?>
--EXPECT--
string(8) "Box<int>"
string(11) "Box<string>"
int(42)
string(2) "hi"
bool(true)
bool(true)
bool(false)
string(8) "DateTime"
int(7)
string(16) "Holder<DateTime>"
