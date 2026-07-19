###############
 Generic types
###############

PHP supports generic type parameters on classes, interfaces, traits, functions, methods, closures,
and arrow functions. They are *reified*, but reified along two different axes depending on the
declaring entity:

-  **Class-like generics are monomorphised.** Each distinct argument tuple at a generic class,
   interface, or trait synthesises a separate ``zend_class_entry`` — ``Box<int>`` and
   ``Box<string>`` are distinct classes whose canonical names are spelled out
   (``"Box<int>"``, ``"Box<string>"``) and registered in ``EG(class_table)``. The monomorph extends
   (or implements, or uses) the base class with the substitution baked in. ``get_class()``,
   ``var_dump``, and ``$x instanceof Box<int>`` all see the canonical mono.

-  **Function- and method-level generics are reified per call frame.** Each call gets a fresh
   ``zend_type_arg_table`` on ``EX(type_args)`` mapping parameter index to bound class name. There
   is no persistent ``id<int>`` function entry — function-level monomorphs would grow without
   bound and require a lot of bookkeeping, so the binding lives on the frame and is consulted on
   demand. Closures and generators preserve the table so it survives suspension.

A parameter's **bound** is the type-check the engine uses on ordinary parameter / return /
property slots: ``arg_info::type``, ``zend_property_info::type``, and ``zend_class_constant::type``
carry the bound, and the existing runtime handlers (parameter coercion, return verification,
property writes) walk them as if no generics were involved. The bound is also the answer to "what
can a caller actually pass at this slot", which is why Reflection's ``ReflectionType::getName()``
and ``__toString()`` continue to return the bound's text. Reflection exposes the reified shape
separately through ``ReflectionNamedType::getGenericArguments()`` and
``ReflectionTypeParameterReference``.

The reified shape is held on a side table keyed by entity slot. The runtime reads it at a small
number of well-defined points:

-  monomorph synthesis (``zend_synthesize_monomorph``),
-  turbofish argument validation (``ZEND_VERIFY_GENERIC_ARGUMENTS``),
-  deferred class lookups for ``instanceof Box<T>`` / ``catch (Box<T> $e)`` (the new
   ``ZEND_FETCH_CLASS_GENERIC_DEFERRED`` sub-type),
-  bare ``T``-ref resolution for ``instanceof T`` / ``catch (T $e)`` / ``new T()`` /
   ``T::method()``,
-  inheritance linking, where a parent's reified type is substituted with the child's bindings,
-  Reflection.

***********************
 Reified type carriers
***********************

``zend_type`` carries the reified shapes generics need on side-table slots. Two bits in
``type_mask`` distinguish them:

.. code:: c

   #define _ZEND_TYPE_TYPE_PARAMETER_BIT  (1u << 25)  /* t.ptr is a zend_type_parameter_ref * */
   #define _ZEND_TYPE_NAMED_WITH_ARGS_BIT (1u << 31)  /* t.ptr is a zend_type_named_with_args * */

When ``_ZEND_TYPE_TYPE_PARAMETER_BIT`` is set, ``t.ptr`` is a reference to a declared parameter;
when ``_ZEND_TYPE_NAMED_WITH_ARGS_BIT`` is set, ``t.ptr`` is a generic application like
``Foo<int, T>``:

.. code:: c

   typedef struct _zend_type_parameter_ref {
       zend_string *name;     /* "T" */
       uint32_t     index;    /* position in the parameter list */
       uint8_t      origin;   /* CLASS_LIKE or FUNCTION_LIKE */
   } zend_type_parameter_ref;

   typedef struct _zend_type_named_with_args {
       zend_string *name;     /* "Foo", or NULL for synthetic turbofish carriers */
       uint32_t     name_attr;
       uint32_t     count;
       zend_type    args[1];  /* flexible array */
   } zend_type_named_with_args;

The ``origin`` field on a ``T``-ref tells you which scope to resolve ``index`` in. NAMED_WITH_ARGS
values with ``name == NULL`` are synthetic carriers that hold the args at a turbofish site —
they're not type expressions, just a payload. Both payloads only appear in side-table slots, are
heap-allocated, and are released through ``zend_type_release``.

**********************
 Per-entity metadata
**********************

Both ``zend_class_entry`` and ``zend_op_array`` carry two pointer fields for generic information,
both ``NULL`` on entities that don't use generics:

.. code:: c

   zend_generic_parameter_list *generic_parameters;  /* the entity's own <T, U> declaration */
   zend_generic_type_table     *generic_types;       /* reified shapes for every other slot */

``generic_parameters`` is the entity's own declaration list (a flexible-array struct allocated
through ``zend_generic_parameter_list_alloc``). Each parameter carries two views of its bound and
default: the ``bound`` / ``default_type`` slots hold the form the ordinary type-check machinery
sees (a class name or scalar mask, with ``T``-refs resolved to their bounds), and the
``..._pre_erasure`` slots (the C field names are kept from earlier scaffolding) hold the reified
shape when it differs from the bound view:

.. code:: c

   typedef struct _zend_generic_parameter {
       zend_string         *name;
       zend_generic_variance variance;          /* INVARIANT, COVARIANT, CONTRAVARIANT */
       zend_type            bound;              /* type-check view; NONE if unbounded */
       zend_type            bound_pre_erasure;  /* NONE if same as bound */
       zend_type            default_type;
       zend_type            default_pre_erasure;
   } zend_generic_parameter;

``generic_types`` collects every other slot where a reified type might need to be looked up:

.. code:: c

   typedef struct _zend_generic_type_table {
       zend_type   *return_type;
       zend_type   *extends;
       HashTable   *parameters;        /* parameter index -> zend_type * */
       HashTable   *properties;        /* zend_string *  -> zend_type * */
       HashTable   *class_constants;
       HashTable   *implements;        /* implements index -> zend_type * */
       HashTable   *trait_uses;        /* trait-use index -> zend_type * */
       HashTable   *turbofish_args;    /* args_id        -> zend_type * */
   } zend_generic_type_table;

Every slot is independently NULLable. The table is allocated lazily by
``zend_generic_get_or_create_class_table`` / ``..._op_array_table`` on first use, and individual
slots stay ``NULL`` whenever the reified form would be byte-equal to the bound view. You populate
slots through the ``zend_generic_type_table_set_*`` family; each setter takes ownership of the
``zend_type`` you hand it.

The ``turbofish_args`` table is keyed by an ``args_id`` rather than the opline's position in the
bytecode array. Optimizer passes reorder, insert, and delete opcodes; the id is stamped into the
opline itself (either ``extended_value`` for ``ZEND_VERIFY_GENERIC_ARGUMENTS`` or the packed
``op.num`` for ``ZEND_FETCH_CLASS_GENERIC_DEFERRED``), so the id survives reordering.

Attributes carry their turbofish on two fields on ``zend_attribute``:

.. code:: c

   uint8_t      generic_arity;
   zend_type   *generic_args;       /* NAMED_WITH_ARGS holding turbofish args */

******************
 Compile-time flow
******************

When the compiler reaches a generic declaration, ``zend_compile_generic_type_parameter_list``
builds the parameter list and pushes a linked-list scope entry. ``T``-ref resolution walks the
chain through ``zend_generic_lookup``. The scope entry's ``self_compiling`` field points at the
parameter whose bound or default is currently being compiled, which is what lets the compiler
reject ``class A<T : T>`` (top-level self-reference) while accepting
``class A<T : Comparable<T>>`` (the inner ``T`` is nested under another generic).

Every type expression carrying a ``T`` is compiled twice: once as the bound-view ``zend_type`` on
the entity's ordinary slot (``arg_info::type``, ``zend_property_info::type``), and once in
reified form on the matching ``generic_types`` slot. When the bound is a list type (union,
intersection, or DNF), the bound copy is built through ``zend_arena_deep_copy_type_list`` so
nested type lists are not aliased between ``param->bound`` and the consuming ``arg_info``.

For a turbofish at a call or ``new`` site, ``zend_emit_verify_generic_arguments`` compiles the
args into a NAMED_WITH_ARGS payload, stores it in ``op_array->generic_types->turbofish_args``
under a fresh ``args_id``, and emits a ``ZEND_VERIFY_GENERIC_ARGUMENTS`` opcode with
``op2.num = arity`` and ``extended_value = args_id``. The opcode sits between the call's
``INIT_*`` (or ``ZEND_NEW``) and its ``DO_*``, so the call frame is set up when the handler runs.

For a generic named type in expression position — ``instanceof Box<int>``, ``catch (Box<T> $e)``,
``new Box::<int>()`` — ``zend_compile_class_ref`` does compile-time canonicalisation when the
args are fully concrete (no ``T``-refs): it produces the canonical name string and emits an
``IS_CONST`` operand, so the lookup is the same constant-cache hit any other class reference
gets. When the args contain ``T``-refs the resolution has to wait for the call frame's bindings;
see *Deferred generic class resolution* below.

******************************
 Variance and static context
******************************

After a class or function with non-invariant generic parameters compiles,
``zend_check_generic_variance_markers`` and ``zend_check_function_variance_markers`` walk every
position where a ``T`` can appear (signatures, properties, hook signatures, the type-args at
``extends``/``implements``/``use``, bounds, defaults). Both go through ``zend_variance_walk``,
which dispatches each ``T``-ref to the matching parameter list by ``ref->origin``.

Polarity composes through nested generics: outer × slot, with INVARIANT absorbing. Method return
types are covariant, parameters contravariant; readonly and get-only-hooked properties covariant,
set-only-hooked contravariant, r/w and get+set hooked invariant; bounds and defaults always
invariant. Constructors are exempt. Violations produce ``Type parameter T declared covariant
(+T) cannot appear in contravariant position``.

The static-context check is structural: ``zend_check_class_origin_in_static_context`` rejects any
class-origin ``T``-ref that resolves while ``CG(in_static_member_type)`` is set. The flag is set
in ``zend_compile_func_decl`` around the signature of a static method and in
``zend_compile_prop_decl`` around the type of a static property — a static member has no instance,
so an instance-bound parameter has no binding to consult.

******************
 Runtime handlers
******************

``ZEND_VERIFY_GENERIC_ARGUMENTS``
---------------------------------

The handler lives in ``zend_vm_def.h``. It looks up the captured args via
``zend_generic_get_turbofish_args`` and dispatches to ``zend_check_generic_call_arguments``
(call) or ``zend_check_generic_new_arguments`` (instantiation). Both validate arity against the
callee's ``generic_parameters``, then walk the carrier and call
``zend_check_generic_arg_satisfies_bound`` for each ``(arg, parameter)`` pair. Mismatches throw
``ArgumentCountError`` or ``TypeError``; on exception, teardown releases the call frame, the
trampoline name if any, and ``$this`` if held.

When validation passes, the handler installs the bindings: for calls,
``zend_build_generic_call_type_args`` builds the ``zend_type_arg_table`` and assigns it to
``call->type_args``; for instantiation, the canonical monomorph is synthesised (or fetched from
the class table) and ``object_init_ex`` runs against it.

Binding resolution (no inference)
---------------------------------

A type-parameter slot is filled by exactly two sources, in precedence order: an explicit
turbofish argument at the call site, then the parameter's declared default (``<T = mixed>``).
There is **no value-directed inference** — a binding never depends on the runtime value, class,
or zval type of an argument, so what a call site means is decided entirely by what is written at
the call site and in the callee's signature. A declared *bound* (``<T : Animal>``) constrains
explicit bindings but is never itself an implicit call-site binding.

A call that leaves a non-defaulted slot unset is rejected. Detection is layered:

-  Compile time: when the callee is statically known, generic, and has a non-defaulted type
   parameter, a call (or first-class callable creation) without turbofish raises
   ``E_COMPILE_ERROR``.
-  Runtime: dynamically dispatched calls throw ``ArgumentCountError`` from
   ``zend_check_generic_call_arguments`` before the callee body runs. A missing slot is forgiven
   only when the frame already carries a validated binding for it — a closure created by a
   turbofish first-class callable installs its captured table onto every invocation frame, and a
   monomorph reached by name carries its own table.

First-class callables bind at creation: ``id::<int>(...)`` runs the same VERIFY against the
pending frame, and ``ZEND_CALLABLE_CONVERT`` captures the resolved table into the closure
(``zend_closure_capture_type_args``), bypassing the per-func closure cache so sites with
different type arguments never share a closure.

Bare ``T``-ref resolution
-------------------------

``instanceof T`` / ``catch (T $e)`` / ``new T()`` / ``T::method()`` emit an ``IS_UNUSED`` operand
whose ``op.num`` is packed by ``zend_pack_type_param_fetch`` (sub-type
``ZEND_FETCH_CLASS_TYPE_PARAM`` or ``..._TYPE_PARAM_CLASS``, parameter index in the high bits).
``zend_resolve_generic_type_param`` does the lookup at runtime:

-  Function/method-level: ``EX(type_args)->names[index]`` is the canonical class name supplied
   for that parameter at the call site.
-  Class-level: walk from the called scope upward until you reach a direct child of the lexical
   scope — that child *is* the monomorph that owns the binding, on ``ce->generic_type_args``.

If the table doesn't have a binding for that slot, the resolver falls back to the parameter's
declared bound. ``zend_type_arg_canonical_name`` writes the full canonical name into the table
(including scalars), and the resolver retries via ``zend_fetch_class_by_name`` silently — a
scalar name like ``"int"`` simply fails to resolve as a class and triggers the bound fallback,
which is fine for the cases where the bound has a class name.

Deferred generic class resolution
---------------------------------

``instanceof Box<T>`` and ``catch (Box<T> $e)`` — generic named types whose argument list still
contains ``T``-refs at compile time — can't be canonicalised statically because the binding for
``T`` only exists per call frame. The compiler:

1. Compiles the reified ``zend_type`` (``Box<T>`` as a NAMED_WITH_ARGS with a T-ref child).
2. Stashes it in the op_array's ``generic_types->turbofish_args`` under a fresh ``args_id``.
3. Emits an ``IS_UNUSED`` operand whose ``op.num`` packs ``ZEND_FETCH_CLASS_GENERIC_DEFERRED``
   and the ``args_id`` (via ``zend_pack_generic_deferred_fetch``).

At runtime, ``zend_fetch_class`` dispatches on the sub-type to
``zend_resolve_deferred_generic_class``, which:

1. Reads the boxed type from the executing op_array's ``turbofish_args`` table.
2. Recursively walks the NAMED_WITH_ARGS, substituting each ``T``-ref against the current
   frame's bindings (function-level via ``EX(type_args)``, class-level via the same walk as
   bare ``T``-ref resolution).
3. Builds the canonical name string (``"Box<int>"``, ``"Outer<Box<string>>"``, etc.).
4. Hands it to ``zend_fetch_class_by_name``, which finds the existing monomorph or synthesises
   one through ``zend_try_synthesize_monomorph_by_name``.

``ZEND_INSTANCEOF`` and ``ZEND_CATCH`` already accept ``IS_UNUSED`` operands and route through
``zend_fetch_class``; the deferred dispatch is just a new sub-type case there.

************************
 Inheritance and linking
************************

When a child extends a generic ancestor or uses a generic trait, the linker substitutes the
inherited prototype's reified types with the child's bindings before the variance check runs.
``zend_get_inheritance_binding`` returns the *direct* binding (the args at ce's own
``extends``/``implements``/``use`` clause); for ``extends`` it matches by pointer when
``ZEND_ACC_RESOLVED_PARENT`` is set and by name otherwise, since the override check fires before
parent resolution on the deferred-obligation path. ``zend_get_inheritance_binding_full`` walks
the parent chain and direct interfaces, composing each link through
``zend_substitute_leaf_type_param`` and looking the parent up by name if it isn't yet resolved.

``zend_substitute_leaf_type_param`` replaces a class-scope ``T``-ref with ``args[T->index]`` and
propagates the nullable bit, so ``?T`` with ``T = int`` substitutes to ``?int``. Other modifier
bits are not propagated; non-leaf carriers (NAMED_WITH_ARGS, lists) are returned unchanged.
``zend_substitute_proto_type`` is the entry point used by ``zend_do_perform_implementation_check``;
it returns the unsubstituted fallback when ce isn't generic, when the binding lookup fails, or
when substitution would yield another ``T``-ref because the inheriting class forwards the
parameter. ``zend_get_function_declaration`` takes an optional ``subst_ce``: when present, each
printed type runs through ``zend_substitute_proto_type`` so the LSP error message shows the
substituted parent signature rather than the bound view.

***************************
 Member substitution sites
***************************

Substitution is applied at five sites. Each clones the parent's ``zend_property_info`` or
``zend_function``, allocates a fresh ``arg_info`` in the child's arena, and shares body opcodes
with the parent via refcount. Clones keep ``ce`` set to the parent's defining class so
``zend_opcode.c``'s teardown does not double-release shared fields.

-  ``do_inherit_property`` — property type on a class extending a generic parent.
-  Property hook signatures — the ``get`` return slot and the ``set`` value-parameter slot.
-  ``zend_do_traits_property_binding`` — trait property type, with the using class's binding.
-  ``zend_add_trait_method`` — trait method signature, via ``zend_substitute_trait_method_arg_info``.
-  ``do_inherit_method`` — non-overridden inherited method signature, via
   ``zend_maybe_substitute_inherited_method``.

Body opcodes are not re-emitted: ``VERIFY_*`` opcodes were laid down at the parent's compile time
against the unsubstituted view, so the child observes the substituted signature on the clone but
the parent's original opcodes inside.

Diamond detection is in ``zend_validate_generic_diamond_bindings``. It runs *before*
``ce->parent`` is set and ``ZEND_ACC_RESOLVED_PARENT`` is established; the side-table accessors
bypass the resolved-parent gate, so detection sees the bindings directly. For each direct binding
source of ce (parent plus each ``implements`` entry), the check composes the ce-to-source binding
with ``zend_get_inheritance_binding_full`` for every generic target reachable from source.
Records are stored in a transient ``HashTable`` keyed by target ``zend_class_entry *``;
``zend_diamond_record_or_check`` compares only arity. Differing arity is structurally
inconsistent and fires ``zend_error_noreturn`` with both source paths. Differing args at matching
arity are admitted at this stage and resolved downstream — the interface-level merge synthesises
a use-site-variance-aware contract on the inheriting interface, and per-path LSP verifies any
concrete implementer against each substituted parent prototype.

``zend_check_generic_link_bounds`` validates each supplied arg against the corresponding target
parameter's bound. When the supplied arg is a leaf class-scope ``T``-ref of ce, the effective arg
type is ce's own bound on that parameter — "ce's bound on Y must satisfy target's bound on T". An
unbounded child parameter cannot be forwarded into a bounded ancestor slot.

***********************
 Reflection visibility
***********************

Reflection is structured so that the *bound view* answers "what can a caller actually pass at
this slot" — the most common question — while the *reified shape* is reachable for tooling that
needs it:

-  ``ReflectionType::__toString()`` and ``ReflectionNamedType::getName()`` return the bound's
   text (``Foo`` for ``T : Foo``, ``mixed`` for unbounded ``T``, ``Container`` for
   ``Container<int>``). This matches what an ordinary parameter / return / property slot enforces
   at runtime.
-  ``ReflectionNamedType::hasGenericArguments()`` / ``getGenericArguments()`` return the type
   arguments of a generic application as ``ReflectionType`` instances in source order.
-  ``ReflectionTypeParameterReference`` appears inside reified type expressions only — as an
   element of ``getGenericArguments()``, as a bound, as a default, or as a nested arg. It is the
   reflection type you see when a ``T``-ref shows up inside another type, not on a parameter
   whose declared type *is* ``T``.
-  ``ReflectionFunctionAbstract::getGenericParameters()`` and ``ReflectionClass::getGenericParameters()``
   return the entity's own declaration list.
-  ``ReflectionClass::getGenericArgumentsFor{ParentClass,ParentInterface,UsedTrait}()`` returns
   the args supplied at the class's own ``extends`` / ``implements`` / ``use`` clause.

******************************
 Opcache, optimizer, and JIT
******************************

Opcache persistence walks the side tables from ``zend_persist_generic_type_table``: scalar slots
(``return_type``, ``extends``) get duped into SHM via ``zend_shared_memdup_put_free`` and walked
by ``zend_persist_type``; hash slots go through ``zend_persist_generic_type_table_ht``. Calc-side
mirrors. ``zend_persist_attributes`` persists ``zend_attribute->generic_args``. ``zend_persist_type``
handles NAMED_WITH_ARGS values whose ``name`` is ``NULL``.

``zend_try_inline_call`` (``Zend/Optimizer/optimize_func_calls.c``) skips its inline transform
when a ``ZEND_VERIFY_GENERIC_ARGUMENTS`` opcode sits between ``INIT_FCALL`` and ``DO_FCALL``: the
verify handler dereferences ``EX(call)->func``, so NOP-ing ``INIT_FCALL`` while leaving the verify
in place would walk an unrelated frame.

Under JIT, ``ZEND_VERIFY_GENERIC_ARGUMENTS`` falls through to the interpreter. The handler is
cold, so the dispatch cost is dwarfed by the type-comparison work. Code that doesn't use
turbofish doesn't emit the opcode, and the surrounding ``INIT_*`` and ``ZEND_NEW`` JIT
specializations are unaffected. The deferred ``instanceof`` / ``catch`` lookup likewise falls
through to ``zend_fetch_class`` like any other ``IS_UNUSED`` class operand.

Cross-request class-monomorph reuse and ``opcache.preload``
=============================================================

``zend_monomorph_cache_get`` / ``zend_monomorph_cache_add`` (``Zend/zend_inheritance.c``) let a
runtime-synthesized class monomorph be promoted into opcache SHM and reused by every subsequent
request in the pool, instead of being rebuilt per request. The cache is gated strictly on the
*template* class being ``ZEND_ACC_IMMUTABLE`` (``zend_synthesize_monomorph`` checks
``base->ce_flags & ZEND_ACC_IMMUTABLE`` before consulting it at all) — a mutable (per-request)
template can never have its monomorphs shared, since the template itself doesn't outlive the
request.

Whether a generic class ends up immutable depends on how it was linked, not on whether it's
generic. A class becomes immutable when opcache can compile-time-link it — no cross-file
interface/parent dependency that would require triggering autoload during linking. Composer's
autoload order routinely defeats this for real class hierarchies (an interface or parent needed
for linking isn't compiled yet when the implementing class is first compiled), leaving the class
— and therefore every monomorph synthesized from it — mutable and rebuilt fresh every request.
Measured on ``doctrine/collections``' ``ArrayCollection`` (implements ``Collection``,
``Selectable``, ``Stringable`` across separate files) under a persistent worker
(``php-cgi -T<N>,1``): without preload, ``class_monomorphs`` grows linearly with request count
(4 → 88 over 21 requests, 4 → 404 over 100) — every request re-synthesizes its own
``ArrayCollection<int,int>`` from scratch. This is the concrete shape of the "unbounded monomorph
growth" concern raised during the RFC's internals discussion.

``opcache.preload`` forces early/immutable linking of a class hierarchy explicitly, ahead of
serving any request, by having the preload script actually reference the classes (e.g.
``class_exists()``) rather than just registering an autoloader for them. Once the template is
immutable, the class-monomorph cache activates and ``class_monomorphs`` stays flat regardless of
request count, in the same measurement above. This is the recommended mitigation for a
long-lived worker (php-fpm, RoadRunner, Swoole) serving traffic through a generic class whose
hierarchy spans multiple files. Type-arg tables (function/method-level bindings, ``EX(type_args)``)
are unaffected by preload — they're per-call-frame data by design (see above) and are not
persisted to SHM.

Preloading a generic class hierarchy where a child inherits a T-free method unchanged from a
generic parent (the common case — ``count()``/``isEmpty()``-style helpers that never reference
the type parameter, shared rather than cloned per the identity-substitution rule) requires the
fix in ``preload_register_trait_methods`` (``ext/opcache/ZendAccelerator.c``): earlier, this
crashed outright on two bugs — treating an inherited internal-function entry (e.g. from an
interface with a default implementation) as a ``zend_op_array``, and double-registering a
shared, un-cloned method's refcount pointer once per class in the hierarchy that inherits it.
Both are fixed; see ``Zend/tests/generics/opcache/preload_generic.inc`` and
``preload_monomorph_synthesis.phpt`` for the regression coverage.

Test coverage matrix
********************

The generics test corpus (``Zend/tests/generics/**`` and
``ext/reflection/tests/generics/**``) is organised as a *feature axis* × *subsystem axis*
grid. Every cell has at least one ``.phpt`` (or an explicit ``--XFAIL--`` documenting a known
gap). The subsystem directories are:

- ``declaration/`` — parameter lists, bounds, defaults, variance, recursive bounds.
- ``syntax/`` — declaration and turbofish surface syntax (incl. enums / readonly classes as
  type arguments, named + spread arguments with turbofish).
- ``turbofish/`` — call/new/instanceof turbofish, arity, bounds, first-class callables.
- ``errors/`` — arity, bound violations, unresolvable ``T``, and rejected declarations (e.g. an
  enum cannot declare type parameters).
- ``erasure/`` — bound-erasure semantics of the reflected/runtime views.
- ``reification/`` — per-frame ``T`` resolution in bodies, closures, and generators.
- ``inheritance/`` — LSP substitution, diamonds, ``extends``/``implements``/``use`` argument
  forwarding, bound conformance.
- ``scoping/`` — type-parameter shadowing and capture.
- ``traits/`` — generic traits and substituted trait members.
- ``runtime/`` — monomorph synthesis, serialization (``serialize``/``__serialize``/
  ``json_encode``/``var_export``), GC of monomorph cycles, destructors.
- ``async/`` — Fibers and generators carrying a captured ``T`` across suspend/resume.
- ``opcache/`` — preload of generic templates, file-cache round-trip of the generic side
  tables, ``opcache_reset()``.
- ``jit/`` — generic calls and monomorph methods in hot loops under tracing/function JIT.
- ``interop/`` — WeakMap/SplObjectStorage keys, clone, equality, ``func_get_args``,
  ``debug_backtrace``.
- ``reflection/`` (both trees) — the ``ReflectionClass``/``ReflectionGeneric*`` surface and the
  monomorph class hierarchy.

Documented gaps (``--XFAIL--`` tests, tracked for follow-up):

- ``jit/function_jit_generic.phpt`` — function-mode JIT mis-compiles some concrete-turbofish
  generic calls into a by-name lookup of the (non-existent) function-monomorph name; tracing JIT
  and the interpreter are correct. Belongs to the JIT-for-generics workstream.
- ``inheritance/extends_args/reified_instanceof_generic_parent.phpt`` — reified ``instanceof``
  reifies transitively through generic *interfaces* but not through a forwarding generic *parent
  class* (a monomorph extends its template, so the substituted parent is not in the linear
  ancestry).

Behaviours pinned as current-but-notable (regular passing tests, not gaps):

- ``var_export`` of a monomorph emits the re-evaluable form
  ``('Box<int>')::__set_state(...)`` — the canonical name is wrapped as a parenthesised string
  literal because ``Box<int>::`` is not valid class-reference syntax. Plain classes keep the
  classic ``\Name::__set_state(...)`` form. The monomorph's static methods are likewise reachable
  via a dynamic class-name string, ``('Box<int>')::method()``.
- ``never``/``void``/``null``/``mixed`` are accepted as type arguments — the engine treats a type
  argument as an opaque type name; semantic rejection is a static-analysis concern.
- A monomorph method's ``debug_backtrace()``/exception-trace ``class`` is the *template* name
  (methods are shared with the template); the exact monomorph is available via the frame
  ``object``.
