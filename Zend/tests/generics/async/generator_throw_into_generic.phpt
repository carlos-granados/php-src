--TEST--
Async: throwing into a suspended generic generator unwinds correctly and T stays resolved afterwards
--FILE--
<?php
function gen<T : object>(): Generator {
    try {
        yield T::class;
        yield "unreachable";
    } catch (RuntimeException $e) {
        echo "caught in ", T::class, ": ", $e->getMessage(), "\n";
        yield T::class . " after catch";
    }
}

$g = gen::<DateTime>();
echo $g->current(), "\n";                 // DateTime
echo $g->throw(new RuntimeException("boom")), "\n"; // resumes into catch, yields again
?>
--EXPECT--
DateTime
caught in DateTime: boom
DateTime after catch
