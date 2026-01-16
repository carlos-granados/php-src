--TEST--
Test remove_suffix() function
--FILE--
<?php

echo "*** Testing remove_suffix() : various strings ***\n";

$testStr = "beginningMiddleEnd";

// Matching suffix - should be removed
var_dump(remove_suffix($testStr, "End"));

// Non-matching suffix (case sensitive) - should return original
var_dump(remove_suffix($testStr, "end"));

// Partial match but not at end - should return original
var_dump(remove_suffix($testStr, "en"));

// Remove entire string
var_dump(remove_suffix($testStr, $testStr));

// Suffix longer than source - should return original
var_dump(remove_suffix($testStr, $testStr.$testStr));

// Empty suffix - should return original
var_dump(remove_suffix($testStr, ""));

// Both empty strings
var_dump(remove_suffix("", ""));

// Empty source, non-empty suffix
var_dump(remove_suffix("", " "));

// Null byte handling
var_dump(remove_suffix($testStr, "\x00"));
var_dump(remove_suffix("\x00", ""));
var_dump(remove_suffix("\x00", "\x00"));
var_dump(remove_suffix("a\x00", "\x00"));
var_dump(remove_suffix("ab\x00c", "b\x00c"));
var_dump(remove_suffix("a\x00b", "d\x00b"));
var_dump(remove_suffix("a\x00b", "a\x00z"));
var_dump(remove_suffix("a", "\x00a"));
var_dump(remove_suffix("a", "a\x00"));

?>
--EXPECTF--
*** Testing remove_suffix() : various strings ***
string(15) "beginningMiddle"
string(18) "beginningMiddleEnd"
string(18) "beginningMiddleEnd"
string(0) ""
string(18) "beginningMiddleEnd"
string(18) "beginningMiddleEnd"
string(0) ""
string(0) ""
string(18) "beginningMiddleEnd"
string(1) "%0"
string(0) ""
string(1) "a"
string(1) "a"
string(3) "a%0b"
string(3) "a%0b"
string(1) "a"
string(1) "a"
