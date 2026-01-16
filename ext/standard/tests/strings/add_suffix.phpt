--TEST--
Test add_suffix() function
--FILE--
<?php

echo "*** Testing add_suffix() : various strings ***\n";

$testStr = "BeginningMiddleEnd";

// Does not end with suffix - should be added
var_dump(add_suffix($testStr, "ing"));

// Already ends with suffix - should return original
var_dump(add_suffix($testStr, "End"));

// Empty suffix - should return original
var_dump(add_suffix($testStr, ""));

// Empty source - should add suffix
var_dump(add_suffix("", "suffix"));

// Both empty strings
var_dump(add_suffix("", ""));

// Add suffix to empty string with space
var_dump(add_suffix("", " "));

// Source shorter than suffix but doesn't end with it
var_dump(add_suffix("a", "abc"));

// Source equals suffix - should return original
var_dump(add_suffix("test", "test"));

// Null byte handling
var_dump(add_suffix($testStr, "\x00"));
var_dump(add_suffix("\x00", ""));
var_dump(add_suffix("\x00", "\x00"));
var_dump(add_suffix("a\x00", "\x00"));
var_dump(add_suffix("a", "\x00"));
var_dump(add_suffix("ab\x00c", "\x00c"));
var_dump(add_suffix("ab\x00", "\x00c"));
var_dump(add_suffix("b\x00a", "d\x00a"));
var_dump(add_suffix("b\x00a", "z\x00a"));

?>
--EXPECTF--
*** Testing add_suffix() : various strings ***
string(21) "BeginningMiddleEnding"
string(18) "BeginningMiddleEnd"
string(18) "BeginningMiddleEnd"
string(6) "suffix"
string(0) ""
string(1) " "
string(4) "aabc"
string(4) "test"
string(19) "BeginningMiddleEnd%0"
string(1) "%0"
string(1) "%0"
string(2) "a%0"
string(2) "a%0"
string(4) "ab%0c"
string(5) "ab%0%0c"
string(6) "b%0ad%0a"
string(6) "b%0az%0a"
