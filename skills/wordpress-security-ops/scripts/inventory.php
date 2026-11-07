#!/usr/bin/env php
<?php
/**
 * Read-only WordPress inventory.
 *
 * Run with:
 *   wp eval-file scripts/inventory.php
 *
 * Flags are passed after the script path in WP-CLI context only when the
 * command supports them. Prefer environment variables for options.
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
		'max-files'   => true,
	)
);
if ( secops_flag( $options, 'help' ) ) {
	secops_usage(
		"Usage: wp eval-file scripts/inventory.php [--include-pii] [--max-files=5000]\n" .
		"\n" .
		"Flags (also available after the script path):\n" .
		"  --include-pii    include unmasked login and email\n" .
		"  --max-files=5000 limit the uploads scan\n" .
		"\n" .
		"Environment:\n" .
		"  SECOPS_INCLUDE_PII=1  include unmasked login and email\n" .
		"  SECOPS_MAX_FILES=5000  limit the uploads scan\n" .
		"\n" .
		"Output: JSON inventory without secrets.\n"
	);
}

secops_require_wp();

$include_pii = secops_flag( $options, 'include-pii' ) || '1' === (string) getenv( 'SECOPS_INCLUDE_PII' );
$max_files = (int) secops_opt( $options, 'max-files', (string) getenv( 'SECOPS_MAX_FILES' ) ?: '5000' );
if ( $max_files < 1 ) {
	$max_files = 5000;
}

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

global $wpdb;

$active_plugins = (array) get_option( 'active_plugins', array() );
$network_plugins = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();

$plugins = array();
foreach ( get_plugins() as $file => $data ) {
	$active = in_array( $file, $active_plugins, true );
	$network_active = isset( $network_plugins[ $file ] );
	$plugins[] = array(
		'file'           => $file,
		'name'           => isset( $data['Name'] ) ? $data['Name'] : '',
		'version'        => isset( $data['Version'] ) ? $data['Version'] : '',
		'author'         => isset( $data['Author'] ) ? $data['Author'] : '',
		'active'         => $active,
		'network_active' => $network_active,
		'update_uri'     => isset( $data['UpdateURI'] ) ? $data['UpdateURI'] : '',
	);
}

$themes = array();
$active_theme = wp_get_theme();
foreach ( wp_get_themes() as $slug => $theme ) {
	$themes[] = array(
		'slug'    => $slug,
		'name'    => $theme->get( 'Name' ),
		'version' => $theme->get( 'Version' ),
		'parent'  => $theme->parent() ? $theme->parent()->get( 'Name' ) : '',
		'active'  => ( $slug === $active_theme->get_stylesheet() ),
	);
}

$user_counts = count_users();
$users = array();
foreach ( get_users( array( 'fields' => 'all' ) ) as $user ) {
	$row = array(
		'id'     => (int) $user->ID,
		'login'  => $include_pii ? $user->user_login : secops_mask_login( (string) $user->user_login ),
		'email'  => $include_pii ? $user->user_email : secops_mask_email( (string) $user->user_email ),
		'roles'  => (array) $user->roles,
	);
	$users[] = $row;
}

$mu_plugins = array();
if ( defined( 'WPMU_PLUGIN_DIR' ) && is_dir( WPMU_PLUGIN_DIR ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( WPMU_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$mu_plugins[] = str_replace( WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR, '', $file->getPathname() );
		}
	}
}

$dropins = array();
foreach ( array( 'advanced-cache.php', 'object-cache.php', 'db.php', 'sunrise.php', 'maintenance.php' ) as $dropin ) {
	$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dropin;
	if ( file_exists( $path ) ) {
		$dropins[] = array(
			'file'   => $dropin,
			'size'   => filesize( $path ),
			'mtime'  => gmdate( 'Y-m-d\TH:i:s\Z', (int) filemtime( $path ) ),
		);
	}
}

$uploads_php = array();
$uploads = wp_upload_dir();
$uploads_dir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
$scanned = 0;
$truncated = false;
if ( '' !== $uploads_dir && is_dir( $uploads_dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $uploads_dir, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$scanned++;
		if ( $scanned > $max_files ) {
			$truncated = true;
			break;
		}
		$ext = strtolower( $file->getExtension() );
		if ( in_array( $ext, array( 'php', 'phtml', 'php5', 'php7', 'phar' ), true ) ) {
			$uploads_php[] = array(
				'path'  => str_replace( $uploads_dir . DIRECTORY_SEPARATOR, '', $file->getPathname() ),
				'size'  => $file->getSize(),
				'mtime' => gmdate( 'Y-m-d\TH:i:s\Z', (int) $file->getMTime() ),
			);
		}
	}
}

$wp_config = ABSPATH . 'wp-config.php';
if ( ! file_exists( $wp_config ) && file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
	$wp_config = dirname( ABSPATH ) . '/wp-config.php';
}
$wp_config_info = array(
	'path' => $wp_config,
	'exists' => file_exists( $wp_config ),
	'perms' => file_exists( $wp_config ) ? substr( decoct( fileperms( $wp_config ) & 0777 ), -4 ) : '',
);

$constants = array();
foreach ( array(
	'DISALLOW_FILE_EDIT',
	'DISALLOW_FILE_MODS',
	'WP_DEBUG',
	'WP_DEBUG_DISPLAY',
	'WP_DEBUG_LOG',
	'DISABLE_WP_CRON',
	'FORCE_SSL_ADMIN',
	'WP_AUTO_UPDATE_CORE',
	'AUTOMATIC_UPDATER_DISABLED',
	'MULTISITE',
	'WP_ENVIRONMENT_TYPE',
) as $constant ) {
	$constants[ $constant ] = defined( $constant ) ? constant( $constant ) : null;
}

$data = array(
	'site' => array(
		'url'          => get_option( 'siteurl' ),
		'home'         => get_option( 'home' ),
		'is_multisite' => is_multisite(),
		'is_ssl'       => is_ssl(),
		'locale'       => get_locale(),
		'wp_config'    => $wp_config_info,
	),
	'core' => array(
		'wp_version'  => get_bloginfo( 'version' ),
		'php_version' => PHP_VERSION,
		'db_version'  => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '',
		'is_wp_cli'   => secops_has_wp_cli(),
	),
	'constants'     => $constants,
	'plugins'       => $plugins,
	'themes'        => $themes,
	'users'         => array(
		'total'       => isset( $user_counts['total_users'] ) ? (int) $user_counts['total_users'] : count( $users ),
		'roles'       => isset( $user_counts['avail_roles'] ) ? $user_counts['avail_roles'] : array(),
		'items'       => $users,
		'2fa'         => 'not_checked; no 2FA source in this script; check the security plugin, user meta or admin UI',
	),
	'mu_plugins'    => $mu_plugins,
	'dropins'       => $dropins,
	'uploads'       => array(
		'php_files' => $uploads_php,
		'scanned'   => $scanned,
		'truncated' => $truncated,
		'max_files' => $max_files,
	),
	'limits' => array(
		'max_files' => $max_files,
	),
);

secops_emit( $data );
