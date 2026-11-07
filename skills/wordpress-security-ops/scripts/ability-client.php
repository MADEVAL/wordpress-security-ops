#!/usr/bin/env php
<?php
/**
 * Ability client for BotBlocker Security.
 *
 * Transports:
 *   rest - WordPress Abilities REST API (read/write)
 *   mcp  - default MCP server (read-only)
 *
 * WP-CLI is the recommended fallback for write operations. Operator MCP is
 * intentionally not implemented here; use REST or WP-CLI.
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/redact.php';
require __DIR__ . '/lib/json.php';

/**
 * Perform an HTTP request with cURL or a stream fallback.
 *
 * @param array<int,string> $headers Request headers.
 * @return array<string,mixed>
 */
function secops_http_request( string $method, string $url, array $headers, ?string $body = null, int $timeout = 30, bool $verify_tls = true ): array {
	$method = strtoupper( $method );
	if ( function_exists( 'curl_init' ) ) {
		$response_headers = array();
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, $verify_tls );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, $verify_tls ? 2 : 0 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'wordpress-security-ops/' . SECOPS_VERSION );
		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			static function ( $handle, string $line ) use ( &$response_headers ): int {
				$trimmed = trim( $line );
				if ( '' === $trimmed ) {
					return strlen( $line );
				}
				if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $trimmed, $match ) ) {
					$response_headers[':status'] = (int) $match[1];
					return strlen( $line );
				}
				$colon = strpos( $trimmed, ':' );
				if ( false !== $colon ) {
					$key = strtolower( trim( substr( $trimmed, 0, $colon ) ) );
					$response_headers[ $key ] = trim( substr( $trimmed, $colon + 1 ) );
				}
				return strlen( $line );
			}
		);
		if ( ! empty( $headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}
		if ( null !== $body ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		}
		$response_body = curl_exec( $ch );
		$error = curl_error( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		if ( PHP_VERSION_ID < 80000 ) {
			curl_close( $ch );
		}
		if ( false === $response_body ) {
			return array(
				'status'  => 0,
				'headers' => $response_headers,
				'body'    => '',
				'error'   => $error ?: 'HTTP request failed',
			);
		}
		return array(
			'status'  => $status,
			'headers' => $response_headers,
			'body'    => (string) $response_body,
			'error'   => '',
		);
	}

	if ( ! function_exists( 'http_get_last_response_headers' ) ) {
		return array(
			'status'  => 0,
			'headers' => array(),
			'body'    => '',
			'error'   => 'cURL is not available and PHP 8.4+ HTTP functions are missing; use WP-CLI or enable cURL',
		);
	}

	$context_options = array(
		'http' => array(
			'method'          => $method,
			'header'          => implode( "\r\n", $headers ),
			'timeout'         => $timeout,
			'ignore_errors'   => true,
			'follow_location' => 0,
		),
	);
	if ( null !== $body ) {
		$context_options['http']['content'] = $body;
	}
	$context = stream_context_create( $context_options );
	$response_body = @file_get_contents( $url, false, $context );
	$raw_headers = http_get_last_response_headers();
	$raw_headers = is_array( $raw_headers ) ? $raw_headers : array();
	$response_headers = array();
	$status = 0;
	foreach ( $raw_headers as $line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $match ) ) {
			$status = (int) $match[1];
			continue;
		}
		$colon = strpos( $line, ':' );
		if ( false !== $colon ) {
			$response_headers[ strtolower( trim( substr( $line, 0, $colon ) ) ) ] = trim( substr( $line, $colon + 1 ) );
		}
	}
	if ( false === $response_body ) {
		return array(
			'status'  => $status,
			'headers' => $response_headers,
			'body'    => '',
			'error'   => 'HTTP request failed',
		);
	}
	return array(
		'status'  => $status,
		'headers' => $response_headers,
		'body'    => (string) $response_body,
		'error'   => '',
	);
}

/**
 * Build the REST endpoint for an ability.
 */
function secops_rest_endpoint( string $base, string $ability ): string {
	$path = implode( '/', array_map( 'rawurlencode', explode( '/', $ability ) ) );
	return rtrim( $base, '/' ) . '/wp-json/wp-abilities/v1/abilities/' . $path . '/run';
}

/**
 * Basic auth header. Never log this value.
 */
function secops_basic_auth( string $user, string $password ): string {
	return 'Authorization: Basic ' . base64_encode( $user . ':' . $password );
}

/**
 * Decode a JSON body if possible.
 *
 * @return mixed
 */
function secops_decode_body( string $body ) {
	$decoded = json_decode( $body, true );
	return null === $decoded ? secops_truncate( $body, 4000 ) : $decoded;
}

/**
 * Decode an MCP response that may arrive as JSON or as an SSE stream.
 *
 * @return mixed
 */
function secops_decode_mcp_body( string $body ) {
	$trimmed = trim( $body );
	if ( '' === $trimmed || 0 !== strpos( $trimmed, 'event:' ) ) {
		return secops_decode_body( $body );
	}
	$data = array();
	foreach ( preg_split( '/\r?\n/', $body ) as $line ) {
		$line = (string) $line;
		if ( 0 === strpos( $line, 'data:' ) ) {
			$data[] = trim( substr( $line, 5 ) );
		}
	}
	$decoded = json_decode( implode( "\n", $data ), true );
	return null === $decoded ? secops_truncate( $body, 4000 ) : $decoded;
}

/**
 * Call an ability over REST.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array<string,mixed>
 */
function secops_rest_call( string $base, string $ability, array $input, string $method, string $user, string $password, int $timeout, bool $verify_tls ): array {
	$headers = array(
		'Accept: application/json',
		secops_basic_auth( $user, $password ),
	);
	$endpoint = secops_rest_endpoint( $base, $ability );
	if ( 'POST' === $method ) {
		$body = json_encode( array( 'input' => $input ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$headers[] = 'Content-Type: application/json';
		$response = secops_http_request( 'POST', $endpoint, $headers, false === $body ? '{}' : $body, $timeout, $verify_tls );
	} else {
		$endpoint .= '?input=' . rawurlencode( (string) json_encode( $input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$response = secops_http_request( $method, $endpoint, $headers, null, $timeout, $verify_tls );
	}
	$response['endpoint'] = $endpoint;
	return $response;
}

/**
 * Call an ability over the default MCP server.
 *
 * @param array<string,mixed> $input Ability input.
 * @return array<string,mixed>
 */
function secops_mcp_call( string $url, string $ability, array $input, string $user, string $password, int $timeout, bool $verify_tls, string $protocol ): array {
	$headers = array(
		'Content-Type: application/json',
		'Accept: application/json, text/event-stream',
	);
	if ( '' !== $user ) {
		$headers[] = secops_basic_auth( $user, $password );
	}

	$init_payload = array(
		'jsonrpc' => '2.0',
		'id'      => 1,
		'method'  => 'initialize',
		'params'  => array(
			'protocolVersion' => $protocol,
			'capabilities'    => new stdClass(),
			'clientInfo'      => array(
				'name'    => 'wordpress-security-ops',
				'version' => SECOPS_VERSION,
			),
		),
	);
	$init = secops_http_request(
		'POST',
		$url,
		$headers,
		(string) json_encode( $init_payload, JSON_UNESCAPED_SLASHES ),
		$timeout,
		$verify_tls
	);
	$session = isset( $init['headers']['mcp-session-id'] ) ? (string) $init['headers']['mcp-session-id'] : '';
	if ( '' === $session ) {
		return array(
			'status'  => $init['status'],
			'headers' => $init['headers'],
			'body'    => $init['body'],
			'error'   => 'no Mcp-Session-Id in initialize response',
		);
	}
	$session_headers = $headers;
	$session_headers[] = 'Mcp-Session-Id: ' . $session;

	$initialized = array(
		'jsonrpc' => '2.0',
		'method'  => 'notifications/initialized',
	);
	secops_http_request(
		'POST',
		$url,
		$session_headers,
		(string) json_encode( $initialized, JSON_UNESCAPED_SLASHES ),
		$timeout,
		$verify_tls
	);

	$call = array(
		'jsonrpc' => '2.0',
		'id'      => 2,
		'method'  => 'tools/call',
		'params'  => array(
			'name'      => 'mcp-adapter-execute-ability',
			'arguments' => array(
				'ability_name' => $ability,
				'parameters'   => $input,
			),
		),
	);
	$response = secops_http_request(
		'POST',
		$url,
		$session_headers,
		(string) json_encode( $call, JSON_UNESCAPED_SLASHES ),
		$timeout,
		$verify_tls
	);

	// Best effort session close.
	$close_headers = $headers;
	$close_headers[] = 'Mcp-Session-Id: ' . $session;
	secops_http_request( 'DELETE', $url, $close_headers, null, min( $timeout, 5 ), $verify_tls );

	return array(
		'status'  => $response['status'],
		'headers' => $response['headers'],
		'body'    => $response['body'],
		'error'   => $response['error'],
	);
}

/**
 * Append a redacted call record to a JSONL log.
 *
 * @param array<string,mixed> $record Record.
 */
function secops_append_log( string $path, array $record ): void {
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) {
		return;
	}
	$line = json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false !== $line ) {
		file_put_contents( $path, $line . "\n", FILE_APPEND | LOCK_EX );
	}
}

$options = getopt(
	'',
	array(
		'help',
		'json',
		'text',
		'transport:',
		'url:',
		'user:',
		'app-password:',
		'ability:',
		'input:',
		'input-file:',
		'dry-run',
		'write',
		'confirm:',
		'method:',
		'timeout:',
		'profile:',
		'log:',
		'insecure',
		'allow-http',
		'protocol:',
	)
);

if ( false === $options ) {
	secops_usage( 'invalid options', SECOPS_EXIT_USAGE );
}
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage:\n" .
		"  php ability-client.php --ability=botblocker/get-firewall-status --transport=rest\n" .
		"  php ability-client.php --ability=botblocker/check-ip --input='{\"ip\":\"203.0.113.10\"}'\n" .
		"  php ability-client.php --ability=botblocker/block-ip --write --dry-run --confirm=BLOCK --input='{\"ip\":\"203.0.113.10\"}'\n" .
		"\n" .
		"Transport:\n" .
		"  rest  WordPress Abilities REST API (read/write)\n" .
		"  mcp   default MCP server (read-only)\n" .
		"\n" .
		"Environment:\n" .
		"  SECOPS_URL, SECOPS_USER, SECOPS_APP_PASSWORD, SECOPS_MCP_URL, SECOPS_TIMEOUT\n" .
		"\n" .
		"Notes:\n" .
		"  - The app password and Authorization header are never printed or logged.\n" .
		"  - A write without --confirm is refused.\n" .
		"  - Operator MCP is not implemented; use REST or WP-CLI.\n"
	);
}

$ability = (string) secops_opt( $options, 'ability', '' );
if ( '' === $ability ) {
	secops_json_error( '--ability is required', SECOPS_EXIT_USAGE );
}

$input = array();
if ( secops_flag( $options, 'input-file' ) ) {
	$input = secops_read_json_file( (string) secops_opt( $options, 'input-file', '' ) );
} elseif ( secops_flag( $options, 'input' ) ) {
	$raw = (string) secops_opt( $options, 'input', '' );
	if ( 0 === strpos( $raw, '@' ) ) {
		$input = secops_read_json_file( substr( $raw, 1 ) );
	} else {
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			secops_json_error( '--input must be a JSON object', SECOPS_EXIT_USAGE );
		}
		$input = $decoded;
	}
}

$dry_run = secops_flag( $options, 'dry-run' );
$write = secops_flag( $options, 'write' );
$confirm = (string) secops_opt( $options, 'confirm', '' );
if ( ( $write || $dry_run ) && '' === $confirm ) {
	secops_json_error( '--confirm is required for --write and --dry-run', SECOPS_EXIT_USAGE );
}
if ( $dry_run ) {
	$input['dry_run'] = true;
}
if ( '' !== $confirm ) {
	$input['confirm'] = $confirm;
}

$transport = (string) secops_opt( $options, 'transport', '' );
$url = (string) secops_opt( $options, 'url', '' );
$user = (string) secops_opt( $options, 'user', (string) getenv( 'SECOPS_USER' ) );
$password = (string) secops_opt( $options, 'app-password', (string) getenv( 'SECOPS_APP_PASSWORD' ) );
$timeout = (int) secops_opt( $options, 'timeout', (string) getenv( 'SECOPS_TIMEOUT' ) ?: '30' );
$profile = (string) secops_opt( $options, 'profile', (string) getenv( 'SECOPS_MCP_PROFILE' ) ?: 'default' );
$protocol = (string) secops_opt( $options, 'protocol', '2025-11-25' );
$verify_tls = ! secops_flag( $options, 'insecure' );
$allow_http = secops_flag( $options, 'allow-http' );
$log_path = (string) secops_opt( $options, 'log', '' );

if ( array_key_exists( 'app-password', $options ) ) {
	fwrite( STDERR, "warning: prefer SECOPS_APP_PASSWORD; a command-line secret is visible in the process list and shell history.\n" );
}

if ( '' === $transport ) {
	$transport = '' !== $url || '' !== (string) getenv( 'SECOPS_URL' ) ? 'rest' : 'mcp';
}
if ( 'mcp' === $transport ) {
	if ( 'default' !== $profile ) {
		secops_json_error( 'operator MCP is not implemented in this client; use REST or WP-CLI', SECOPS_EXIT_POLICY );
	}
	if ( $write || $dry_run ) {
		secops_json_error( 'default MCP is read-only; use REST or WP-CLI for writes', SECOPS_EXIT_POLICY );
	}
}
if ( 'rest' === $transport && '' === $url ) {
	$url = (string) getenv( 'SECOPS_URL' );
}
if ( 'mcp' === $transport && '' === $url ) {
	$url = (string) getenv( 'SECOPS_MCP_URL' );
}

$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
$host = (string) parse_url( $url, PHP_URL_HOST );
if ( 'http' === $scheme && ! $allow_http ) {
	$local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	if ( ! $local ) {
		secops_json_error( 'HTTPS is required; use --allow-http only for a local test site', SECOPS_EXIT_POLICY );
	}
}
if ( 'https' !== $scheme && 'http' !== $scheme ) {
	secops_json_error( 'invalid URL: ' . $url, SECOPS_EXIT_USAGE );
}

if ( 'rest' === $transport ) {
	if ( '' === $user || '' === $password ) {
		secops_json_error( 'REST requires --user and --app-password or SECOPS_USER / SECOPS_APP_PASSWORD', SECOPS_EXIT_USAGE );
	}
	$method = strtoupper( (string) secops_opt( $options, 'method', '' ) );
	if ( '' === $method ) {
		$method = ( $write || $dry_run ) ? 'DELETE' : 'GET';
	}
	if ( ! in_array( $method, array( 'GET', 'DELETE', 'POST' ), true ) ) {
		secops_json_error( '--method must be GET, DELETE or POST', SECOPS_EXIT_USAGE );
	}
} else {
	$method = 'MCP';
}

$started = microtime( true );
$response = 'rest' === $transport
	? secops_rest_call( $url, $ability, $input, $method, $user, $password, $timeout, $verify_tls )
	: secops_mcp_call( $url, $ability, $input, $user, $password, $timeout, $verify_tls, $protocol );
$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

$decoded = 'mcp' === $transport ? secops_decode_mcp_body( (string) $response['body'] ) : secops_decode_body( (string) $response['body'] );
$http_ok = $response['status'] >= 200 && $response['status'] < 300;
$rpc_error = is_array( $decoded ) && ( isset( $decoded['error'] ) || ( isset( $decoded['result']['isError'] ) && $decoded['result']['isError'] ) );
$ok = $http_ok && '' === (string) $response['error'] && ! $rpc_error;
$error_message = (string) $response['error'];
if ( '' === $error_message && is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
	$error_message = (string) $decoded['error']['message'];
}
$envelope = array(
	'ok'          => $ok,
	'transport'   => $transport,
	'ability'     => $ability,
	'method'      => $method,
	'dry_run'     => $dry_run,
	'write'       => $write,
	'http_status' => $response['status'],
	'duration_ms' => $duration,
	'request'     => array(
		'input' => secops_redact( $input, true ),
	),
	'response'    => $decoded,
	'error'       => $error_message,
);

if ( '' !== $log_path ) {
	secops_append_log(
		$log_path,
		array(
			'ts'        => secops_now(),
			'transport' => $transport,
			'ability'   => $ability,
			'method'    => $method,
			'input'     => secops_redact( $input, true ),
			'status'    => $response['status'],
			'ok'        => $ok,
			'duration'  => $duration,
			'error'     => $error_message,
		)
	);
}

if ( secops_flag( $options, 'text' ) ) {
	echo is_string( $decoded ) ? $decoded : secops_json_encode( $decoded );
	echo "\n";
	exit( $ok ? SECOPS_EXIT_OK : SECOPS_EXIT_PARTIAL );
}

secops_json_response( $envelope, $ok ? SECOPS_EXIT_OK : SECOPS_EXIT_PARTIAL );
