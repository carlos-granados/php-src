--TEST--
Test remove_prefix() function
--FILE--
<?php

echo "*** Testing remove_prefix() : various strings ***\n";

$testStr = "BeginningMiddleEnd";

// Matching prefix - should be removed
var_dump(remove_prefix($testStr, "Beginning"));

// Non-matching prefix (case sensitive) - should return original
var_dump(remove_prefix($testStr, "beginning"));

// Partial match but not at start - should return original
var_dump(remove_prefix($testStr, "Middle"));

// Remove entire string
var_dump(remove_prefix($testStr, $testStr));

// Prefix longer than source - should return original
var_dump(remove_prefix($testStr, $testStr.$testStr));

// Empty prefix - should return original
var_dump(remove_prefix($testStr, ""));

// Both empty strings
var_dump(remove_prefix("", ""));

// Empty source, non-empty prefix
var_dump(remove_prefix("", " "));

// Null byte handling
var_dump(remove_prefix($testStr, "\x00"));
var_dump(remove_prefix("\x00", ""));
var_dump(remove_prefix("\x00", "\x00"));
var_dump(remove_prefix("\x00a", "\x00"));
var_dump(remove_prefix("\x00bca", "\x00bc"));
var_dump(remove_prefix("b\x00a", "d\x00a"));
var_dump(remove_prefix("b\x00a", "z\x00a"));
var_dump(remove_prefix("a", "a\x00"));
var_dump(remove_prefix("a", "\x00a"));

?>
--EXPECTF--
*** Testing remove_prefix() : various strings ***
string(9) "MiddleEnd"
string(18) "BeginningMiddleEnd"
string(18) "BeginningMiddleEnd"
string(0) ""
string(18) "BeginningMiddleEnd"
string(18) "BeginningMiddleEnd"
string(0) ""
string(0) ""
string(18) "BeginningMiddleEnd"
string(1) "%0"
string(0) ""
string(1) "a"
string(1) "a"
string(3) "b%0a"
string(3) "b%0a"
string(1) "a"
string(1) "a"
