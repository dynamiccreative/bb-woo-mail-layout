<?php
/**
 * Produits mis en avant automatiques : ventes croisées, produits apparentés, complément manuel.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Plugin;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Email\LayoutRenderer
 */
class FeaturedAutoTest extends TestCase {

	/**
	 * Produit publié.
	 *
	 * @param string $name        Nom.
	 * @param int[]  $categories  Catégories.
	 * @param int[]  $cross_sells Ventes croisées.
	 */
	private function product( string $name, array $categories = array(), array $cross_sells = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '20' );
		$product->set_status( 'publish' );
		$product->set_category_ids( $categories );
		$product->set_cross_sell_ids( $cross_sells );
		return $product->save();
	}

	/**
	 * Commande contenant les produits donnés.
	 *
	 * @param int[] $product_ids Produits.
	 */
	private function order_with( array $product_ids ): \WC_Order {
		$order = wc_create_order();
		foreach ( $product_ids as $id ) {
			$order->add_product( wc_get_product( $id ), 1 );
		}
		$order->set_billing_first_name( 'Camille' );
		$order->set_billing_email( 'camille@example.org' );
		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->save();
		return $order;
	}

	/**
	 * Noms des produits du bloc, dans l'ordre.
	 *
	 * @param string $email_id E-mail.
	 * @param int    $order_id Commande.
	 * @return string[]
	 */
	private function featured( string $email_id, int $order_id ): array {
		$simulator = new Simulator( Plugin::instance()->registry() );
		$html      = $simulator->render( $simulator->prepare( $email_id, $order_id ) );
		preg_match_all( '/class="bb-featured-name"[^>]*><a [^>]*>([^<]+)<\/a>/', $html, $m );
		return array_map( 'html_entity_decode', $m[1] );
	}

	public function test_source_is_sanitized(): void {
		$this->assertSame( 'related', Options::sanitize_field( 'featured_source', 'related' ) );
		$this->assertSame( 'manual', Options::sanitize_field( 'featured_source', 'autre' ) );
	}

	public function test_manual_mode_is_unchanged(): void {
		$cross  = $this->product( 'Tapenade' );
		$olive  = $this->product( 'Huile', array(), array( $cross ) );
		$manual = $this->product( 'Savon' );

		Options::replace(
			array(
				'show_featured'     => 'yes',
				'featured_products' => array( $manual ),
			)
		);
		$this->assertSame( array( 'Savon' ), $this->featured( 'customer_processing_order', $this->order_with( array( $olive ) )->get_id() ) );
	}

	public function test_cross_sells_first_then_manual_and_ordered_products_excluded(): void {
		$cross_a = $this->product( 'Tapenade' );
		$cross_b = $this->product( 'Pesto' );
		$olive   = $this->product( 'Huile', array(), array( $cross_a, $cross_b ) );
		$vinegar = $this->product( 'Vinaigre', array(), array( $olive ) ); // Vente croisée déjà commandée : exclue.
		$manual  = $this->product( 'Savon' );

		Options::replace(
			array(
				'show_featured'     => 'yes',
				'featured_source'   => 'cross_sells',
				'featured_products' => array( $manual, $olive ),
			)
		);
		$order = $this->order_with( array( $olive, $vinegar ) );

		$this->assertSame( array( 'Tapenade', 'Pesto', 'Savon' ), $this->featured( 'customer_processing_order', $order->get_id() ) );
		$this->assertSame( array(), $this->featured( 'new_order', $order->get_id() ), 'Jamais dans les e-mails admin.' );
	}

	public function test_related_products_from_same_category(): void {
		$category = wp_insert_term( 'Épicerie', 'product_cat' );
		$cat_id   = (int) $category['term_id'];
		$olive    = $this->product( 'Huile', array( $cat_id ) );
		$this->product( 'Tapenade', array( $cat_id ) );
		$this->product( 'Sans rapport' );
		delete_transient( 'wc_related_' . $olive );

		Options::replace(
			array(
				'show_featured'   => 'yes',
				'featured_source' => 'related',
			)
		);
		$this->assertSame( array( 'Tapenade' ), $this->featured( 'customer_processing_order', $this->order_with( array( $olive ) )->get_id() ) );
	}

	public function test_emails_without_order_use_manual_products(): void {
		$manual = $this->product( 'Savon' );
		Options::replace(
			array(
				'show_featured'     => 'yes',
				'featured_source'   => 'cross_sells',
				'featured_products' => array( $manual ),
			)
		);
		$this->assertSame( array( 'Savon' ), $this->featured( 'customer_new_account', 0 ) );
	}
}
