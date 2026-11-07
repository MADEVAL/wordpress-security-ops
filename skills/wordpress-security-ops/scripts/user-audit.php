#!/usr/bin/env php
<?php
/**
 * Read-only user, role and session audit.
 *
 * Run with:
 *   wp eval-file scripts/user-audit.php
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
		'help'        => false,
		'json'        => false,
		'include-pii' => false,
	)
);
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage: wp eval-file scripts/user-audit.php [--include-pii]\n" .
		"\n" .
		"Flags (also available after the script path):\n" .
		"  --include-pii  include unmasked login and email\n" .
		"\n" .
		"Environment:\n" .
		"  SECOPS_INCLUDE_PII=1  include unmasked login and email\n" .
		"\n" .
		"Output: JSON users, roles, sessions and 2FA status.\n"
	);
}

secops_require_wp();

$include_pii = secops_flag( $options, 'include-pii' ) || '1' === (string) getenv( 'SECOPS_INCLUDE_PII' );

$users = get_users( array( 'fields' => 'all' ) );
$items = array();
$admins = array();
$with_manage_options = array();
$stale = array();
$sessions_total = 0;
$now = time();

foreach ( $users as $user ) {
	$id = (int) $user->ID;
	$roles = (array) $user->roles;
	$caps = (array) $user->allcaps;
	$is_admin = in_array( 'administrator', $roles, true ) || ! empty( $caps['manage_options'] );
	if ( $is_admin ) {
		$admins[] = $id;
	}
	if ( ! empty( $caps['manage_options'] ) ) {
		$with_manage_options[] = $id;
	}

	$session_tokens = get_user_meta( $id, 'session_tokens', true );
	$session_count = is_array( $session_tokens ) ? count( $session_tokens ) : 0;
	$sessions_total += $session_count;

	$last_login = get_user_meta( $id, 'last_login', true );
	if ( '' === $last_login ) {
		$last_login = get_user_meta( $id, 'last_login_time', true );
	}
	$last_login_ts = is_numeric( $last_login ) ? (int) $last_login : 0;
	$stale_days = $last_login_ts > 0 ? (int) floor( ( $now - $last_login_ts ) / 86400 ) : null;
	if ( null !== $stale_days && $stale_days >= 90 ) {
		$stale[] = array( 'id' => $id, 'stale_days' => $stale_days );
	}

	$row = array(
		'id'            => $id,
		'login'         => $include_pii ? $user->user_login : secops_mask_login( (string) $user->user_login ),
		'email'         => $include_pii ? $user->user_email : secops_mask_email( (string) $user->user_email ),
		'roles'         => $roles,
		'manage_options'=> ! empty( $caps['manage_options'] ),
		'sessions'      => $session_count,
		'last_login'    => $last_login_ts > 0 ? gmdate( 'Y-m-d\TH:i:s\Z', $last_login_ts ) : 'not_checked',
		'stale_days'    => $stale_days,
		'2fa'           => 'not_checked; no 2FA source in this script; check the security plugin, user meta or admin UI',
		'user_registered' => (string) $user->user_registered,
	);
	$items[] = $row;
}

$counts = count_users();
$data = array(
	'summary' => array(
		'total_users'        => isset( $counts['total_users'] ) ? (int) $counts['total_users'] : count( $items ),
		'roles'              => isset( $counts['avail_roles'] ) ? $counts['avail_roles'] : array(),
		'admins'             => count( $admins ),
		'manage_options'     => count( $with_manage_options ),
		'sessions_total'     => $sessions_total,
		'stale_90_days'      => count( $stale ),
	),
	'admin_ids'  => $admins,
	'stale'      => $stale,
	'users'      => $items,
	'not_checked' => array(
		'2fa' => 'no 2FA source in this script; check the security plugin, user meta or the admin UI',
	),
);

secops_emit( $data );
