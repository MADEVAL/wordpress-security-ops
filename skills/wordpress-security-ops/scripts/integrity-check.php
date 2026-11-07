#!/usr/bin/env php
<?php
/**
 * Compare a directory with an evidence manifest, or print checksum commands.
 *
 * Read-only. No network by default.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/json.php';

if ( ! secops_is_cli() ) {
	secops_json_error( 'CLI only', SECOPS_EXIT_USAGE );
}

$options = getopt( '', array( 'help', 'json', 'path:', 'manifest:', 'out:', 'max-files:' ) );
if ( false === $options ) {
	secops_usage( 'invalid options', SECOPS_EXIT_USAGE );
}
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage:\n" .
		"  php integrity-check.php --path=/var/www/site --manifest=evidence/manifest.json\n" .
		"  php integrity-check.php --path=/var/www/site\n" .
		"\n" .
		"Without --manifest, prints the WP-CLI checksum commands to run.\n" .
		"With --manifest, compares current files against the manifest.\n" .
		"\n" .
		"Options:\n" .
		"  --path       directory to check (default: current directory)\n" .
		"  --manifest   hash-evidence manifest (JSON object or JSONL)\n" .
		"  --out        write result JSON to this path\n" .
		"  --max-files  limit, default 20000\n"
	);
}

$path = (string) secops_opt( $options, 'path', getcwd() );
$real = realpath( $path );
if ( false === $real || ! is_dir( $real ) ) {
	secops_json_error( 'path is not a directory: ' . $path, SECOPS_EXIT_USAGE );
}
$manifest_path = (string) secops_opt( $options, 'manifest', '' );
$out = (string) secops_opt( $options, 'out', '' );
$max_files = (int) secops_opt( $options, 'max-files', '20000' );
if ( $max_files < 1 ) {
	$max_files = 20000;
}

/**
 * Read a manifest object or JSONL file.
 *
 * @return array<string,array<string,mixed>>
 */
function secops_read_manifest_items( string $path ): array {
	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		secops_fail( 'cannot read manifest: ' . $path, SECOPS_EXIT_USAGE );
	}
	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) && isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
		$items = array();
		foreach ( $decoded['items'] as $item ) {
			if ( isset( $item['path'] ) ) {
				$items[ (string) $item['path'] ] = $item;
			}
		}
		return $items;
	}
	$items = array();
	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$item = json_decode( $line, true );
		if ( is_array( $item ) && isset( $item['path'] ) ) {
			$items[ (string) $item['path'] ] = $item;
		}
	}
	return $items;
}

$commands = array(
	'wp core verify-checksums',
	'wp plugin verify-checksums --all',
	'wp theme verify-checksums --all',
);

if ( '' === $manifest_path ) {
	$result = array(
		'status'   => 'partial',
		'summary'  => array(
			'changed' => 0,
			'missing' => 0,
			'added'   => 0,
		),
		'commands' => $commands,
		'not_checked' => array(
			'checksums' => 'run the commands above; wp.org checksums require network access',
		),
	);
	if ( '' !== $out ) {
		secops_write_json_file( $out, $result );
		secops_emit( array( 'ok' => true, 'out' => $out, 'result' => $result ), SECOPS_EXIT_PARTIAL );
	}
	secops_json_response( secops_envelope( $result ), SECOPS_EXIT_PARTIAL );
}

$items = secops_read_manifest_items( $manifest_path );
$manifest_root = '';
$manifest_raw = json_decode( (string) file_get_contents( $manifest_path ), true );
if ( is_array( $manifest_raw ) && isset( $manifest_raw['root'] ) ) {
	$manifest_root = (string) $manifest_raw['root'];
}
$warnings = array();
if ( '' !== $manifest_root ) {
	$root_real = realpath( $manifest_root );
	if ( false !== $root_real && ! secops_path_within( $real, $root_real ) && ! secops_path_within( $root_real, $real ) ) {
		$warnings[] = 'manifest root differs from --path: ' . $manifest_root;
	}
}
$current = array();
$count = 0;
$truncated = false;
try {
	$directory = new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS );
	$iterator = new RecursiveIteratorIterator( $directory, RecursiveIteratorIterator::LEAVES_ONLY );
	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		$count++;
		if ( $count > $max_files ) {
			$truncated = true;
			break;
		}
		if ( $file->isLink() ) {
			continue;
		}
		$relative = ltrim( str_replace( $real, '', $file->getPathname() ), DIRECTORY_SEPARATOR );
		$relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
		if ( strpos( $relative, '.git/' ) === 0 || strpos( $relative, 'node_modules/' ) === 0 ) {
			continue;
		}
		$hash = hash_file( 'sha256', $file->getPathname() );
		if ( false !== $hash ) {
			$current[ $relative ] = $hash;
		}
	}
} catch ( UnexpectedValueException $exception ) {
	secops_json_error( 'iterator error: ' . $exception->getMessage(), SECOPS_EXIT_RUNTIME );
}

$changed = array();
$missing = array();
foreach ( $items as $rel => $item ) {
	if ( ! isset( $current[ $rel ] ) ) {
		$missing[] = array( 'path' => $rel, 'expected' => isset( $item['sha256'] ) ? $item['sha256'] : '' );
		continue;
	}
	if ( isset( $item['sha256'] ) && ! hash_equals( (string) $item['sha256'], $current[ $rel ] ) ) {
		$changed[] = array(
			'path'     => $rel,
			'expected' => (string) $item['sha256'],
			'actual'   => $current[ $rel ],
		);
	}
}
$added = array();
foreach ( $current as $rel => $hash ) {
	if ( ! isset( $items[ $rel ] ) ) {
		$added[] = array( 'path' => $rel, 'sha256' => $hash );
	}
}

$status = ( empty( $changed ) && empty( $missing ) ) ? 'ok' : 'changed';
if ( $truncated ) {
	$status = 'partial';
}
$result = array(
	'status'    => $status,
	'summary'   => array(
		'manifest_files' => count( $items ),
		'current_files'  => count( $current ),
		'changed'        => count( $changed ),
		'missing'        => count( $missing ),
		'added'          => count( $added ),
	),
	'changed'   => $changed,
	'missing'   => $missing,
	'added'     => $added,
	'warnings'  => $warnings,
	'commands'  => $commands,
	'limits'    => array( 'max_files' => $max_files, 'truncated' => $truncated ),
);

if ( '' !== $out ) {
	secops_write_json_file( $out, $result );
	secops_emit( array( 'ok' => 'ok' === $status, 'out' => $out, 'result' => $result ), 'ok' === $status ? SECOPS_EXIT_OK : SECOPS_EXIT_PARTIAL );
}

$envelope = secops_envelope( $result );
$envelope['ok'] = ( 'ok' === $status );
secops_json_response( $envelope, 'ok' === $status ? SECOPS_EXIT_OK : SECOPS_EXIT_PARTIAL );
