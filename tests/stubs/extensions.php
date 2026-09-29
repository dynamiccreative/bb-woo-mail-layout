<?php
/**
 * Doublures minimales des objets de WooCommerce Bookings et Memberships (extensions payantes absentes des tests).
 * Seules les méthodes lues par le plugin sont reproduites, avec les noms relevés dans le code des extensions.
 *
 * @package BB\WooMailLayout
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting, Generic.Commenting.DocComment.MissingShort

if ( ! class_exists( 'WC_Booking' ) ) {
	/**
	 * Réservation.
	 */
	class WC_Booking {
		public function __construct( private ?WC_Order $order, private ?WC_Product $product, private int $customer_id, private int $start, private string $guest_name = '' ) {}
		public function get_order() {
			return $this->order ?? false;
		}
		public function get_product() {
			return $this->product;
		}
		public function get_customer_id(): int {
			return $this->customer_id;
		}
		public function get_customer(): object {
			return (object) array(
				'name'    => $this->guest_name,
				'user_id' => $this->customer_id,
			);
		}
		public function get_start(): int {
			return $this->start;
		}
	}
}

if ( ! class_exists( 'WC_Memberships_Membership_Plan' ) ) {
	/**
	 * Formule d'adhésion.
	 */
	class WC_Memberships_Membership_Plan {
		public function __construct( private string $name ) {}
		public function get_name(): string {
			return $this->name;
		}
	}
}

if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
	/**
	 * Adhésion d'un membre.
	 */
	class WC_Memberships_User_Membership {
		public function __construct( private WP_User $user, private WC_Memberships_Membership_Plan $plan ) {}
		public function get_user() {
			return $this->user;
		}
		public function get_order() {
			return false;
		}
		public function get_plan() {
			return $this->plan;
		}
	}
}
