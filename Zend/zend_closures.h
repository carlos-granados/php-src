/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright © Zend Technologies Ltd., a subsidiary company of          |
   |     Perforce Software, Inc., and Contributors.                       |
   +----------------------------------------------------------------------+
   | This source file is subject to the Modified BSD License that is      |
   | bundled with this package in the file LICENSE, and is available      |
   | through the World Wide Web at <https://www.php.net/license/>.        |
   |                                                                      |
   | SPDX-License-Identifier: BSD-3-Clause                                |
   +----------------------------------------------------------------------+
   | Authors: Christian Seiler <chris_se@gmx.net>                         |
   |          Dmitry Stogov <dmitry@php.net>                              |
   +----------------------------------------------------------------------+
*/

#ifndef ZEND_CLOSURES_H
#define ZEND_CLOSURES_H

#include "zend_types.h"

BEGIN_EXTERN_C()

typedef struct _zend_closure {
	zend_object       std;
	zend_function     func;
	zval              this_ptr;
	zend_class_entry *called_scope;
	zif_handler       orig_internal_handler;
	/* Snapshot of the generic type-arg table from the frame that created
	 * the closure. NULL when the closure was created outside a generic
	 * frame. Cleared on closure free_obj.
	 *
	 * Two distinct cases, told apart by captured_type_args_shared:
	 *  - Exclusively owned (shared=false): the closure holds the only
	 *    reference (zend_type_arg_table_capture_or_share deep-cloned
	 *    because the source wasn't already guaranteed to outlive this
	 *    frame). Marked persisted so call-frame teardown skips destroying
	 *    it; free_obj un-persists and destroys it.
	 *  - Shared (shared=true): the source table was already persisted
	 *    (e.g. a naked call's per-function defaults-cache table, see
	 *    Zend/zend_compile.c) and therefore already guaranteed to outlive
	 *    this closure regardless -- capture_or_share returned the SAME
	 *    pointer instead of cloning it, to avoid the clone's allocation +
	 *    per-entry copy work entirely (this is the common case for a
	 *    closure created inside a generic function body and never escaping
	 *    it, e.g. `array_map(static fn($v) => ..., $x)`). free_obj must
	 *    NOT touch persisted or destroy this pointer -- its real owner
	 *    (the defaults-cache, released at request/class-teardown) still
	 *    references it. */
	struct _zend_type_arg_table *captured_type_args;
	bool captured_type_args_shared;
} zend_closure;

/* This macro depends on zend_closure structure layout */
#define ZEND_CLOSURE_OBJECT(op_array) \
	((zend_object*)((char*)(op_array) - sizeof(zend_object)))

void zend_register_closure_ce(void);
void zend_closure_bind_var(zval *closure_zv, zend_string *var_name, zval *var);
void zend_closure_bind_var_ex(zval *closure_zv, uint32_t offset, zval *val);
void zend_closure_from_frame(zval *closure_zv, const zend_execute_data *frame);

extern ZEND_API zend_class_entry *zend_ce_closure;

ZEND_API void zend_create_closure(zval *res, zend_function *op_array, zend_class_entry *scope, zend_class_entry *called_scope, zval *this_ptr);
ZEND_API void zend_create_fake_closure(zval *res, zend_function *op_array, zend_class_entry *scope, zend_class_entry *called_scope, zval *this_ptr);
ZEND_API void zend_closure_capture_type_args(zval *closure_zv, struct _zend_type_arg_table *src);
ZEND_API uint32_t zend_generic_fn_required_type_params(const zend_function *fn);
ZEND_API zend_function *zend_get_closure_invoke_method(zend_object *obj);
ZEND_API const zend_function *zend_get_closure_method_def(zend_object *obj);
ZEND_API zval* zend_get_closure_this_ptr(zval *obj);

END_EXTERN_C()

#endif
