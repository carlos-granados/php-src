--TEST--
Opcache file_cache_only: implementing/using a generic interface/trait does not leak the rewritten monomorph names
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip file_cache path handling differs on Windows');
?>
--FILE--
<?php
$dir = sys_get_temp_dir() . '/gen_fco_' . getmypid();
@mkdir($dir);

$script = <<<'PHP'
<?php
interface Producer<out T> { public function produce(): T; }
trait Holder<T : object> {
    public T $value;
    public function get(): T { return $this->value; }
}
class AnimalProducer implements Producer<object> {
    use Holder<object>;
    public function produce(): object { return new stdClass(); }
}
$p = new AnimalProducer();
$p->value = new stdClass();
var_dump($p->produce());
var_dump($p->get());
var_dump($p instanceof Producer);
PHP;

$file = $dir . '/prog.php';
file_put_contents($file, $script);

$run = function () use ($dir, $file) {
    // file_update_protection=0: the freshly written script must actually be
    // CACHED, matching the codepath that exercises the deferred/runtime
    // class-linking monomorph rewrite (opcache.file_cache implies
    // ZEND_COMPILE_DELAYED_BINDING, so this class links via a runtime
    // ZEND_DECLARE_CLASS opcode, not at compile time).
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
object(stdClass)#%d (0) {
}
object(stdClass)#%d (0) {
}
bool(true)
run2:
object(stdClass)#%d (0) {
}
object(stdClass)#%d (0) {
}
bool(true)
