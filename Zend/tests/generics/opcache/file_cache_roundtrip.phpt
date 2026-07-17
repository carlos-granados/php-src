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
    // file_update_protection=0: the freshly written script must actually be
    // CACHED — with the default 2s window the first run would silently skip
    // the file-cache write and the second run would recompile, testing
    // nothing.
    $cmd = sprintf(
        '%s -n -d opcache.enable_cli=1 -d opcache.file_cache=%s -d opcache.file_cache_only=1 -d opcache.file_update_protection=0 %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($dir),
        escapeshellarg($file)
    );
    return shell_exec($cmd);
};

// First run compiles and writes the opcode+side tables to the file cache.
echo "run1:\n", $run();
// Guard against the silent-no-op failure mode: the cache must exist now.
$cached = false;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (str_ends_with($f->getFilename(), '.bin')) { $cached = true; break; }
}
echo "cached: ", $cached ? "y" : "n", "\n";
// Second run loads everything (incl. turbofish_args / generic_types) from the cache.
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
Box<DateTime>|DateTime
y
n
cached: y
run2:
Box<DateTime>|DateTime
y
n
