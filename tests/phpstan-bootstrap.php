<?php
/**
 * Constantes du plugin pour l'analyse statique.
 *
 * @package BB\WooMailLayout
 */

define( 'BB_WML_VERSION', '0.0.0' );
define( 'BB_WML_FILE', dirname( __DIR__ ) . '/bb-woo-mail-layout.php' );
define( 'BB_WML_DIR', dirname( __DIR__ ) . '/' );
define( 'BB_WML_URL', 'https://example.test/wp-content/plugins/bb-woo-mail-layout/' );
define( 'BB_WML_OPTION', 'bb_woo_mail_layout' );

if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '11.1.2' );
}
