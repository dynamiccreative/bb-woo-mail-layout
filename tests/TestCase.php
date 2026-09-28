<?php
/**
 * Base des tests : fabrique de commandes.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Settings\Options;

/**
 * Cas de test commun.
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Réglages par défaut avant chaque test.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( BB_WML_OPTION, Options::defaults() );
	}

	/**
	 * Commande client complète (adresses, $lines lignes, livraison).
	 *
	 * @param int    $lines  Nombre de lignes.
	 * @param string $status Statut.
	 */
	protected function create_order( int $lines = 2, string $status = 'processing' ): \WC_Order {
		$order = wc_create_order();

		for ( $i = 1; $i <= $lines; $i++ ) {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Huile d’olive vierge extra n° ' . $i );
			$product->set_regular_price( (string) ( 10 + $i ) );
			$product->set_sku( 'HUILE-' . uniqid() . '-' . $i );
			$product->save();
			$order->add_product( $product, 1 + ( $i % 3 ) );
		}

		$address = array(
			'first_name' => 'Camille',
			'last_name'  => 'Martin',
			'company'    => '',
			'address_1'  => '12 chemin des Oliviers',
			'city'       => 'Nyons',
			'postcode'   => '26110',
			'country'    => 'FR',
			'email'      => 'camille.martin@example.org',
			'phone'      => '0475000000',
		);
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		$shipping = new \WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Colissimo' );
		$shipping->set_total( '6.90' );
		$order->add_item( $shipping );

		$order->set_payment_method_title( 'Carte bancaire' );
		$order->set_customer_note( 'Merci de livrer après 17 h.' );
		$order->calculate_totals();
		$order->set_status( $status );
		$order->save();

		return $order;
	}
}
