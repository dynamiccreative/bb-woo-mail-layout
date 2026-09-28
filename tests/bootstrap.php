<?php
/**
 * Bootstrap PHPUnit (wp-env : `npm run test` ou `npx wp-env run tests-cli --env-cwd=wp-content/plugins/bb-woo-mail-layout vendor/bin/phpunit`).
 *
 * @package BB\WooMailLayout
 */

$bb_root = dirname( __DIR__ );

require_once $bb_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$bb_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $bb_tests_dir ) {
	$bb_tests_dir = $bb_root . '/vendor/wp-phpunit/wp-phpunit';
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require_once $bb_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $bb_root ): void {
		require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require_once $bb_root . '/bb-woo-mail-layout.php';
	}
);

tests_add_filter(
	'setup_theme',
	static function (): void {
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
		BB\WooMailLayout\Plugin::activate();
	}
);

require $bb_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
