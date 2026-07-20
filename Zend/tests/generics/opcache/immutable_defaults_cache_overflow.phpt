--TEST--
Opcache preload: naked calls to more than ZEND_IMMUTABLE_DEFAULTS_CACHE_MAX distinct immutable generic functions stay correct (and don't leak a table nothing ever releases)
--DESCRIPTION--
zend_verify_speculative_generic_call() memoizes a naked (non-turbofish)
generic call's defaults-built type-arg table into the callee's own
run_time_cache, and additionally tracks it in EG(immutable_defaults_cache_
tables) when the callee is opcache-immutable, since shutdown_executor() (not
the normal per-op_array teardown, which immutable op_arrays skip) is what
releases those. Past the tracker's fixed 64-entry capacity, a table would be
persisted into the cache slot with nothing ever able to release it -- a
real per-request leak for any sufficiently generics-heavy preloaded app.
This preloads 70 distinct such functions (over the cap) and calls all of
them; correctness here plus a clean full-suite valgrind run is the
regression coverage (leak-freedom itself isn't something a phpt EXPECT
block can assert).
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.optimization_level=-1
opcache.preload={PWD}/immutable_defaults_cache_overflow.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
$ok = true;
for ($i = 0; $i < 70; $i++) {
    $fn = "f$i";
    if ($fn() !== $i) {
        $ok = false;
        echo "mismatch at $i\n";
    }
}
echo $ok ? "OK\n" : "FAIL\n";
?>
--EXPECT--
OK
