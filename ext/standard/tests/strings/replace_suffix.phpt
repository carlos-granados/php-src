--TEST--
Test replace_suffix() function
--FILE--
<?php

echo "*** Testing replace_suffix() : various strings ***\n";

$testStr = "BeginningMiddleEnd";

// Ends with suffix - should be replaced
var_dump(replace_suffix($testStr, "End", "Finish"));

// Does not end with suffix - should return original
var_dump(replace_suffix($testStr, "Middle", "Center"));

// Replace with empty string (essentially remove)
var_dump(replace_suffix($testStr, "End", ""));

// Replace empty suffix - should add suffix
var_dump(replace_suffix($testStr, "", "New"));

// Replace with longer string
var_dump(replace_suffix("file.txt", ".txt", ".backup.txt"));

// Replace with shorter string
var_dump(replace_suffix("document.html", ".html", ".md"));

// Suffix longer than source - should return original
var_dump(replace_suffix("ab", "abcd", "xyz"));

// Source equals suffix - replace entire string
var_dump(replace_suffix("test", "test", "replaced"));

// Empty source
var_dump(replace_suffix("", "suffix", "new"));

// Both suffix and replace empty
var_dump(replace_suffix($testStr, "", ""));

// Null byte handling
var_dump(replace_suffix("a\x00", "\x00", "b"));
var_dump(replace_suffix("a\x00", "\x00", "\x00"));
var_dump(replace_suffix("ab\x00c", "\x00c", "d"));
var_dump(replace_suffix("ab\x00c", "\x00c", "\x00d"));
var_dump(replace_suffix("a\x00b", "c\x00b", "d"));
var_dump(replace_suffix("test\x00", "st\x00", "new"));

?>
--EXPECTF--
*** Testing replace_suffix() : various strings ***
string(21) "BeginningMiddleFinish"
string(18) "BeginningMiddleEnd"
string(15) "BeginningMiddle"
string(21) "BeginningMiddleEndNew"
string(15) "file.backup.txt"
string(11) "document.md"
string(2) "ab"
string(8) "replaced"
string(0) ""
string(18) "BeginningMiddleEnd"
string(2) "ab"
string(2) "a%0"
string(3) "abd"
string(4) "ab%0d"
string(3) "a%0b"
string(5) "tenew"
