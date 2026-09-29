<?php
/**
 * Plugin Name:          BB Woo Mail Layout
 * Plugin URI:           https://github.com/dynamiccreative/bb-woo-mail-layout
 * Description:          Mise en forme brandée et 100 % française des e-mails transactionnels WooCommerce (layout codé, réglages simples).
 * Version:              1.2.0
 * Requires at least:    6.4
 * Tested up to:         7.1
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               bleuebuzz — Mathieu Paillet
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          bb-woo-mail-layout
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 * Update URI:           https://github.com/dynamiccreative/bb-woo-mail-layout
 *
 * @package BB\WooMailLayout
 */

defined( 'ABSPATH' ) || exit;

define( 'BB_WML_VERSION', '1.2.0' );
define( 'BB_WML_FILE', __FILE__ );
define( 'BB_WML_DIR', plugin_dir_path( __FILE__ ) );
define( 'BB_WML_URL', plugin_dir_url( __FILE__ ) );
define( 'BB_WML_OPTION', 'bb_woo_mail_layout' );

// Autoload : Composer si présent (dev), sinon autoloader PSR-4 minimal (zéro dépendance runtime).
if ( is_readable( BB_WML_DIR . 'vendor/autoload.php' ) ) {
	require_once BB_WML_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'BB\\WooMailLayout\\';
			if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = BB_WML_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

// Doit être posé avant le chargement de WooCommerce.
BB\WooMailLayout\Compat\Hpos::register();
BB\WooMailLayout\Compat\EmailImprovements::register();

register_activation_hook( __FILE__, array( BB\WooMailLayout\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( BB\WooMailLayout\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( BB\WooMailLayout\Plugin::class, 'boot' ), 20 );
