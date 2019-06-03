<?php
/**
 * ElasticPress test bootstrap
 *
 * @group 3.0
 */

namespace ElasticPressTest;

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * Make sure we only test on 1 shard because any more will lead to inconsitent results
 *
 * @since  3.0
 */
function test_shard_number( $mapping ) {
	$mapping['settings']['index'] = array(
		'number_of_shards' => 1,
	);

	return $mapping;
}

/**
 * Bootstrap EP plugin
 *
 * @since 3.0
 */
function load_plugin() {
	global $wp_version;

	$host = getenv( 'EP_HOST' );

	if ( empty( $host ) ) {
		$host = 'http://elasticpress.test/__elasticsearch';
	}

	update_option( 'ep_host', $host );
	update_site_option( 'ep_host', $host );

	define( 'EP_IS_NETWORK', true );
	define( 'WP_NETWORK_ADMIN', true );

	include_once __DIR__ . '/../vendor/woocommerce/woocommerce.php';
	require_once __DIR__ . '/../elasticpress.php';

	add_filter( 'ep_config_mapping', __NAMESPACE__ . '\test_shard_number' );

	$tries = 5;
	$sleep = 3;

	do {
		$response = wp_remote_get( $host );
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Looks good!
			break;
		} else {
			printf( "\nInvalid response from ES, sleeping %d seconds and trying again...\n", intval( $sleep ) );
			sleep( $sleep );
		}
	} while ( --$tries );

	if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
		exit( 'Could not connect to ElasticPress server.' );
	}

	require_once __DIR__ . '/includes/functions.php';

	echo 'WordPress version ' . $wp_version . "\n";
}

tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\load_plugin' );

/**
 * Setup WooCommerce for tests
 *
 * @since  3.0
 */
function setup_wc() {
	if ( class_exists( '\WC_Install' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', true );

		update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
		include_once __DIR__ . '/../vendor/woocommerce/uninstall.php';

		\WC_Install::install();

		$GLOBALS['wp_roles'] = new \WP_Roles();

		echo 'Installing WooCommerce version ' . WC()->version . ' ...' . PHP_EOL;
	}
}

tests_add_filter( 'setup_theme', __NAMESPACE__ . '\setup_wc' );

require_once $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/classes/BaseTestCase.php';
require_once __DIR__ . '/includes/classes/FeatureTest.php';
