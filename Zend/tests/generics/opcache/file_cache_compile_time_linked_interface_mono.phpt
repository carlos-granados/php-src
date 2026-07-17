--TEST--
Opcache file cache: a compile-time-synthesized, already-linked interface monomorph serializes and round-trips correctly
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip file_cache path handling differs on Windows');
?>
--FILE--
<?php
$dir = sys_get_temp_dir() . '/gen_fclm_' . getmypid();
@mkdir($dir);

$script = <<<'PHP'
<?php
interface Producer<out T> { public function make(): T; }
class Base<T> implements Producer<T> { public function make(): T {} }
class Derived<T> extends Base<T> {}

$o = new Derived::<int>();

// Compiling this instanceof against a concrete turbofish argument
// synthesizes and fully LINKS the "Producer<int>"/"Producer<string>"
// monomorphs at COMPILE TIME, before the file cache serializes the
// script -- unlike every other class in this file, whose linking is
// deferred past that point.
var_dump($o instanceof Producer::<int>);
var_dump($o instanceof Producer::<string>);
var_dump($o instanceof Base::<int>);
var_dump($o instanceof Base::<string>);
var_dump($o instanceof Base);
var_dump($o instanceof Producer);
PHP;

$file = $dir . '/prog.php';
file_put_contents($file, $script);

$run = function () use ($dir, $file) {
    // file_update_protection=0: the freshly written script must actually be
    // CACHED for this to exercise both the serialize (run1) and unserialize
    // (run2) paths.
    $cmd = sprintf(
        '%s -n -d opcache.enable_cli=1 -d opcache.file_cache=%s -d opcache.file_cache_only=1 -d opcache.file_update_protection=0 %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($dir),
        escapeshellarg($file)
    );
    return shell_exec($cmd);
};

echo "run1:\n", $run();
echo "run2:\n", $run();

$rm = function (string $p) use (&$rm): void {
    foreach (glob($p . '/*') ?: [] as $e) {
        is_dir($e) ? $rm($e) : unlink($e);
    }
    @rmdir($p);
};
$rm($dir);
?>
--EXPECTF--
run1:
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
run2:
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
