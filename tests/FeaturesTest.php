<?php
/**
 * 1.1.0 : layout E-commerce, produits mis en avant, placeholders, CSS personnalisé.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Plugin;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Email\LayoutRenderer
 * @covers \BB\WooMailLayout\Email\Placeholders
 */
class FeaturesTest extends TestCase {

	/**
	 * Rendu d'un e-mail pour une commande.
	 *
	 * @param string    $email_id E-mail.
	 * @param \WC_Order $order    Commande.
	 */
	private function render( string $email_id, \WC_Order $order ): string {
		$simulator = new Simulator( Plugin::instance()->registry() );
		return $simulator->render( $simulator->prepare( $email_id, $order->get_id() ) );
	}

	/**
	 * Produit publié, avec prix promo optionnel.
	 *
	 * @param string $name Nom.
	 * @param string $sale Prix promo (vide = aucun).
	 */
	private function product( string $name, string $sale = '' ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '20' );
		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}
		$product->set_status( 'publish' );
		return $product->save();
	}

	public function test_ids_and_css_sanitizing(): void {
		$this->assertSame( array( 12, 40, 50 ), Options::sanitize_field( 'featured_products', '12, x, 12, 40, 50, 60' ) );

		$css = Options::sanitize_field( 'custom_css', '.a{color:red}</style><script>alert(1)</script>@import url(x.css);.b{width:expression(1)}' );
		$this->assertStringContainsString( '.a{color:red}', $css );
		$this->assertStringNotContainsString( 'script', $css );
		$this->assertStringNotContainsString( '@import', $css );
		$this->assertStringNotContainsString( 'expression(', $css );
		$this->assertStringNotContainsString( '<', $css );
	}

	public function test_ecommerce_layout_shows_product_images_only_there(): void {
		$order = $this->create_order( 2 );

		Options::replace( array( 'layout' => 'ecommerce' ) );
		$this->assertMatchesRegularExpression( '/bb-order-table.*<img/s', $this->render( 'customer_processing_order', $order ) );

		Options::replace( array( 'layout' => 'classique' ) );
		$html = $this->render( 'customer_processing_order', $order );
		$this->assertDoesNotMatchRegularExpression( '/<table class="td bb-order-table.*?<img.*?<\/table>/s', $html );
	}

	public function test_layout_can_be_set_per_email(): void {
		$order = $this->create_order( 2 );

		Options::replace(
			array(
				'layout'        => 'classique',
				'email_layouts' => array( 'customer_processing_order' => 'ecommerce' ),
			)
		);
		$this->assertMatchesRegularExpression( '/bb-order-table.*<img/s', $this->render( 'customer_processing_order', $order ) );
		$this->assertDoesNotMatchRegularExpression( '/<table class="td bb-order-table.*?<img.*?<\/table>/s', $this->render( 'customer_completed_order', $order ) );

		// Retour au layout général : '' écrase l'ancienne valeur à la fusion ; un slug inconnu est refusé.
		$this->assertSame(
			array(
				'customer_processing_order' => '',
				'new_order'                 => '',
			),
			Options::sanitize_field(
				'email_layouts',
				array(
					'customer_processing_order' => '',
					'new_order'                 => '../evil',
				)
			)
		);
	}

	public function test_featured_products_block(): void {
		$ids    = array(
			$this->product( 'Tapenade noire' ),
			$this->product( 'Huile de noyaux', '15' ),
			$this->product( 'Savon lavande' ),
		);
		$hidden = $this->product( 'Produit brouillon' );
		wp_update_post(
			array(
				'ID'          => $hidden,
				'post_status' => 'draft',
			)
		);

		Options::replace(
			array(
				'show_featured'     => 'yes',
				'featured_title'    => 'Nos coups de cœur',
				'featured_products' => array_merge( array( $hidden ), $ids ),
			)
		);
		$order = $this->create_order();
		$html  = $this->render( 'customer_processing_order', $order );

		$this->assertStringContainsString( 'Nos coups de cœur', $html );
		$this->assertSame( 2, substr_count( $html, 'class="bb-featured-item"' ), 'Brouillon ignoré, 3 IDs maximum enregistrés.' );
		$this->assertStringNotContainsString( 'Produit brouillon', $html );
		$this->assertStringContainsString( 'Tapenade noire', $html );
		$this->assertStringContainsString( '<del', $html );
		$this->assertStringNotContainsString( 'screen-reader-text', $html );
		$this->assertStringNotContainsString( 'bb-featured-item', $this->render( 'new_order', $order ), 'Pas de mise en avant dans les e-mails admin.' );
	}

	public function test_new_placeholders(): void {
		$order = $this->create_order();
		$order->update_meta_data( '_numero_client', 'CL-7' );
		$order->save();

		Options::replace(
			array(
				'intros' => array(
					'customer_processing_order' => "{payment_method} / {shipping_method} / {order_meta:_numero_client} / {order_meta:inconnue}\n\n{billing_address}",
				),
			)
		);
		$html = $this->render( 'customer_processing_order', $order );

		$this->assertStringContainsString( 'Carte bancaire / Colissimo / CL-7 /', $html );
		$this->assertStringContainsString( '12 chemin des Oliviers', $html );
		$this->assertStringNotContainsString( '{order_meta', $html );
	}

	public function test_logo_size_uses_both_limits_without_upscaling(): void {
		$order = $this->create_order();
		$logo  = function ( int $side ): int {
			$id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
			update_post_meta( $id, '_wp_attached_file', "logo-$side.png" );
			wp_update_attachment_metadata(
				$id,
				array(
					'width'  => $side,
					'height' => $side,
					'file'   => "logo-$side.png",
				)
			);
			return $id;
		};
		$size  = function ( int $logo, int $max_w, int $max_h ) use ( $order ): string {
			Options::replace(
				array(
					'logo_url'        => 'https://example.org/wp-content/uploads/logo-' . $logo . '.png',
					'logo_id'         => $logo,
					'logo_max_width'  => $max_w,
					'logo_max_height' => $max_h,
				)
			);
			preg_match( '/class="bb-header".*?(<img[^>]*>)/s', $this->render( 'customer_processing_order', $order ), $m );
			preg_match( '/width="(\d+)" height="(\d+)"/', $m[1] ?? '', $wh );
			return ( $wh[1] ?? '?' ) . 'x' . ( $wh[2] ?? '?' );
		};

		$big   = $logo( 400 );
		$small = $logo( 100 );

		$this->assertSame( '80x80', $size( $big, 200, 80 ), 'Logo carré : la hauteur maximale l’emporte.' );
		$this->assertSame( '200x200', $size( $big, 200, 300 ), 'La largeur maximale l’emporte.' );
		$this->assertSame( '100x100', $size( $small, 200, 150 ), 'Jamais agrandi au-delà de sa taille réelle.' );
		$this->assertSame( 300, Options::sanitize_field( 'logo_max_height', '999' ), 'Hauteur bornée à 300 px.' );
	}

	public function test_border_color_setting(): void {
		$this->assertSame( '', Options::sanitize_field( 'color_border', 'pas une couleur' ) );

		Options::replace( array( 'color_border' => '#AA0000' ) );
		$html = $this->render( 'customer_processing_order', $this->create_order() );
		$this->assertMatchesRegularExpression( '/class="td bb-total-first"[^>]*border-top: 1px solid #a(a0)?00;/', $html, 'L’inliner peut abréger #aa0000 en #a00.' );

		Options::replace( array() );
		$html = $this->render( 'customer_processing_order', $this->create_order() );
		$this->assertDoesNotMatchRegularExpression( '/#a(a0)?00\b/', $html, 'Vide : teinte calculée.' );
	}

	public function test_custom_css_is_applied(): void {
		Options::replace( array( 'custom_css' => '.bb-heading { letter-spacing: 3px; }' ) );
		$this->assertMatchesRegularExpression( '/class="bb-heading"[^>]*letter-spacing: 3px/', $this->render( 'customer_processing_order', $this->create_order() ) );
	}
}
