--TEST--
Opcache preload: preloaded templates whose methods share a dedup'd substituted arg_info block persist to SHM without crashing
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.optimization_level=-1
opcache.preload={PWD}/preload_subst_arg_info_dedup.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
$m = new MultiAccessor::<int>(5);
var_dump($m->first());
var_dump($m->last());
var_dump($m->current());

$a = new Box::<int>(5);
$b = new Crate::<int>(7);
var_dump($a->get());
var_dump($b->fetch());
?>
--EXPECT--
int(5)
int(5)
int(5)
int(5)
int(7)
