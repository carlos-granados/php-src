--TEST--
Opcache: compact_literals doesn't misread a T-ref catch's packed deferred-fetch descriptor as a literal index
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
--EXTENSIONS--
opcache
--FILE--
<?php
// catch (Box<T> $e) compiles op1 as IS_UNUSED with op1.num holding a packed
// type-param/deferred-fetch descriptor, not a literal-table index. The
// literal-marking pass of zend_optimizer_compact_literals (Zend/Optimizer/
// compact_literals.c) used to write LITERAL_INFO(opline->op1.constant, 2)
// unconditionally for ZEND_CATCH, treating that packed descriptor as an
// index into the (much smaller) per-op_array literal-info array -- an
// out-of-bounds write only visible under a memory checker (valgrind:
// "Invalid write of size 1" at compact_literals.c, landing in memory freed
// by an unrelated, already-finished optimizer pass). This just needs to run
// under opcache so the persist-time optimizer pipeline (including
// compact_literals) actually executes.
class BoxedError<T> extends Exception {}

function tryCatch<T>(Exception $e): string {
    try {
        throw $e;
    } catch (BoxedError<T> $caught) {
        return "matched BoxedError<T>";
    } catch (Exception $other) {
        return "fell through";
    }
}

echo tryCatch::<int>(new BoxedError::<int>('payload')), "\n";
echo tryCatch::<int>(new BoxedError::<string>('payload')), "\n";
echo tryCatch::<int>(new Exception('plain')), "\n";
echo tryCatch::<string>(new BoxedError::<string>('s')), "\n";
?>
--EXPECT--
matched BoxedError<T>
fell through
fell through
matched BoxedError<T>
