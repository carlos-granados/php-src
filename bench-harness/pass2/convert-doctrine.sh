#!/usr/bin/env bash
#
# Convert doctrine/collections' generic surface to native reified generics, in
# place. Like convert-psl.php but for CLASSES + a generic interface hierarchy
# (doctrine expresses generics via @template docblocks over erased signatures).
#
# Parameterises the user-defined generic types with DEFAULTED type params (so
# existing call sites work unchanged) and forwards the args through
# extends/implements. Built-in interfaces (Countable/IteratorAggregate/
# ArrayAccess/Stringable) are left erased — reify accepts a generic class
# implementing an un-parameterised built-in, and native generics can't
# parameterise built-ins. Variance and method-level @template are intentionally
# dropped: the goal is to exercise CLASS monomorphisation, not to reproduce
# doctrine's full type semantics.
#
# Usage: convert-doctrine.sh <collections/src dir>
set -euo pipefail
SRC="${1:?usage: convert-doctrine.sh <collections/src>}"

repl() { # file  old  new
  local f="$1" old="$2" new="$3"
  grep -qF "$old" "$f" || { echo "  ! pattern not found in $(basename "$f"): $old"; exit 1; }
  # Pass strings via env so neither bash nor perl re-interpolates their contents
  # (the method patterns contain a literal `$func`).
  old="$old" new="$new" perl -i -pe 'BEGIN{$o=$ENV{old};$n=$ENV{new}} s/\Q$o\E/$n/' "$f"
  grep -qF "$new" "$f" || { echo "  ! replacement did not apply in $(basename "$f")"; exit 1; }
}

repl "$SRC/Selectable.php" \
  'interface Selectable' \
  'interface Selectable<TKey = mixed, T = mixed>'

repl "$SRC/ReadableCollection.php" \
  'interface ReadableCollection extends Countable, IteratorAggregate, Selectable' \
  'interface ReadableCollection<TKey = mixed, T = mixed> extends Countable, IteratorAggregate, Selectable<TKey, T>'

repl "$SRC/Collection.php" \
  'interface Collection extends ReadableCollection, ArrayAccess' \
  'interface Collection<TKey = mixed, T = mixed> extends ReadableCollection<TKey, T>, ArrayAccess'

repl "$SRC/ArrayCollection.php" \
  'class ArrayCollection implements Collection, Selectable, Stringable' \
  'class ArrayCollection<TKey = mixed, T = mixed> implements Collection<TKey, T>, Selectable<TKey, T>, Stringable'

repl "$SRC/AbstractLazyCollection.php" \
  'abstract class AbstractLazyCollection implements Collection, Selectable' \
  'abstract class AbstractLazyCollection<TKey = mixed, T = mixed> implements Collection<TKey, T>, Selectable<TKey, T>'

# Method-level generics: map() is genuinely generic (@phpstan-template U -> maps
# T to a new element type U, returning ...<TKey, U>). Making it a native generic
# method adds per-method-call type-arg binding on top of the class monomorph,
# which is what "using doctrine's generics" really costs. Declared as `: self` on
# the interfaces and `: Collection` on the classes.
repl "$SRC/ReadableCollection.php" \
  'public function map(Closure $func): self;' \
  'public function map<U = mixed>(Closure $func): self;'
repl "$SRC/Collection.php" \
  'public function map(Closure $func): self;' \
  'public function map<U = mixed>(Closure $func): self;'
repl "$SRC/ArrayCollection.php" \
  'public function map(Closure $func): Collection' \
  'public function map<U = mixed>(Closure $func): Collection'
repl "$SRC/AbstractLazyCollection.php" \
  'public function map(Closure $func): Collection' \
  'public function map<U = mixed>(Closure $func): Collection'

# contains() is generic in its PARAMETER (@template TMaybeContained -> accepts a
# possibly-wider type). The driver calls it every iteration, so convert it too.
# (indexOf/reduce/removeElement are also generic but the driver never calls them,
# so they do not affect this measurement and are left as-is.)
repl "$SRC/ReadableCollection.php" \
  'public function contains(mixed $element): bool;' \
  'public function contains<TMaybeContained = mixed>(mixed $element): bool;'
repl "$SRC/ArrayCollection.php" \
  'public function contains(mixed $element): bool' \
  'public function contains<TMaybeContained = mixed>(mixed $element): bool'
repl "$SRC/AbstractLazyCollection.php" \
  'public function contains(mixed $element): bool' \
  'public function contains<TMaybeContained = mixed>(mixed $element): bool'

echo "converted doctrine/collections in $SRC"
