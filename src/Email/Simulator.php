<?php
/**
 * Prépare un e-mail WooCommerce avec une commande réelle, sans déclencher son trigger.
 * Utilisé par la prévisualisation et l'e-mail de test.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Email;

defined( 'ABSPATH' ) || exit;

/**
 * Jeu de données pour preview / test.
 */
final class Simulator {

	/** E-mails dont l'objet est un utilisateur et non une commande. */
	public const ACCOUNT_EMAILS = array( 'customer_new_account', 'customer_reset_password', 'customer_verify_email' );

	/**
	 * Constructeur.
	 *
	 * @param EmailRegistry $registry Registre des e-mails.
	 */
	public function __construct( private EmailRegistry $registry ) {}

	/**
	 * 20 dernières commandes (API CRUD, compatible HPOS).
	 *
	 * @param int $limit Nombre de commandes.
	 * @return \WC_Order[]
	 */
	public function recent_orders( int $limit = 20 ): array {
		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			)
		);
		return is_array( $orders ) ? array_values( $orders ) : array();
	}

	/**
	 * Clone de l'e-mail, alimenté avec la commande.
	 *
	 * @param string $email_id Identifiant de l'e-mail.
	 * @param int    $order_id Commande (0 = aucune).
	 * @throws \RuntimeException E-mail ou commande introuvable.
	 */
	public function prepare( string $email_id, int $order_id ): \WC_Email {
		$source = $this->registry->get( $email_id );
		if ( ! $source ) {
			throw new \RuntimeException( esc_html__( 'E-mail introuvable.', 'bb-woo-mail-layout' ) );
		}
		$email = clone $source;

		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( $order_id && ! $order instanceof \WC_Order ) {
			throw new \RuntimeException( esc_html__( 'Commande introuvable.', 'bb-woo-mail-layout' ) );
		}

		if ( in_array( $email->id, self::ACCOUNT_EMAILS, true ) ) {
			$this->prepare_account_email( $email, $order instanceof \WC_Order ? $order : null );
			return $email;
		}

		if ( ! $order instanceof \WC_Order ) {
			throw new \RuntimeException( esc_html__( 'Choisissez une commande pour cet e-mail.', 'bb-woo-mail-layout' ) );
		}

		self::set( $email, 'object', $order );
		if ( $email->is_customer_email() ) {
			self::set( $email, 'recipient', $order->get_billing_email() );
		}
		self::set(
			$email,
			'placeholders',
			array_merge(
				(array) self::get( $email, 'placeholders' ),
				array(
					'{order_date}'              => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
					'{order_number}'            => (string) $order->get_order_number(),
					'{order_billing_full_name}' => $order->get_formatted_billing_full_name(),
				)
			)
		);

		if ( 'customer_note' === $email->id ) {
			self::set( $email, 'customer_note', __( 'Ceci est un exemple de note ajoutée à la commande par la boutique.', 'bb-woo-mail-layout' ) );
		}
		if ( 'customer_refunded_order' === $email->id ) {
			$refunds = $order->get_refunds();
			self::set( $email, 'partial_refund', $refunds && (float) $order->get_total_refunded() < (float) $order->get_total() );
			self::set( $email, 'refund', $refunds ? reset( $refunds ) : null );
		}

		/**
		 * Complète la préparation d'un e-mail tiers (propriétés attendues par son template).
		 *
		 * @param \WC_Email $email E-mail cloné.
		 * @param \WC_Order $order Commande.
		 *
		 * @since 1.0.0
		 */
		do_action( 'bb_email_prepare_simulation', $email, $order );

		return $email;
	}

	/**
	 * Rendu HTML final (layout + CSS inliné par WooCommerce).
	 *
	 * @param \WC_Email $email E-mail préparé.
	 */
	public function render( \WC_Email $email ): string {
		$email->setup_locale();
		try {
			$html = $email->style_inline( $email->get_content_html() );
		} finally {
			$email->restore_locale();
		}
		/**
		 * Filtre WooCommerce appliqué au contenu avant envoi (documenté dans WC_Email::send).
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( 'woocommerce_mail_content', $html ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- reproduit le pipeline WooCommerce.
	}

	/**
	 * Nouveau compte / mot de passe : l'objet est un utilisateur. Clé factice, aucun lien réel.
	 *
	 * @param \WC_Email      $email E-mail cloné.
	 * @param \WC_Order|null $order Commande (pour retrouver le client).
	 */
	private function prepare_account_email( \WC_Email $email, ?\WC_Order $order ): void {
		$user = $order ? $order->get_user() : false;
		if ( ! $user instanceof \WP_User ) {
			$user = wp_get_current_user();
		}

		$props = array(
			'object'             => $user,
			'recipient'          => $user->user_email,
			'user_login'         => $user->user_login,
			'user_email'         => $user->user_email,
			'user_id'            => $user->ID,
			'user_display_name'  => $user->display_name,
			'user_pass'          => '',
			'password_generated' => true,
			'reset_key'          => 'exemple-cle-de-test',
			'verify_url'         => add_query_arg( 'bb-exemple', 'verification', wc_get_page_permalink( 'myaccount' ) ),
			'set_password_url'   => add_query_arg(
				array(
					'key' => 'exemple-cle-de-test',
					'id'  => $user->ID,
				),
				wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
			),
		);
		foreach ( $props as $prop => $value ) {
			self::set( $email, $prop, $value );
		}
	}

	/**
	 * Écrit une propriété déclarée de l'e-mail, quelle que soit sa visibilité (`placeholders` est protected).
	 * Une propriété non déclarée est ignorée (pas de propriété dynamique).
	 *
	 * @param \WC_Email $email E-mail.
	 * @param string    $prop  Propriété.
	 * @param mixed     $value Valeur.
	 */
	private static function set( \WC_Email $email, string $prop, $value ): void {
		if ( property_exists( $email, $prop ) ) {
			\Closure::bind(
				function () use ( $prop, $value ): void {
					$this->$prop = $value;
				},
				$email,
				get_class( $email )
			)();
		}
	}

	/**
	 * Lit une propriété déclarée de l'e-mail, quelle que soit sa visibilité.
	 *
	 * @param \WC_Email $email E-mail.
	 * @param string    $prop  Propriété.
	 * @return mixed
	 */
	private static function get( \WC_Email $email, string $prop ) {
		if ( ! property_exists( $email, $prop ) ) {
			return null;
		}
		return \Closure::bind(
			function () use ( $prop ) {
				return $this->$prop;
			},
			$email,
			get_class( $email )
		)();
	}
}
