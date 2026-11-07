#!/usr/bin/env php
<?php
/**
 * Read-only database audit.
 *
 * Run with:
 *   wp eval-file scripts/db-audit.php
 *
 * SELECT only. No UPDATE, DELETE, DROP, TRUNCATE or ALTER.
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
		'max-rows'    => true,
	)
);
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage: wp eval-file scripts/db-audit.php [--include-pii] [--max-rows=100]\n" .
		"\n" .
		"Flags (also available after the script path):\n" .
		"  --include-pii    include unmasked login and email\n" .
		"  --max-rows=100   limit rows per query\n" .
		"\n" .
		"Environment:\n" .
		"  SECOPS_INCLUDE_PII=1  include unmasked login and email\n" .
		"  SECOPS_MAX_ROWS=100   limit rows per query\n" .
		"\n" .
		"Output: JSON database audit. Read-only.\n"
	);
}

secops_require_wp();

global $wpdb;

$include_pii = secops_flag( $options, 'include-pii' ) || '1' === (string) getenv( 'SECOPS_INCLUDE_PII' );
$max_rows = (int) secops_opt( $options, 'max-rows', (string) getenv( 'SECOPS_MAX_ROWS' ) ?: '100' );
if ( $max_rows < 1 ) {
	$max_rows = 100;
}
if ( $max_rows > 1000 ) {
	$max_rows = 1000;
}

$high_signal = array( 'eval(', 'base64_decode', 'gzinflate', 'str_rot13' );
$low_signal  = array( '<script', 'iframe', 'display:none', 'document.write', 'unescape(', 'window.location' );
$patterns    = array_merge( $high_signal, $low_signal );
$like_clauses = array();
foreach ( $patterns as $pattern ) {
	$like_clauses[] = 'option_value LIKE ' . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $pattern ) . '%' );
}

$tables = array();
$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
$tables_checked = is_array( $rows );
if ( is_array( $rows ) ) {
	foreach ( $rows as $row ) {
		$tables[] = array(
			'name'          => isset( $row['Name'] ) ? $row['Name'] : '',
			'engine'        => isset( $row['Engine'] ) ? $row['Engine'] : '',
			'rows'          => isset( $row['Rows'] ) ? (int) $row['Rows'] : 0,
			'data_length'   => isset( $row['Data_length'] ) ? (int) $row['Data_length'] : 0,
			'index_length'  => isset( $row['Index_length'] ) ? (int) $row['Index_length'] : 0,
			'data_free'     => isset( $row['Data_free'] ) ? (int) $row['Data_free'] : 0,
			'data_mb'       => isset( $row['Data_length'] ) ? round( (int) $row['Data_length'] / 1048576, 2 ) : 0,
			'index_mb'      => isset( $row['Index_length'] ) ? round( (int) $row['Index_length'] / 1048576, 2 ) : 0,
			'overhead_mb'   => isset( $row['Data_free'] ) ? round( (int) $row['Data_free'] / 1048576, 2 ) : 0,
		);
	}
}

$autoload = $wpdb->get_results(
	"SELECT option_name, autoload, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY size DESC LIMIT " . (int) $max_rows,
	ARRAY_A
);

$suspicious_options = array();
if ( ! empty( $like_clauses ) ) {
	$sql = "SELECT option_name, autoload, LENGTH(option_value) AS size, option_value FROM {$wpdb->options} WHERE (" . implode( ' OR ', $like_clauses ) . ') LIMIT ' . (int) $max_rows;
	$found = $wpdb->get_results( $sql, ARRAY_A );
	if ( is_array( $found ) ) {
		foreach ( $found as $row ) {
			$row_signals = array();
			foreach ( $patterns as $pattern ) {
				if ( false !== stripos( (string) $row['option_value'], $pattern ) ) {
					$row_signals[] = $pattern;
				}
			}
			$high = array_intersect( $row_signals, $high_signal );
			$suspicious_options[] = array(
				'option_name' => $row['option_name'],
				'autoload'    => $row['autoload'],
				'size'        => (int) $row['size'],
				'signal'      => empty( $high ) ? 'low' : 'high',
				'matches'     => array_values( $row_signals ),
				'excerpt'     => secops_truncate( secops_mask_text( (string) $row['option_value'] ), 240 ),
			);
		}
	}
}

$transient_like = $wpdb->esc_like( '_transient_' ) . '%';
$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_' ) . '%';
$transient_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like ) );
$expired_transients = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $transient_timeout_like, time() ) );

$users = array();
$user_rows = get_users( array( 'fields' => 'all' ) );
foreach ( $user_rows as $user ) {
	$users[] = array(
		'id'    => (int) $user->ID,
		'login' => $include_pii ? $user->user_login : secops_mask_login( (string) $user->user_login ),
		'email' => $include_pii ? $user->user_email : secops_mask_email( (string) $user->user_email ),
		'roles' => (array) $user->roles,
	);
}
$counts = count_users();

$post_counts = $wpdb->get_results(
	"SELECT post_type, post_status, COUNT(*) AS count FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY count DESC LIMIT " . (int) $max_rows,
	ARRAY_A
);

$post_like = array();
foreach ( $patterns as $pattern ) {
	$post_like[] = 'post_content LIKE ' . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $pattern ) . '%' );
}
$suspicious_posts = array();
if ( ! empty( $post_like ) ) {
	$sql = "SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts} WHERE (" . implode( ' OR ', $post_like ) . ') LIMIT ' . (int) $max_rows;
	$found = $wpdb->get_results( $sql, ARRAY_A );
	if ( is_array( $found ) ) {
		foreach ( $found as $row ) {
			$row_signals = array();
			foreach ( $patterns as $pattern ) {
				if ( false !== stripos( (string) $row['post_content'], $pattern ) ) {
					$row_signals[] = $pattern;
				}
			}
			$high = array_intersect( $row_signals, $high_signal );
			$suspicious_posts[] = array(
				'id'        => (int) $row['ID'],
				'type'      => $row['post_type'],
				'status'    => $row['post_status'],
				'signal'    => empty( $high ) ? 'low' : 'high',
				'matches'   => array_values( $row_signals ),
				'title'     => secops_mask_text( (string) $row['post_title'] ),
				'excerpt'   => secops_truncate( secops_mask_text( (string) $row['post_content'] ), 240 ),
			);
		}
	}
}

$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
$cron_jobs = array();
if ( is_array( $cron ) ) {
	foreach ( $cron as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook => $entries ) {
			$cron_jobs[] = array(
				'hook'     => $hook,
				'next_run' => gmdate( 'Y-m-d\TH:i:s\Z', (int) $timestamp ),
				'count'    => is_array( $entries ) ? count( $entries ) : 0,
				'args'     => 'redacted; use get-cron-jobs or cron-audit.php',
			);
		}
	}
}

$comment_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
$recent_comments = $wpdb->get_results(
	"SELECT comment_ID, comment_author, comment_author_email, comment_author_url, comment_content, comment_approved FROM {$wpdb->comments} ORDER BY comment_ID DESC LIMIT " . (int) $max_rows,
	ARRAY_A
);
$comment_items = array();
if ( is_array( $recent_comments ) ) {
	foreach ( $recent_comments as $row ) {
		$comment_items[] = array(
			'id'      => (int) $row['comment_ID'],
			'author'  => $include_pii ? $row['comment_author'] : secops_mask_login( (string) $row['comment_author'] ),
			'email'   => $include_pii ? $row['comment_author_email'] : secops_mask_email( (string) $row['comment_author_email'] ),
			'url'     => (string) $row['comment_author_url'],
			'approved'=> (string) $row['comment_approved'],
			'excerpt' => secops_truncate( secops_mask_text( (string) $row['comment_content'] ), 160 ),
		);
	}
}

$data = array(
	'database' => array(
		'version'        => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '',
		'prefix'         => $wpdb->prefix,
		'tables_checked' => $tables_checked,
		'tables'         => $tables,
		'size'           => array(
			'data_mb'  => round( array_sum( array_column( $tables, 'data_length' ) ) / 1048576, 2 ),
			'index_mb' => round( array_sum( array_column( $tables, 'index_length' ) ) / 1048576, 2 ),
			'free_mb'  => round( array_sum( array_column( $tables, 'data_free' ) ) / 1048576, 2 ),
		),
	),
	'options' => array(
		'autoload_top'        => $autoload,
		'suspicious'          => $suspicious_options,
		'transients'          => $transient_count,
		'expired_transients'  => $expired_transients,
		'note'                => 'A match is a lead, not a verdict. Review autoload, owner and context.',
	),
	'users' => array(
		'total' => isset( $counts['total_users'] ) ? (int) $counts['total_users'] : count( $users ),
		'roles' => isset( $counts['avail_roles'] ) ? $counts['avail_roles'] : array(),
		'items' => $users,
	),
	'posts' => array(
		'counts'     => $post_counts,
		'suspicious' => $suspicious_posts,
	),
	'comments' => array(
		'total'  => $comment_count,
		'recent' => $comment_items,
	),
	'cron' => array(
		'jobs' => $cron_jobs,
		'args' => 'redacted; use get-cron-jobs or cron-audit.php',
	),
	'limits' => array(
		'max_rows' => $max_rows,
	),
	'not_checked' => array_filter(
		array(
			'2fa'    => 'no 2FA source in this script; use the plugin summary, user meta or the admin UI',
			'tables' => $tables_checked ? '' : 'SHOW TABLE STATUS failed or was denied; use the host panel',
		)
	),
);

secops_emit( $data );
