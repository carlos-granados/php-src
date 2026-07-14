<?php
/* Generic version of plain_request.php (reify only — master cannot parse this).
 * Under a warm opcache the class/function *declarations* are served from SHM,
 * but runtime monomorph synthesis (Box<int>, Pair<int,string>) re-runs each
 * request against the per-request class table — so the warm number captures the
 * real per-request generic cost that opcache does NOT amortize. Compare against
 * plain_request.php on the same build (cost of using generics vs hand-writing)
 * and on master (cost vs today's PHP). */

class Box<T> {
    public function __construct(public T $v) {}
    public function get(): T { return $this->v; }
}
class Pair<A, B> {
    public function __construct(public A $a, public B $b) {}
}
function id<T>(T $x): T { return $x; }

$acc = 0;
for ($i = 0; $i < 5000; $i++) {
    $b = new Box::<int>($i);
    $acc += $b->get();
    $p = new Pair::<int, string>($i, "x");
    $acc += id::<int>($p->a);
}
