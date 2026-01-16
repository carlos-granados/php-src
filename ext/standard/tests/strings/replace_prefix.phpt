--TEST--
Test replace_prefix() function
--FILE--
<?php

echo "*** Testing replace_prefix() : various strings ***\n";

$testStr = "BeginningMiddleEnd";

// Starts with prefix - should be replaced
var_dump(replace_prefix($testStr, "Beginning", "Start"));

// Does not start with prefix - should return original
var_dump(replace_prefix($testStr, "Middle", "Center"));

// Replace with empty string (essentially remove)
var_dump(replace_prefix($testStr, "Beginning", ""));

// Replace empty prefix - should add prefix
var_dump(replace_prefix($testStr, "", "New"));

// Replace with longer string
var_dump(replace_prefix("http://example.com", "http://", "https://"));

// Replace with shorter string
var_dump(replace_prefix("/usr/local/bin", "/usr/local/", "/opt/"));

// Prefix longer than source - should return original
var_dump(replace_prefix("ab", "abcd", "xyz"));

// Source equals prefix - replace entire string
var_dump(replace_prefix("test", "test", "replaced"));

// Empty source
var_dump(replace_prefix("", "prefix", "new"));

// Both prefix and replace empty
var_dump(replace_prefix($testStr, "", ""));

// Null byte handling
var_dump(replace_prefix("\x00a", "\x00", "b"));
var_dump(replace_prefix("\x00a", "\x00", "\x00"));
var_dump(replace_prefix("\x00cab", "\x00c", "d"));
var_dump(replace_prefix("\x00cab", "\x00c", "\x00d"));
var_dump(replace_prefix("b\x00a", "c\x00a", "d"));
var_dump(replace_prefix("\x00test", "\x00te", "new"));

?>
--EXPECTF--
*** Testing replace_prefix() : various strings ***
string(14) "StartMiddleEnd"
string(18) "BeginningMiddleEnd"
string(9) "MiddleEnd"
string(21) "NewBeginningMiddleEnd"
string(19) "https://example.com"
string(8) "/opt/bin"
string(2) "ab"
string(8) "replaced"
string(0) ""
string(18) "BeginningMiddleEnd"
string(2) "ba"
string(2) "%0a"
string(3) "dab"
string(4) "%0dab"
string(3) "b%0a"
string(5) "newst"
