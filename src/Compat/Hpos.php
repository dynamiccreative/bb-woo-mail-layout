<?php
/**
 * Déclaration de compatibilité HPOS (tables de commandes personnalisées).
 *
 * Le plugin n'accède aux commandes que via l'API CRUD (wc_get_orders, WC_Order) : aucune requête SQL directe.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibilité HPOS.
 */
final class Hpos {

	/**
	 * Hook, à poser avant le chargement de WooCommerce.
	 */
	public static function register(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare' ) );
	}

	/**
	 * Déclare la compatibilité.
	 */
	public static function declare(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BB_WML_FILE, true );
		}
	}
}
