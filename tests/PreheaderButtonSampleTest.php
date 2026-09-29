<?php
/**
 * 1.4.0 : pré-en-tête, bouton d'action par e-mail, commande fictive.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Plugin;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Email\LayoutRenderer
 * @covers \BB\WooMailLayout\Email\Simulator
 */
class PreheaderButtonSampleTest extends TestCase {

	/**
	 * Rendu d'un e-mail (commande 0 = commande fictive).
	 *
	 * @param string $email_id E-mail.
	 * @param int    $order_id Commande.
	 */
	private function render( string $email_id, int $order_id ): string {
		$simulator = new Simulator( Plugin::instance()->registry() );
		return $simulator->render( $simulator->prepare( $email_id, $order_id ) );
	}

	/**
	 * Texte du pré-en-tête (avant les espaces invisibles).
	 *
	 * @param string $html HTML.
	 */
	private static function preheader( string $html ): string {
		if ( ! preg_match( '/<div class="bb-preheader[^"]*"[^>]*>([^<]*)/', $html, $m ) ) {
			return '';
		}
		// L'inliner convertit les entités de remplissage (&#847;&zwnj;&nbsp;) en caractères.
		return trim( (string) preg_replace( '/[\x{034F}\x{200C}\x{00A0}\s]+$/u', '', html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	public function test_preheader_defaults_to_intro_and_can_be_customized(): void {
		$order = $this->create_order();

		Options::replace( array( 'intros' => array( 'customer_processing_order' => "Bonjour {customer_first_name}, merci pour la commande {order_number}.\n\nSecond paragraphe." ) ) );
		$html = $this->render( 'customer_processing_order', $order->get_id() );
		$this->assertMatchesRegularExpression( '/<body[^>]*>\s*<div class="bb-preheader /', $html, 'Pré-en-tête juste après <body>.' );
		$this->assertMatchesRegularExpression( '/class="bb-preheader[^"]*"[^>]*display: ?none/', $html );
		$this->assertSame( 'Bonjour ' . $order->get_billing_first_name() . ', merci pour la commande ' . $order->get_order_number() . '. Second paragraphe.', self::preheader( $html ) );

		Options::replace( array( 'preheaders' => array( 'customer_processing_order' => 'Votre colis part demain, {customer_first_name} !' ) ) );
		$this->assertSame( 'Votre colis part demain, ' . $order->get_billing_first_name() . ' !', self::preheader( $this->render( 'customer_processing_order', $order->get_id() ) ) );

		Options::replace( array( 'preheaders' => array( 'customer_processing_order' => str_repeat( 'a', 300 ) ) ) );
		$this->assertSame( 140, mb_strlen( self::preheader( $this->render( 'customer_processing_order', $order->get_id() ) ) ), 'Tronqué à 140 caractères.' );
	}

	public function test_action_button_from_placeholder_or_url(): void {
		$order = $this->create_order();

		$this->assertSame( '{tracking_url}', Options::sanitize_link( ' {tracking_url} ' ) );
		$this->assertSame( '', Options::sanitize_link( 'javascript:alert(1)' ) );
		$this->assertSame( 'https://exemple.fr/avis', Options::sanitize_link( 'https://exemple.fr/avis' ) );

		Options::replace(
			array(
				'button_urls'   => array(
					'customer_processing_order' => '{order_url}',
					'customer_completed_order'  => '{tracking_url}',
					'customer_on_hold_order'    => 'https://exemple.fr/avis',
				),
				'button_labels' => array( 'customer_on_hold_order' => 'Donner mon avis, {customer_first_name}' ),
			)
		);

		$html = $this->render( 'customer_processing_order', $order->get_id() );
		$this->assertMatchesRegularExpression( '/<div class="bb-action">.*?<a class="bb-button" href="' . preg_quote( esc_url( $order->get_view_order_url() ), '/' ) . '"[^>]*>Voir ma commande<\/a>/s', $html, 'Libellé vide : libellé du placeholder.' );

		$this->assertStringNotContainsString( 'bb-action', $this->render( 'customer_completed_order', $order->get_id() ), 'Pas de numéro de suivi : pas de bouton.' );

		$html = $this->render( 'customer_on_hold_order', $this->create_order( 2, 'on-hold' )->get_id() );
		$this->assertMatchesRegularExpression( '/class="bb-button" href="https:\/\/exemple\.fr\/avis"[^>]*>Donner mon avis, ' . preg_quote( $order->get_billing_first_name(), '/' ) . '<\/a>/', $html );

		// Filtre développeur.
		add_filter( 'bb_email_action_button', '__return_null' );
		$this->assertStringNotContainsString( 'bb-action', $this->render( 'customer_processing_order', $order->get_id() ) );
		remove_filter( 'bb_email_action_button', '__return_null' );
	}

	public function test_sample_order_renders_without_writing_to_database(): void {
		global $wpdb;
		$orders = count( wc_get_orders( array( 'limit' => -1 ) ) );
		$items  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( array( 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'new_order' ) as $email_id ) {
			$html = $this->render( $email_id, 0 );
			$this->assertStringContainsString( 'bb-layout-', $html );
			$this->assertStringContainsString( 'Commande n°', $html );
			$this->assertStringContainsString( Simulator::SAMPLE_NUMBER, $html );
			$this->assertStringContainsString( 'Camille', $html );
			$this->assertStringContainsString( '13100', $html );
		}

		$this->assertSame( $orders, count( wc_get_orders( array( 'limit' => -1 ) ) ), 'Aucune commande créée.' );
		$this->assertSame( $items, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items" ), 'Aucune ligne de commande créée.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_sample_status_follows_email(): void {
		$simulator = new Simulator( Plugin::instance()->registry() );
		$this->assertSame( 'completed', $simulator->sample_order( 'customer_completed_order' )->get_status() );
		$this->assertSame( 'processing', $simulator->sample_order( 'new_order' )->get_status() );
		$this->assertSame( Simulator::SAMPLE_NUMBER, $simulator->sample_order()->get_order_number() );
	}
}
