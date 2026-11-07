#!/usr/bin/env php
<?php
/**
 * Build a SHA-256 evidence manifest for a directory.
 *
 * Read-only. Does not follow symlinks outside the requested root.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/json.php';

if ( ! secops_is_cli() ) {
	secops_json_error( 'CLI only', SECOPS_EXIT_USAGE );
}

$options = getopt( '', array( 'help', 'json', 'path:', 'out:', 'max-files:', 'max-size:', 'exclude:', 'follow-symlinks' ) );
if ( false === $options ) {
	secops_usage( 'invalid options', SECOPS_EXIT_USAGE );
}
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage:\n" .
		"  php hash-evidence.php --path=/var/www/site --out=evidence/manifest.json\n" .
		"\n" .
		"Options:\n" .
		"  --path          directory to hash (default: current directory)\n" .
		"  --out           output manifest path; stdout if omitted\n" .
		"  --max-files     limit, default 20000\n" .
		"  --max-size      skip files larger than bytes, default 104857600\n" .
		"  --exclude       comma-separated directory names, default .git,node_modules\n" .
		"  --follow-symlinks  follow symlinks inside the root\n" .
		"\n" .
		"Output: JSON evidence manifest with SHA-256, size and mtime.\n"
	);
}

$path = (string) secops_opt( $options, 'path', getcwd() );
$real = realpath( $path );
if ( false === $real || ! is_dir( $real ) ) {
	secops_json_error( 'path is not a directory: ' . $path, SECOPS_EXIT_USAGE );
}
$out = (string) secops_opt( $options, 'out', '' );
$max_files = (int) secops_opt( $options, 'max-files', '20000' );
$max_size = (int) secops_opt( $options, 'max-size', '104857600' );
$exclude_raw = (string) secops_opt( $options, 'exclude', '.git,node_modules' );
$follow = secops_flag( $options, 'follow-symlinks' );
$exclude = array_filter( array_map( 'trim', explode( ',', $exclude_raw ) ) );

if ( $max_files < 1 ) {
	$max_files = 20000;
}
if ( $max_size < 1 ) {
	$max_size = 104857600;
}

$items = array();
$skipped = array();
$warnings = array();
$count = 0;

try {
	$directory = new RecursiveDirectoryIterator(
		$real,
		FilesystemIterator::SKIP_DOTS | ( $follow ? 0 : FilesystemIterator::CURRENT_AS_FILEINFO )
	);
	$iterator = new RecursiveIteratorIterator( $directory, RecursiveIteratorIterator::LEAVES_ONLY );
	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		$count++;
		if ( $count > $max_files ) {
			$warnings[] = 'max_files reached at ' . $max_files;
			break;
		}
		$pathname = $file->getPathname();
		$relative = ltrim( str_replace( $real, '', $pathname ), DIRECTORY_SEPARATOR );
		$relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
		$parts = explode( '/', $relative );
		$skip = false;
		foreach ( $parts as $part ) {
			if ( in_array( $part, $exclude, true ) ) {
				$skip = true;
				break;
			}
		}
		if ( $skip ) {
			continue;
		}
		if ( $file->isLink() && ! $follow ) {
			$skipped[] = array( 'path' => $relative, 'reason' => 'symlink' );
			continue;
		}
		if ( $file->getSize() > $max_size ) {
			$skipped[] = array( 'path' => $relative, 'reason' => 'max_size', 'size' => $file->getSize() );
			continue;
		}
		$hash = hash_file( 'sha256', $pathname );
		if ( false === $hash ) {
			$skipped[] = array( 'path' => $relative, 'reason' => 'unreadable' );
			continue;
		}
		$items[] = array(
			'path'         => $relative,
			'sha256'       => $hash,
			'size'         => $file->getSize(),
			'mtime'        => gmdate( 'Y-m-d\TH:i:s\Z', (int) $file->getMTime() ),
			'collected_at' => secops_now(),
			'collector'    => 'hash-evidence.php',
			'source'       => 'filesystem',
			'redaction'    => 'none',
		);
	}
} catch ( UnexpectedValueException $exception ) {
	$warnings[] = 'iterator error: ' . $exception->getMessage();
}

if ( '' !== $out ) {
	$out_real = realpath( dirname( $out ) );
	if ( false !== $out_real && secops_path_within( $out, $real ) ) {
		$warnings[] = 'output is inside the hashed root; store evidence outside the webroot';
	}
}

$manifest = array(
	'manifest_version' => '1.0',
	'created_at'       => secops_now(),
	'collector'        => 'hash-evidence.php',
	'root'             => $real,
	'items'            => $items,
	'skipped'          => $skipped,
	'warnings'         => $warnings,
	'counts'           => array(
		'files'   => count( $items ),
		'skipped' => count( $skipped ),
	),
);

if ( '' !== $out ) {
	secops_write_json_file( $out, $manifest );
	secops_emit( array( 'ok' => true, 'out' => $out, 'counts' => $manifest['counts'], 'warnings' => $warnings ) );
}

secops_json_response( $manifest );
