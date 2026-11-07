#!/usr/bin/env php
<?php
/**
 * Merge evidence into a normalized UTC timeline.
 *
 * Read-only. No network. Inputs may be JSON objects with an events array,
 * JSON arrays of events, or JSON Lines with one event per line.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/redact.php';
require __DIR__ . '/lib/json.php';

if ( ! secops_is_cli() ) {
	secops_json_error( 'CLI only', SECOPS_EXIT_USAGE );
}

$options = getopt( '', array( 'help', 'json', 'input:', 'out:', 'csv:', 'mask', 'max-events:' ) );
if ( false === $options ) {
	secops_usage( 'invalid options', SECOPS_EXIT_USAGE );
}
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage:\n" .
		"  php build-timeline.php --input=ability.json,access.jsonl --out=timeline.json --csv=timeline.csv\n" .
		"\n" .
		"Options:\n" .
		"  --input       comma-separated JSON or JSONL files; positional paths also work\n" .
		"  --out         write the JSON timeline to this path\n" .
		"  --csv         write a CSV timeline to this path\n" .
		"  --mask        mask IP and email in the output\n" .
		"  --max-events  limit, default 10000\n" .
		"\n" .
		"Accepted event fields: ts or timestamp or time or created_at, source, actor,\n" .
		"action, target, result, confidence, evidence. Unknown fields are kept under data.\n" .
		"\n" .
		"Timestamps are normalized to UTC. Duplicates are removed by time, source,\n" .
		"actor, action and target.\n"
	);
}

$max_events = (int) secops_opt( $options, 'max-events', '10000' );
if ( $max_events < 1 ) {
	$max_events = 10000;
}
$mask = secops_flag( $options, 'mask' );
$out = (string) secops_opt( $options, 'out', '' );
$csv = (string) secops_opt( $options, 'csv', '' );

$inputs = array();
$input_option = (string) secops_opt( $options, 'input', '' );
if ( '' !== $input_option ) {
	foreach ( explode( ',', $input_option ) as $path ) {
		$path = trim( $path );
		if ( '' !== $path ) {
			$inputs[] = $path;
		}
	}
}
$argv = isset( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ? $_SERVER['argv'] : array();
foreach ( $argv as $index => $arg ) {
	if ( 0 === $index || ! is_string( $arg ) || 0 === strpos( $arg, '-' ) ) {
		continue;
	}
	$inputs[] = $arg;
}
$inputs = array_values( array_unique( $inputs ) );
if ( empty( $inputs ) ) {
	secops_json_error( '--input or at least one file path is required', SECOPS_EXIT_USAGE );
}

/**
 * Normalize a timestamp to Unix seconds.
 *
 * @param mixed $value Raw value.
 */
function secops_timeline_ts( $value ): int {
	if ( is_int( $value ) ) {
		return $value > 9999999999 ? intdiv( $value, 1000 ) : $value;
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}
	if ( preg_match( '/^\d{10,13}$/', $value ) ) {
		$number = (int) $value;
		return $number > 9999999999 ? intdiv( $number, 1000 ) : $number;
	}
	try {
		$date = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		return $date->getTimestamp();
	} catch ( Exception $exception ) {
		return 0;
	}
}

/**
 * Extract an event list from a decoded JSON value.
 *
 * @param array<mixed> $decoded Decoded value.
 * @return array<int,mixed>
 */
function secops_timeline_events( array $decoded ): array {
	if ( isset( $decoded['events'] ) && is_array( $decoded['events'] ) ) {
		return array_values( $decoded['events'] );
	}
	if ( isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
		return array_values( $decoded );
	}
	return array();
}

/**
 * Read JSON, JSONL or a single event object.
 *
 * @return array{events:array<int,mixed>,error:string}
 */
function secops_timeline_read( string $path ): array {
	if ( ! is_file( $path ) ) {
		return array( 'events' => array(), 'error' => 'file not found' );
	}
	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		return array( 'events' => array(), 'error' => 'unreadable' );
	}
	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) ) {
		$events = secops_timeline_events( $decoded );
		if ( empty( $events ) ) {
			foreach ( array( 'ts', 'timestamp', 'time', 'created_at', 'date' ) as $key ) {
				if ( isset( $decoded[ $key ] ) ) {
					$events = array( $decoded );
					break;
				}
			}
		}
		return array( 'events' => $events, 'error' => '' );
	}
	$events = array();
	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$event = json_decode( $line, true );
		if ( is_array( $event ) ) {
			$events[] = $event;
		}
	}
	return array( 'events' => $events, 'error' => '' );
}

/**
 * Normalize one event.
 *
 * @param mixed $raw Raw event.
 * @param int   $index Source index.
 * @return array<string,mixed>|null
 */
function secops_timeline_event( $raw, int $index ): ?array {
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$ts = 0;
	foreach ( array( 'ts', 'timestamp', 'time', 'created_at', 'date' ) as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$ts = secops_timeline_ts( $raw[ $key ] );
			if ( $ts > 0 ) {
				break;
			}
		}
	}
	if ( $ts <= 0 ) {
		return null;
	}
	$event = array(
		'ts'         => gmdate( 'Y-m-d\TH:i:s\Z', $ts ),
		'ts_unix'    => $ts,
		'source'     => isset( $raw['source'] ) ? (string) $raw['source'] : 'input',
		'actor'      => isset( $raw['actor'] ) ? (string) $raw['actor'] : '',
		'action'     => isset( $raw['action'] ) ? (string) $raw['action'] : '',
		'target'     => isset( $raw['target'] ) ? (string) $raw['target'] : '',
		'result'     => isset( $raw['result'] ) ? (string) $raw['result'] : '',
		'confidence' => isset( $raw['confidence'] ) ? (string) $raw['confidence'] : 'review',
		'evidence'   => isset( $raw['evidence'] ) ? (string) $raw['evidence'] : '',
		'input'      => $index,
	);
	$known = array( 'ts', 'timestamp', 'time', 'created_at', 'date', 'source', 'actor', 'action', 'target', 'result', 'confidence', 'evidence' );
	$extra = array();
	foreach ( $raw as $key => $value ) {
		if ( ! in_array( $key, $known, true ) ) {
			$extra[ $key ] = $value;
		}
	}
	if ( ! empty( $extra ) ) {
		$event['data'] = $extra;
	}
	return $event;
}

$events = array();
$skipped_files = array();
$skipped_events = 0;
foreach ( $inputs as $index => $input ) {
	$read = secops_timeline_read( $input );
	if ( '' !== $read['error'] ) {
		$skipped_files[] = array( 'path' => $input, 'reason' => $read['error'] );
		continue;
	}
	foreach ( $read['events'] as $raw ) {
		$event = secops_timeline_event( $raw, $index );
		if ( null === $event ) {
			$skipped_events++;
			continue;
		}
		$key = implode( '|', array( $event['ts'], $event['source'], $event['actor'], $event['action'], $event['target'] ) );
		if ( isset( $events[ $key ] ) ) {
			continue;
		}
		$events[ $key ] = $event;
	}
}

$timeline = array_values( $events );
usort(
	$timeline,
	static function ( array $a, array $b ): int {
		if ( $a['ts_unix'] === $b['ts_unix'] ) {
			return strcmp( $a['source'], $b['source'] );
		}
		return $a['ts_unix'] <=> $b['ts_unix'];
	}
);
$truncated = false;
if ( count( $timeline ) > $max_events ) {
	$timeline = array_slice( $timeline, 0, $max_events );
	$truncated = true;
}
if ( $mask ) {
	foreach ( $timeline as $position => $event ) {
		$timeline[ $position ] = secops_redact( $event, true );
	}
}

$result = array(
	'created_at' => secops_now(),
	'inputs'     => $inputs,
	'events'     => $timeline,
	'counts'     => array(
		'kept'            => count( $timeline ),
		'skipped_events'  => $skipped_events,
		'skipped_inputs'  => count( $skipped_files ),
	),
	'skipped'    => $skipped_files,
	'limits'     => array(
		'max_events' => $max_events,
		'truncated'  => $truncated,
		'masked'     => $mask,
	),
	'notes'      => array(
		'dedupe' => 'Removed duplicates by time, source, actor, action and target.',
		'utc'    => 'All timestamps are UTC and sorted ascending.',
	),
);

$exit_code = empty( $skipped_files ) ? SECOPS_EXIT_OK : SECOPS_EXIT_PARTIAL;

if ( '' !== $csv ) {
	$handle = fopen( 'php://temp', 'r+' );
	if ( false === $handle ) {
		secops_json_error( 'cannot create CSV buffer', SECOPS_EXIT_RUNTIME );
	}
	fputcsv( $handle, array( 'ts', 'source', 'actor', 'action', 'target', 'result', 'confidence', 'evidence' ), ',', '"', '' );
	foreach ( $timeline as $event ) {
		fputcsv(
			$handle,
			array(
				$event['ts'],
				$event['source'],
				$event['actor'],
				$event['action'],
				$event['target'],
				$event['result'],
				$event['confidence'],
				$event['evidence'],
			),
			',',
			'"',
			''
		);
	}
	rewind( $handle );
	$csv_body = stream_get_contents( $handle );
	fclose( $handle );
	if ( false !== $csv_body ) {
		$tmp = $csv . '.tmp-' . getmypid();
		if ( false === file_put_contents( $tmp, $csv_body ) || ! rename( $tmp, $csv ) ) {
			@unlink( $tmp );
			secops_json_error( 'cannot write CSV: ' . $csv, SECOPS_EXIT_RUNTIME );
		}
	}
	$result['csv'] = $csv;
}

if ( '' !== $out ) {
	secops_write_json_file( $out, $result );
	$result['out'] = $out;
}

secops_emit( $result, $exit_code );
