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

	/** Numéro affiché pour la commande fictive. */
	public const SAMPLE_NUMBER = '1234';

	/** Statut de la commande fictive selon l'e-mail (par défaut : en cours). */
	private const SAMPLE_STATUSES = array(
		'customer_on_hold_order'   => 'on-hold',
		'customer_completed_order' => 'completed',
		'customer_invoice'         => 'pending',
		'customer_failed_order'    => 'failed',
		'failed_order'             => 'failed',
		'customer_cancelled_order' => 'cancelled',
		'cancelled_order'          => 'cancelled',
		'customer_refunded_order'  => 'refunded',
	);

	/**
	 * Commande fictive (créée une fois par instance).
	 *
	 * @var \WC_Order|null
	 */
	private ?\WC_Order $sample = null;

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
	 * @param int    $order_id Commande (0 = commande fictive ; utilisateur courant pour les e-mails de compte).
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
			$order = $this->sample_order( $email->id );
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
			$refunds = $order->get_id() ? $order->get_refunds() : array();
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
	 * Commande fictive, jamais enregistrée : aperçu et test sur un site sans commande.
	 *
	 * Rien n'est écrit en base : les lignes sont ajoutées sans save() (add_product() enregistrerait
	 * la ligne) et l'identifiant reste 0 (aucune lecture de métadonnées d'une vraie commande).
	 * Produits : les derniers produits publiés du site (photos réelles), sinon des produits d'exemple.
	 *
	 * @param string $email_id E-mail (fixe le statut affiché).
	 */
	public function sample_order( string $email_id = '' ): \WC_Order {
		if ( ! $this->sample ) {
			$this->sample = $this->build_sample_order();
		}
		$this->sample->set_status( self::SAMPLE_STATUSES[ $email_id ] ?? 'processing' );
		// Aucun remboursement : évite une requête « parent = 0 » dans WC_Order::get_refunds().
		wp_cache_set( \WC_Cache_Helper::get_cache_prefix( 'orders' ) . 'refund_ids0', array(), 'orders' );
		return $this->sample;
	}

	/**
	 * Construit la commande fictive.
	 */
	private function build_sample_order(): \WC_Order {
		$order = new \WC_Order();
		$order->set_currency( get_woocommerce_currency() );
		$order->set_date_created( time() );
		$order->set_order_key( 'wc_order_exemple' );
		$order->set_payment_method_title( __( 'Carte bancaire', 'bb-woo-mail-layout' ) );

		$address = array(
			'first_name' => 'Camille',
			'last_name'  => 'Martin',
			'company'    => '',
			'address_1'  => __( '12 rue des Oliviers', 'bb-woo-mail-layout' ),
			'address_2'  => '',
			'city'       => 'Aix-en-Provence',
			'state'      => '',
			'postcode'   => '13100',
			'country'    => 'FR',
		);
		foreach ( $address as $field => $value ) {
			$order->{'set_billing_' . $field}( $value );
			$order->{'set_shipping_' . $field}( $value );
		}
		$order->set_billing_email( 'camille.martin@example.com' );
		$order->set_billing_phone( '06 12 34 56 78' );

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'type'    => array( 'simple' ),
				'limit'   => 2,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		$products = is_array( $products ) ? $products : array();
		if ( ! $products ) {
			foreach ( array(
				__( 'Produit d’exemple', 'bb-woo-mail-layout' )        => '24.90',
				__( 'Second produit d’exemple', 'bb-woo-mail-layout' ) => '12.50',
			) as $name => $price ) {
				$product = new \WC_Product_Simple();
				$product->set_name( $name );
				$product->set_regular_price( $price );
				$product->set_price( $price );
				$products[] = $product;
			}
		}

		$subtotal = 0.0;
		foreach ( array_values( $products ) as $i => $product ) {
			$qty       = 0 === $i ? 2 : 1;
			$line      = (float) $product->get_price() * $qty;
			$subtotal += $line;

			$item = new \WC_Order_Item_Product();
			$item->set_props(
				array(
					'name'       => $product->get_name(),
					'product_id' => $product->get_id(),
					'quantity'   => $qty,
					'subtotal'   => $line,
					'total'      => $line,
				)
			);
			$order->add_item( $item );
		}

		$shipping_total = 6.90;
		$shipping       = new \WC_Order_Item_Shipping();
		$shipping->set_method_title( __( 'Livraison à domicile', 'bb-woo-mail-layout' ) );
		$shipping->set_total( (string) $shipping_total );
		$order->add_item( $shipping );

		$order->set_shipping_total( (string) $shipping_total );
		$order->set_total( (string) ( $subtotal + $shipping_total ) );

		add_filter(
			'woocommerce_order_number',
			static fn( $number, $subject ) => $subject === $order ? self::SAMPLE_NUMBER : $number,
			10,
			2
		);
		return $order;
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
		// WP-CLI sans --user : aucun utilisateur connecté, on prend le compte de l'administrateur du site.
		if ( ! $user->exists() ) {
			$admin = get_user_by( 'email', (string) get_option( 'admin_email' ) );
			$user  = $admin instanceof \WP_User ? $admin : $user;
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
