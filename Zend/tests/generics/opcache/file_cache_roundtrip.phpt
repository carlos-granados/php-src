--TEST--
Opcache file cache: a script using turbofish/monomorphs round-trips through the file cache (serialize + deserialize of the generic side tables)
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip file_cache path handling differs on Windows');
?>
--FILE--
<?php
$dir = sys_get_temp_dir() . '/gen_fc_' . getmypid();
@mkdir($dir);

$script = <<<'PHP'
<?php
class Box<T : object> {
    public function __construct(public T $value) {}
    public function kind(): string { return T::class; }
}

$b = new Box::<DateTime>(new DateTime());
echo $b::class, "|", $b->kind(), "\n";
echo ($b instanceof Box::<DateTime>) ? "y" : "n", "\n";
echo ($b instanceof Box::<Exception>) ? "y" : "n", "\n";
PHP;

$file = $dir . '/prog.php';
file_put_contents($file, $script);

$run = function () use ($dir, $file) {
    $cmd = sprintf(
        '%s -n -d opcache.enable_cli=1 -d opcache.file_cache=%s -d opcache.file_cache_only=1 %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($dir),
        escapeshellarg($file)
    );
    return shell_exec($cmd);
};

// First run compiles and writes the opcode+side tables to the file cache.
echo "run1:\n", $run();
// Second run loads everything (incl. turbofish_args / generic_types) from the cache.
echo "run2:\n", $run();

array_map('unlink', glob($dir . '/*') ?: []);
@array_map('unlink', glob($dir . '/**/*') ?: []);
?>
--EXPECTF--
run1:
Box<DateTime>|DateTime
y
n
run2:
Box<DateTime>|DateTime
y
n
