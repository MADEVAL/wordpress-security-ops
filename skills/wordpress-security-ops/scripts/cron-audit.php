#!/usr/bin/env php
<?php
/**
 * Read-only cron audit.
 *
 * Run with:
 *   wp eval-file scripts/cron-audit.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/redact.php';
require __DIR__ . '/lib/json.php';

if ( ! secops_is_cli() ) {
	secops_json_error( 'CLI only', SECOPS_EXIT_USAGE );
}

$options = secops_parse_args(
	array(
		'help'         => false,
		'json'         => false,
		'include-args' => false,
	)
);
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage: wp eval-file scripts/cron-audit.php [--include-args]\n" .
		"\n" .
		"Flags (also available after the script path):\n" .
		"  --include-args  include redacted arguments\n" .
		"\n" .
		"Output: JSON cron hooks and redacted arguments.\n"
	);
}

secops_require_wp();

$include_args = secops_flag( $options, 'include-args' );
$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
$jobs = array();
$suspicious = array();
$patterns = array( 'eval', 'base64', 'curl', 'wget', 'shell', 'exec', 'system', 'passthru', 'file_get_contents', 'include', 'require', 'http://', 'https://' );

if ( is_array( $cron ) ) {
	foreach ( $cron as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook => $entries ) {
			$count = is_array( $entries ) ? count( $entries ) : 0;
			$args_preview = array();
			if ( $include_args && is_array( $entries ) ) {
				foreach ( $entries as $entry ) {
					if ( isset( $entry['args'] ) ) {
						$args_preview[] = secops_redact( $entry['args'], true );
					}
				}
			}
			$job = array(
				'hook'      => $hook,
				'next_run'  => gmdate( 'Y-m-d\TH:i:s\Z', (int) $timestamp ),
				'count'     => $count,
				'args'      => $include_args ? $args_preview : 'redacted; pass --include-args to preview',
			);
			$jobs[] = $job;

			$haystack = strtolower( $hook . ' ' . wp_json_encode( $args_preview ) );
			foreach ( $patterns as $pattern ) {
				if ( strpos( $haystack, $pattern ) !== false ) {
					$suspicious[] = array(
						'hook'    => $hook,
						'pattern' => $pattern,
						'next_run'=> $job['next_run'],
					);
					break;
				}
			}
		}
	}
}

usort(
	$jobs,
	static function ( array $a, array $b ): int {
		return strcmp( $a['next_run'], $b['next_run'] );
	}
);

$data = array(
	'total'      => count( $jobs ),
	'jobs'       => $jobs,
	'suspicious' => $suspicious,
	'notes'      => array(
		'args' => 'Arguments are redacted even with --include-args.',
		'unknown_hooks' => 'A hook is not automatically malicious; verify its owner.',
	),
);

secops_emit( $data );
