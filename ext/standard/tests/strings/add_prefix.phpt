--TEST--
Test add_prefix() function
--FILE--
<?php

echo "*** Testing add_prefix() : various strings ***\n";

$testStr = "BeginningMiddleEnd";

// Does not start with prefix - should be added
var_dump(add_prefix($testStr, "The"));

// Already starts with prefix - should return original
var_dump(add_prefix($testStr, "Beginning"));

// Empty prefix - should return original
var_dump(add_prefix($testStr, ""));

// Empty source - should add prefix
var_dump(add_prefix("", "prefix"));

// Both empty strings
var_dump(add_prefix("", ""));

// Add prefix to empty string with space
var_dump(add_prefix("", " "));

// Source shorter than prefix but doesn't start with it
var_dump(add_prefix("a", "abc"));

// Source equals prefix - should return original
var_dump(add_prefix("test", "test"));

// Null byte handling
var_dump(add_prefix($testStr, "\x00"));
var_dump(add_prefix("\x00", ""));
var_dump(add_prefix("\x00", "\x00"));
var_dump(add_prefix("\x00a", "\x00"));
var_dump(add_prefix("a", "\x00"));
var_dump(add_prefix("c\x00ab", "\x00ab"));
var_dump(add_prefix("\x00ab", "c\x00"));
var_dump(add_prefix("a\x00b", "a\x00d"));
var_dump(add_prefix("a\x00b", "a\x00z"));

?>
--EXPECTF--
*** Testing add_prefix() : various strings ***
string(21) "TheBeginningMiddleEnd"
string(18) "BeginningMiddleEnd"
string(18) "BeginningMiddleEnd"
string(6) "prefix"
string(0) ""
string(1) " "
string(4) "abca"
string(4) "test"
string(19) "%0BeginningMiddleEnd"
string(1) "%0"
string(1) "%0"
string(2) "%0a"
string(2) "%0a"
string(7) "%0abc%0ab"
string(5) "c%0%0ab"
string(6) "a%0da%0b"
string(6) "a%0za%0b"
