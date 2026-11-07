<?php
/**
 * Redaction helpers for logs, reports and ability output.
 */

declare(strict_types=1);

/**
 * Whether a key looks sensitive.
 */
function secops_sensitive_key( string $key ): bool {
	$key = strtolower( $key );
	$needles = array(
		'password',
		'passwd',
		'secret',
		'token',
		'api_key',
		'apikey',
		'authorization',
		'cookie',
		'session',
		'nonce',
		'salt',
		'private_key',
		'application_password',
		'auth_token',
		'auth_key',
	);
	foreach ( $needles as $needle ) {
		if ( strpos( $key, $needle ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Mask an IPv4 or IPv6 address.
 */
function secops_mask_ip( string $ip ): string {
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$parts = explode( '.', $ip );
		return $parts[0] . '.' . $parts[1] . '.*.*';
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$parts = explode( ':', $ip );
		while ( count( $parts ) < 8 ) {
			$parts[] = '*';
		}
		return $parts[0] . ':' . $parts[1] . ':' . implode( ':', array_fill( 2, 6, '*' ) );
	}
	return '[IP]';
}

/**
 * Mask an email address.
 */
function secops_mask_email( string $email ): string {
	$at = strpos( $email, '@' );
	if ( false === $at || $at < 1 ) {
		return '[EMAIL]';
	}
	$local  = substr( $email, 0, $at );
	$domain = substr( $email, $at + 1 );
	$first  = substr( $local, 0, 1 );
	return $first . '***@' . $domain;
}

/**
 * Mask a login name.
 */
function secops_mask_login( string $login ): string {
	if ( strlen( $login ) <= 2 ) {
		return '***';
	}
	return substr( $login, 0, 1 ) . '***' . substr( $login, -1 );
}

/**
 * Mask inline secrets and identifiers in text.
 */
function secops_mask_text( string $text, bool $mask_pii = true ): string {
	$text = preg_replace( '/(Bearer\s+)[A-Za-z0-9._\-]+/i', '$1[REDACTED]', $text );
	$text = preg_replace( '/(Basic\s+)[A-Za-z0-9+\/=._\-]+/i', '$1[REDACTED]', $text );
	$text = preg_replace( '/((?:password|passwd|token|secret|api_key|apikey)\s*[=:]\s*)[^\s&,;]+/i', '$1[REDACTED]', $text );
	if ( $mask_pii ) {
		$text = preg_replace_callback(
			'/\b\d{1,3}(?:\.\d{1,3}){3}\b/',
			static function ( array $match ): string {
				return secops_mask_ip( $match[0] );
			},
			$text
		);
		$text = preg_replace_callback(
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
			static function ( array $match ): string {
				return secops_mask_email( $match[0] );
			},
			$text
		);
	}
	return is_string( $text ) ? $text : '';
}

/**
 * Recursively redact an array.
 *
 * @param mixed $value Value to redact.
 * @return mixed
 */
function secops_redact( $value, bool $mask_pii = false, string $key = '' ) {
	if ( '' !== $key && secops_sensitive_key( $key ) ) {
		return '[REDACTED]';
	}
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $child_key => $child_value ) {
			$out[ $child_key ] = secops_redact( $child_value, $mask_pii, (string) $child_key );
		}
		return $out;
	}
	if ( is_string( $value ) ) {
		return secops_mask_text( $value, $mask_pii );
	}
	return $value;
}

/**
 * Mask a user record for a report.
 *
 * @param array<string,mixed> $user User data.
 * @return array<string,mixed>
 */
function secops_mask_user( array $user ): array {
	if ( isset( $user['user_login'] ) && is_string( $user['user_login'] ) ) {
		$user['user_login'] = secops_mask_login( $user['user_login'] );
	}
	if ( isset( $user['user_email'] ) && is_string( $user['user_email'] ) ) {
		$user['user_email'] = secops_mask_email( $user['user_email'] );
	}
	return $user;
}
