<?php
/**
 * Shared bootstrap for wordpress-security-ops scripts.
 *
 * No business logic. Path safety, JSON, CLI helpers and exit codes only.
 */

declare(strict_types=1);

if ( ! defined( 'SECOPS_VERSION' ) ) {
	define( 'SECOPS_VERSION', '0.1.0' );
}

if ( ! defined( 'SECOPS_EXIT_OK' ) ) {
	define( 'SECOPS_EXIT_OK', 0 );
	define( 'SECOPS_EXIT_USAGE', 2 );
	define( 'SECOPS_EXIT_PARTIAL', 3 );
	define( 'SECOPS_EXIT_POLICY', 4 );
	define( 'SECOPS_EXIT_RUNTIME', 5 );
}

/**
 * Whether the script runs from the command line.
 */
function secops_is_cli(): bool {
	return PHP_SAPI === 'cli';
}

/**
 * Current UTC timestamp in ISO 8601.
 */
function secops_now(): string {
	return gmdate( 'Y-m-d\TH:i:s\Z' );
}

/**
 * JSON encode with stable, readable defaults.
 *
 * @param mixed $data Value to encode.
 */
function secops_json_encode( $data ): string {
	$json = json_encode(
		$data,
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	return false === $json ? '{}' : $json;
}

/**
 * Decode JSON into an associative array.
 *
 * @param string $json JSON text.
 * @return array<mixed>
 */
function secops_json_decode( string $json ): array {
	$data = json_decode( $json, true );
	return is_array( $data ) ? $data : array();
}

/**
 * Read a JSON file.
 *
 * @return array<mixed>
 */
function secops_read_json_file( string $path ): array {
	if ( ! is_file( $path ) ) {
		secops_fail( 'file not found: ' . $path, SECOPS_EXIT_USAGE );
	}
	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		secops_fail( 'cannot read: ' . $path, SECOPS_EXIT_RUNTIME );
	}
	return secops_json_decode( $raw );
}

/**
 * Write JSON atomically and restrict permissions where possible.
 *
 * @param mixed $data Value to write.
 */
function secops_write_json_file( string $path, $data ): void {
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) {
		secops_fail( 'cannot create directory: ' . $dir, SECOPS_EXIT_RUNTIME );
	}
	$tmp = $path . '.tmp-' . getmypid();
	$json = secops_json_encode( $data ) . "\n";
	if ( false === file_put_contents( $tmp, $json ) ) {
		secops_fail( 'cannot write: ' . $path, SECOPS_EXIT_RUNTIME );
	}
	if ( ! rename( $tmp, $path ) ) {
		@unlink( $tmp );
		secops_fail( 'cannot replace: ' . $path, SECOPS_EXIT_RUNTIME );
	}
	if ( DIRECTORY_SEPARATOR !== '\\' ) {
		@chmod( $path, 0600 );
	}
}

/**
 * Print a JSON response and exit.
 *
 * @param mixed $data Response data.
 */
function secops_json_response( $data, int $exit_code = SECOPS_EXIT_OK ): void {
	echo secops_json_encode( $data ) . "\n";
	exit( $exit_code );
}

/**
 * Print a JSON error and exit.
 *
 * @param array<string,mixed> $extra Extra fields.
 */
function secops_json_error( string $message, int $exit_code = SECOPS_EXIT_RUNTIME, array $extra = array() ): void {
	$data = array_merge(
		array(
			'ok'      => false,
			'error'   => $message,
			'code'    => $exit_code,
			'at'      => secops_now(),
			'version' => SECOPS_VERSION,
		),
		$extra
	);
	secops_json_response( $data, $exit_code );
}

/**
 * Print an error to stderr and exit.
 */
function secops_fail( string $message, int $exit_code = SECOPS_EXIT_RUNTIME ): void {
	if ( secops_is_cli() ) {
		fwrite( STDERR, 'error: ' . $message . "\n" );
	}
	exit( $exit_code );
}

/**
 * Parse long options from the full argv, wherever they appear.
 *
 * PHP getopt() stops at the first non-option, which breaks flags when a script
 * is run as `wp eval-file script.php --include-pii`. This parser scans every
 * argument, ignores unknown WP-CLI flags and only accepts whitelisted names.
 *
 * @param array<string,bool> $spec Option name => whether it takes a value.
 * @return array<string,mixed>
 */
function secops_parse_args( array $spec ): array {
	$options = array();
	$argv    = isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ? $_SERVER['argv'] : array();
	$count   = count( $argv );
	for ( $i = 0; $i < $count; $i++ ) {
		$arg = $argv[ $i ];
		if ( ! is_string( $arg ) || 0 !== strpos( $arg, '--' ) || 2 === strlen( $arg ) ) {
			continue;
		}
		$name  = substr( $arg, 2 );
		$value = null;
		$eq    = strpos( $name, '=' );
		if ( false !== $eq ) {
			$value = substr( $name, $eq + 1 );
			$name  = substr( $name, 0, $eq );
		}
		if ( ! array_key_exists( $name, $spec ) ) {
			continue;
		}
		$takes_value = (bool) $spec[ $name ];
		if ( $takes_value && null === $value ) {
			$next = isset( $argv[ $i + 1 ] ) ? $argv[ $i + 1 ] : null;
			if ( is_string( $next ) && '' !== $next && 0 !== strpos( $next, '-' ) ) {
				$value = $next;
				$i++;
			}
		}
		$options[ $name ] = $takes_value ? (string) ( null === $value ? '' : $value ) : true;
	}
	return $options;
}

/**
 * Get a long option from getopt output.
 *
 * @param array<string,mixed> $options Parsed options.
 * @param mixed               $default Default value.
 * @return mixed
 */
function secops_opt( array $options, string $name, $default = null ) {
	return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
}

/**
 * Whether a flag is present.
 *
 * @param array<string,mixed> $options Parsed options.
 */
function secops_flag( array $options, string $name ): bool {
	return array_key_exists( $name, $options );
}

/**
 * Normalize a path and check that it stays inside a root.
 */
function secops_path_within( string $path, string $root ): bool {
	$root_real = realpath( $root );
	if ( false === $root_real ) {
		return false;
	}
	$path_real = realpath( $path );
	if ( false === $path_real ) {
		$parent = realpath( dirname( $path ) );
		if ( false === $parent ) {
			return false;
		}
		$path_real = $parent . DIRECTORY_SEPARATOR . basename( $path );
	}
	$root_cmp = DIRECTORY_SEPARATOR === '\\' ? strtolower( $root_real ) : $root_real;
	$path_cmp = DIRECTORY_SEPARATOR === '\\' ? strtolower( $path_real ) : $path_real;
	$root_cmp = rtrim( $root_cmp, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	return $path_cmp === rtrim( $root_cmp, DIRECTORY_SEPARATOR ) || strpos( $path_cmp, $root_cmp ) === 0;
}

/**
 * Require WordPress context.
 */
function secops_require_wp(): void {
	if ( ! defined( 'ABSPATH' ) ) {
		secops_fail( 'run this script with wp eval-file or inside a WordPress bootstrap', SECOPS_EXIT_USAGE );
	}
}

/**
 * Whether WP-CLI is available.
 */
function secops_has_wp_cli(): bool {
	return defined( 'WP_CLI' ) && WP_CLI;
}

/**
 * Format bytes for a human-readable field.
 */
function secops_bytes( int $bytes ): string {
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$value = (float) $bytes;
	$index = 0;
	while ( $value >= 1024 && $index < count( $units ) - 1 ) {
		$value /= 1024;
		$index++;
	}
	return round( $value, 2 ) . ' ' . $units[ $index ];
}

/**
 * Truncate a string safely.
 */
function secops_truncate( string $text, int $limit = 240 ): string {
	if ( strlen( $text ) <= $limit ) {
		return $text;
	}
	return substr( $text, 0, $limit ) . '...';
}

/**
 * Print a usage block and exit.
 */
function secops_usage( string $text, int $exit_code = SECOPS_EXIT_USAGE ): void {
	echo $text . "\n";
	exit( $exit_code );
}
