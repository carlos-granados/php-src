<?php
/**
 * Auto-prepended memory probe for the reify memory-benchmark harness.
 * Registers a shutdown function that reports PHP-level memory figures and
 * (when available) reify's generics counters + opcache SHM usage as a single
 * JSON line, written to the path in MEM_PROBE_OUT (append mode). Does not
 * touch stdout, so it's safe to prepend to any of the three workloads
 * (psl/doctrine drivers, BCC) without disturbing their own output.
 */
$__mem_probe_out = getenv('MEM_PROBE_OUT');
if ($__mem_probe_out) {
    register_shutdown_function(static function () use ($__mem_probe_out) {
        $stats = [
            'memory_get_usage' => memory_get_usage(true),
            'memory_get_peak_usage' => memory_get_peak_usage(true),
        ];
        if (function_exists('zend_test_generics_stats')) {
            $stats['generics'] = zend_test_generics_stats();
        }
        if (function_exists('opcache_get_status')) {
            $st = opcache_get_status(false);
            if (is_array($st) && isset($st['memory_usage'])) {
                $stats['opcache_memory_usage'] = $st['memory_usage'];
            }
            if (is_array($st) && isset($st['opcache_statistics']['num_cached_scripts'])) {
                $stats['opcache_cached_scripts'] = $st['opcache_statistics']['num_cached_scripts'];
            }
        }
        file_put_contents($__mem_probe_out, json_encode($stats) . "\n", FILE_APPEND | LOCK_EX);
    });
}
