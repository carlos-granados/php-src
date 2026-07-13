--TEST--
Errors: an enum cannot declare type parameters
--FILE--
<?php
enum E<T> {
    case A;
}
?>
--EXPECTF--
Parse error: syntax error, unexpected token "<"%ain %s on line %d
