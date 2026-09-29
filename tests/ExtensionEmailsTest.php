<?php
/**
 * Textes FR et placeholders des e-mails de WooCommerce Subscriptions, Bookings et Memberships.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\DefaultTexts;
use BB\WooMailLayout\Email\Placeholders;
use BB\WooMailLayout\Plugin;

require_once __DIR__ . '/stubs/extensions.php';

/**
 * @covers \BB\WooMailLayout\Email\DefaultTexts
 * @covers \BB\WooMailLayout\Email\Placeholders
 */
class ExtensionEmailsTest extends TestCase {

	/**
	 * E-mail d'extension simulé.
	 *
	 * @param string $id       Identifiant.
	 * @param mixed  $subject  Objet de l'e-mail.
	 * @param bool   $customer Destiné au client.
	 */
	private function email( string $id, $subject, bool $customer = true ): \WC_Email {
		$email         = new class( $customer ) extends \WC_Email {
			/**
			 * Constructeur.
			 *
			 * @param bool $customer Destiné au client.
			 */
			public function __construct( bool $customer ) {
				$this->customer_email = $customer;
				parent::__construct();
			}
		};
		$email->id     = $id;
		$email->title  = $id;
		$email->object = $subject;
		return $email;
	}

	/**
	 * Intro rendue, en texte brut.
	 *
	 * @param \WC_Email $email E-mail.
	 */
	private function intro( \WC_Email $email ): string {
		return trim( html_entity_decode( wp_strip_all_tags( Plugin::instance()->renderer()->intro_html( $email ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	public function test_extension_emails_have_french_texts(): void {
		$texts = ( new DefaultTexts() )->by_id();
		foreach ( array( 'customer_processing_renewal_order', 'customer_notification_auto_renewal', 'new_renewal_order', 'booking_confirmed', 'new_booking', 'WC_Memberships_User_Membership_Ending_Soon_Email' ) as $id ) {
			$this->assertArrayHasKey( $id, $texts );
		}
		$this->assertArrayHasKey( 'customer_processing_order', $texts, 'Textes natifs conservés.' );

		// Commande de renouvellement : objet WC_Order, placeholders habituels.
		$order = $this->create_order();
		$this->assertStringStartsWith( 'Bonjour Camille,', $this->intro( $this->email( 'customer_processing_renewal_order', $order ) ) );
		$this->assertStringContainsString( 'commande n° ' . $order->get_order_number(), $this->intro( $this->email( 'customer_processing_renewal_order', $order ) ) );
	}

	public function test_booking_placeholders_use_linked_order_and_product(): void {
		$order   = $this->create_order();
		$product = new \WC_Product_Simple();
		$product->set_name( 'Visite du moulin' );
		$product->save();
		$start = (int) strtotime( '2026-10-15 14:30:00' );

		$intro = $this->intro( $this->email( 'booking_confirmed', new \WC_Booking( $order, $product, 0, $start ) ) );
		$this->assertStringStartsWith( 'Bonjour Camille,', $intro, 'Prénom lu sur la commande liée.' );
		$this->assertStringContainsString( '« Visite du moulin »', $intro );
		$this->assertStringContainsString( date_i18n( wc_date_format(), $start ), $intro );

		// Réservation d'un invité sans commande : nom saisi à la réservation.
		$intro = $this->intro( $this->email( 'booking_reminder', new \WC_Booking( null, $product, 0, $start, 'Dominique' ) ) );
		$this->assertStringStartsWith( 'Bonjour Dominique,', $intro );

		// Client connecté.
		$user_id = self::factory()->user->create( array( 'first_name' => 'Alix' ) );
		$context = Placeholders::context( new \WC_Booking( null, $product, $user_id, $start ) );
		$this->assertSame( $user_id, $context['user'] ? $context['user']->ID : 0 );
	}

	public function test_membership_placeholders(): void {
		$user = get_user_by( 'id', self::factory()->user->create( array( 'first_name' => 'Sacha' ) ) );
		$this->assertInstanceOf( \WP_User::class, $user );

		$membership = new \WC_Memberships_User_Membership( $user, new \WC_Memberships_Membership_Plan( 'Club des gourmets' ) );
		$intro      = $this->intro( $this->email( 'WC_Memberships_User_Membership_Ended_Email', $membership ) );
		$this->assertStringStartsWith( 'Bonjour Sacha,', $intro );
		$this->assertStringContainsString( 'Votre adhésion « Club des gourmets » a pris fin.', $intro );
	}
}
