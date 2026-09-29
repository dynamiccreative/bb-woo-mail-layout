<?php
/**
 * Traductions et bascule de langue.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Compat\Wpml;
use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Plugin;

/**
 * @covers \BB\WooMailLayout\Compat\Wpml
 */
class LanguageTest extends TestCase {

	public function test_english_site_gets_english_layout_strings(): void {
		remove_filter( 'override_load_textdomain', array( $this, 'keep_source_strings' ), 10 );
		unload_textdomain( 'bb-woo-mail-layout' );
		load_textdomain( 'bb-woo-mail-layout', BB_WML_DIR . 'languages/bb-woo-mail-layout-en_US.mo' );

		$simulator = new Simulator( Plugin::instance()->registry() );
		$html      = $simulator->render( $simulator->prepare( 'customer_processing_order', $this->create_order()->get_id() ) );

		$this->assertStringContainsString( 'Hello Camille', $html );
		$this->assertStringContainsString( 'Billing address', $html );
		$this->assertStringContainsString( 'Order #', $html );
		$this->assertStringNotContainsString( 'Adresse de facturation', $html );
	}

	public function test_no_language_switch_without_multilingual_plugin(): void {
		$this->assertFalse( Wpml::is_multilingual(), 'Ni WPML ni Polylang dans l’environnement de test.' );

		$calls = 0;
		add_filter(
			'bb_email_locale',
			static function ( $locale ) use ( &$calls ) {
				++$calls;
				return $locale;
			}
		);

		$order = $this->create_order();
		$user  = self::factory()->user->create( array( 'locale' => 'de_DE' ) );
		$order->set_customer_id( $user );
		$order->save();

		$simulator = new Simulator( Plugin::instance()->registry() );
		$simulator->render( $simulator->prepare( 'customer_processing_order', $order->get_id() ) );

		$this->assertSame( 0, $calls, 'Aucune bascule de langue sans extension multilingue (e-mail à moitié traduit sinon).' );
	}
}
