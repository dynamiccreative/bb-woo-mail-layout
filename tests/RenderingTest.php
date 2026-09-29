<?php
/**
 * Rendu des e-mails natifs dans les deux layouts + snapshots HTML.
 *
 * Snapshots (livrable) : BB_WML_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter RenderingTest
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Plugin;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Email\LayoutRenderer
 */
class RenderingTest extends TestCase {

	/**
	 * Couples layout / e-mail natif.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public function provide_native_emails(): array {
		$cases = array();
		foreach ( array( 'classique', 'sobre', 'ecommerce' ) as $layout ) {
			foreach ( EmailRegistry::NATIVE_IDS as $id ) {
				$cases[ $layout . ' / ' . $id ] = array( $layout, $id );
			}
		}
		return $cases;
	}

	/**
	 * @dataProvider provide_native_emails
	 *
	 * @param string $layout   Layout.
	 * @param string $email_id E-mail.
	 */
	public function test_native_email_is_rendered_in_layout( string $layout, string $email_id ): void {
		$registry = Plugin::instance()->registry();
		if ( ! $registry->get( $email_id ) ) {
			$this->markTestSkipped( "E-mail $email_id absent de cette version de WooCommerce." );
		}

		Options::replace(
			array(
				'layout'            => $layout,
				'logo_url'          => 'https://example.org/logo.png',
				'contact_phone'     => '04 75 00 00 00',
				'social_facebook'   => 'https://facebook.com/exemple',
				'footer_legal_name' => 'Moulin Exemple SARL',
			)
		);

		$order     = $this->create_order( 3, 'customer_invoice' === $email_id ? 'pending' : 'processing' );
		$simulator = new Simulator( $registry );
		$html      = $simulator->render( $simulator->prepare( $email_id, $order->get_id() ) );

		$this->assertStringContainsString( 'bb-layout-' . $layout, $html );
		$this->assertStringContainsString( 'bb-heading', $html );
		$this->assertStringContainsString( 'Moulin Exemple SARL', $html );
		$this->assertStringContainsString( 'assets/icons/facebook.png', $html );
		// Le « contenu additionnel » (ex. « Congratulations on the sale. ») est un réglage WooCommerce, traduit sur un site FR.
		$this->assertDoesNotMatchRegularExpression( '/\bHi\b|Just to let you know|Billing address|Shipping address|Process your orders on the go/', $html );
		$this->assertSame( 1, substr_count( $html, '<html' ), 'Un seul document HTML (header/footer natifs non doublés).' );

		if ( ! in_array( $email_id, Simulator::ACCOUNT_EMAILS, true ) ) {
			$this->assertStringContainsString( 'Commande n°', $html );
			$this->assertStringContainsString( 'Adresse de facturation', $html );
		}

		$this->snapshot( $layout, $email_id, $html );
	}

	/**
	 * Une extension qui remplace le CSS des e-mails (ex. Flycart) ne doit pas retirer le style du layout.
	 */
	public function test_layout_css_survives_third_party_style_override(): void {
		$override = static fn() => '.autre-extension { color: red; }';
		add_filter( 'woocommerce_email_styles', $override, 10 );

		$simulator = new Simulator( Plugin::instance()->registry() );
		$html      = $simulator->render( $simulator->prepare( 'new_order', $this->create_order()->get_id() ) );

		remove_filter( 'woocommerce_email_styles', $override, 10 );

		$this->assertMatchesRegularExpression( '/<td class="bb-main"[^>]*style="[^"]*padding:/', $html );
	}

	public function test_disabled_email_falls_back_to_native_rendering(): void {
		Options::replace( array( 'emails' => array( 'customer_processing_order' => 'no' ) ) );

		$simulator = new Simulator( Plugin::instance()->registry() );
		$html      = $simulator->render( $simulator->prepare( 'customer_processing_order', $this->create_order()->get_id() ) );

		$this->assertStringNotContainsString( 'bb-body', $html );
		$this->assertStringContainsString( 'template_container', $html, 'Header WooCommerce natif attendu.' );
	}

	/**
	 * Surcoût du layout par rapport au rendu WooCommerce natif (commande de 10 lignes).
	 *
	 * Le temps absolu dépend surtout de WooCommerce et de l'hébergement (OPcache, base) : on mesure
	 * donc le surcoût propre au plugin. BB_WML_PERF_BUDGET_MS ajuste le budget (25 ms par défaut, marge pour le bruit de mesure).
	 */
	public function test_layout_overhead_under_budget(): void {
		$budget    = (float) ( getenv( 'BB_WML_PERF_BUDGET_MS' ) ? getenv( 'BB_WML_PERF_BUDGET_MS' ) : 25 );
		$simulator = new Simulator( Plugin::instance()->registry() );
		$order_id  = $this->create_order( 10 )->get_id();

		$median = function () use ( $simulator, $order_id ): float {
			$simulator->render( $simulator->prepare( 'customer_processing_order', $order_id ) ); // Chauffe.
			$times = array();
			for ( $i = 0; $i < 7; $i++ ) {
				$start = hrtime( true );
				$simulator->render( $simulator->prepare( 'customer_processing_order', $order_id ) );
				$times[] = ( hrtime( true ) - $start ) / 1e6;
			}
			sort( $times );
			return $times[3];
		};

		$with_layout = $median();
		Options::replace( array( 'emails' => array( 'customer_processing_order' => 'no' ) ) );
		$native = $median();

		$this->assertLessThan(
			$budget,
			$with_layout - $native,
			sprintf( 'Layout %.1f ms, natif %.1f ms', $with_layout, $native )
		);
	}

	/**
	 * Écrit le snapshot si demandé.
	 *
	 * @param string $layout   Layout.
	 * @param string $email_id E-mail.
	 * @param string $html     HTML.
	 */
	private function snapshot( string $layout, string $email_id, string $html ): void {
		if ( ! getenv( 'BB_WML_UPDATE_SNAPSHOTS' ) ) {
			return;
		}
		$dir = __DIR__ . '/snapshots/' . $layout;
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/' . $email_id . '.html', $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}
