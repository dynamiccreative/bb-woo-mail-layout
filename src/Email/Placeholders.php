<?php
/**
 * Placeholders des textes éditables ({customer_first_name}, {order_number}…).
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Email;

use BB\WooMailLayout\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Calcule les valeurs et les remplace dans un texte.
 *
 * Une valeur est soit une chaîne (texte brut, échappé au rendu), soit un lien
 * `array( 'url' => …, 'label' => … )` rendu en <a>.
 */
final class Placeholders {

	/** Placeholders dont la valeur est un lien : proposés comme lien du bouton d'action. */
	public const LINKS = array( '{order_url}', '{payment_url}', '{tracking_url}', '{site_url}' );

	/**
	 * Placeholders documentés (pour l'aide de l'admin).
	 *
	 * @return array<string, string> placeholder => description.
	 */
	public static function documented(): array {
		return array(
			'{site_title}'            => __( 'Nom du site', 'bb-woo-mail-layout' ),
			'{site_url}'              => __( 'Lien vers le site', 'bb-woo-mail-layout' ),
			'{customer_first_name}'   => __( 'Prénom du client', 'bb-woo-mail-layout' ),
			'{customer_last_name}'    => __( 'Nom du client', 'bb-woo-mail-layout' ),
			'{order_number}'          => __( 'Numéro de commande', 'bb-woo-mail-layout' ),
			'{order_date}'            => __( 'Date de commande', 'bb-woo-mail-layout' ),
			'{order_total}'           => __( 'Total de la commande', 'bb-woo-mail-layout' ),
			'{order_url}'             => __( 'Lien « Voir ma commande »', 'bb-woo-mail-layout' ),
			'{tracking_url}'          => __( 'Lien de suivi du colis (si fourni par l’extension d’expédition)', 'bb-woo-mail-layout' ),
			'{admin_email}'           => __( 'E-mail de l’administrateur', 'bb-woo-mail-layout' ),
			'{shop_phone}'            => __( 'Téléphone de la boutique', 'bb-woo-mail-layout' ),
			'{billing_address}'       => __( 'Adresse de facturation', 'bb-woo-mail-layout' ),
			'{shipping_address}'      => __( 'Adresse de livraison', 'bb-woo-mail-layout' ),
			'{payment_method}'        => __( 'Moyen de paiement', 'bb-woo-mail-layout' ),
			'{shipping_method}'       => __( 'Mode de livraison', 'bb-woo-mail-layout' ),
			'{payment_url}'           => __( 'Lien « Payer ma commande » (commande à régler uniquement)', 'bb-woo-mail-layout' ),
			'{order_meta:clé}'        => __( 'Métadonnée de commande (ex. {order_meta:_numero_client})', 'bb-woo-mail-layout' ),
			'{next_payment_date}'     => __( 'Date du prochain paiement (WooCommerce Subscriptions)', 'bb-woo-mail-layout' ),
			'{subscription_end_date}' => __( 'Date de fin de l’abonnement (WooCommerce Subscriptions)', 'bb-woo-mail-layout' ),
			'{booking_product}'       => __( 'Prestation réservée (WooCommerce Bookings)', 'bb-woo-mail-layout' ),
			'{booking_date}'          => __( 'Date et heure de la réservation (WooCommerce Bookings)', 'bb-woo-mail-layout' ),
			'{membership_plan}'       => __( 'Formule d’adhésion (WooCommerce Memberships)', 'bb-woo-mail-layout' ),
		);
	}

	/**
	 * Commande et client liés à l'objet d'un e-mail.
	 *
	 * Commande (dont abonnement WC_Subscription) ou utilisateur : directement. Réservation
	 * (WooCommerce Bookings) et adhésion (WooCommerce Memberships) : commande et client rattachés,
	 * détectés par leurs méthodes (get_order(), get_user() / get_customer_id()) sans dépendre des classes.
	 *
	 * @param mixed $subject Objet de l'e-mail.
	 * @return array{order:\WC_Order|null,user:\WP_User|null}
	 */
	public static function context( $subject ): array {
		$order = $subject instanceof \WC_Order ? $subject : null;
		$user  = $subject instanceof \WP_User ? $subject : null;

		if ( ! $order && ! $user && is_object( $subject ) ) {
			if ( method_exists( $subject, 'get_order' ) ) {
				$linked = $subject->get_order();
				$order  = $linked instanceof \WC_Order ? $linked : null;
			}
			if ( method_exists( $subject, 'get_user' ) ) {
				$linked = $subject->get_user();
				$user   = $linked instanceof \WP_User ? $linked : null;
			} elseif ( method_exists( $subject, 'get_customer_id' ) ) {
				$linked = get_user_by( 'id', (int) $subject->get_customer_id() );
				$user   = $linked instanceof \WP_User ? $linked : null;
			}
		}

		return array(
			'order' => $order,
			'user'  => $user,
		);
	}

	/**
	 * Valeurs pour un e-mail donné.
	 *
	 * @param \WC_Email|null $email E-mail en cours de rendu.
	 * @return array<string, mixed> Valeurs (texte ou lien [ url, label ]) ; le filtre peut en ajouter.
	 */
	public function values( ?\WC_Email $email ): array {
		$object  = $email ? $email->object : null;
		$context = self::context( $object );
		$order   = $context['order'];
		$user    = $context['user'];

		$values = array(
			'{site_title}'          => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			'{site_url}'            => array(
				'url'   => home_url( '/' ),
				'label' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			),
			'{customer_first_name}' => '',
			'{customer_last_name}'  => '',
			'{order_number}'        => '',
			'{order_date}'          => '',
			'{order_total}'         => '',
			'{order_url}'           => '',
			'{tracking_url}'        => '',
			'{admin_email}'         => (string) get_option( 'admin_email' ),
			'{shop_phone}'          => (string) Options::get( 'contact_phone' ),
			'{billing_address}'     => '',
			'{shipping_address}'    => '',
			'{payment_method}'      => '',
			'{shipping_method}'     => '',
			'{payment_url}'         => '',
			'{email_title}'         => $email ? $email->get_title() : '',
		);

		if ( $order ) {
			$values['{customer_first_name}'] = $order->get_billing_first_name();
			$values['{customer_last_name}']  = $order->get_billing_last_name();
			$values['{order_number}']        = (string) $order->get_order_number();
			$values['{order_date}']          = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
			$values['{order_total}']         = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );

			$is_admin_email        = ! $email->is_customer_email();
			$is_subscription       = is_a( $order, 'WC_Subscription' );
			$values['{order_url}'] = array(
				'url'   => $is_admin_email ? $order->get_edit_order_url() : $order->get_view_order_url(),
				'label' => $is_subscription
					? ( $is_admin_email ? __( 'Voir l’abonnement', 'bb-woo-mail-layout' ) : __( 'Voir mon abonnement', 'bb-woo-mail-layout' ) )
					: ( $is_admin_email ? __( 'Voir la commande', 'bb-woo-mail-layout' ) : __( 'Voir ma commande', 'bb-woo-mail-layout' ) ),
			);

			$values['{billing_address}']  = array( 'html' => (string) $order->get_formatted_billing_address() );
			$values['{shipping_address}'] = array( 'html' => (string) $order->get_formatted_shipping_address() );
			$values['{payment_method}']   = $order->get_payment_method_title();
			$values['{shipping_method}']  = $order->get_shipping_method();
			if ( $order->needs_payment() ) {
				$values['{payment_url}'] = array(
					'url'   => $order->get_checkout_payment_url(),
					'label' => __( 'Payer ma commande', 'bb-woo-mail-layout' ),
				);
			}

			$tracking = $this->tracking_url( $order );
			if ( '' !== $tracking ) {
				$values['{tracking_url}'] = array(
					'url'   => $tracking,
					'label' => __( 'Suivre mon colis', 'bb-woo-mail-layout' ),
				);
			}
		}
		if ( $user && '' === $values['{customer_first_name}'] ) {
			$values['{customer_first_name}'] = $user->first_name ? $user->first_name : $user->display_name;
			$values['{customer_last_name}']  = (string) $user->last_name;
		}

		$values = array_merge( $values, self::extension_values( $object, $values ) );

		/**
		 * Ajoute ou modifie des placeholders.
		 *
		 * @param array<string, mixed> $values Placeholder => texte brut, lien [ 'url' => …, 'label' => … ] ou HTML [ 'html' => … ].
		 * @param \WC_Email|null       $email  E-mail en cours de rendu.
		 *
		 * @since 1.0.0
		 */
		return (array) apply_filters( 'bb_email_placeholders', $values, $email );
	}

	/**
	 * Placeholders propres à WooCommerce Subscriptions, Bookings et Memberships (vides sinon).
	 * Méthodes testées avec method_exists() : aucune dépendance aux classes de ces extensions.
	 *
	 * @param mixed                $subject Objet de l'e-mail.
	 * @param array<string, mixed> $values  Valeurs déjà calculées.
	 * @return array<string, string>
	 */
	private static function extension_values( $subject, array $values ): array {
		$out = array(
			'{next_payment_date}'     => '',
			'{subscription_end_date}' => '',
			'{booking_product}'       => '',
			'{booking_date}'          => '',
			'{membership_plan}'       => '',
		);
		if ( ! is_object( $subject ) ) {
			return $out;
		}

		// Abonnement : l'objet est la commande de renouvellement ou l'abonnement lui-même.
		$subscription = is_a( $subject, 'WC_Subscription' ) ? $subject : null;
		if ( ! $subscription && $subject instanceof \WC_Order && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$related      = wcs_get_subscriptions_for_order( $subject, array( 'order_type' => 'any' ) );
			$subscription = is_array( $related ) && $related ? reset( $related ) : null;
		}
		if ( is_object( $subscription ) && method_exists( $subscription, 'get_date_to_display' ) ) {
			$out['{next_payment_date}']     = (string) $subscription->get_date_to_display( 'next_payment' );
			$out['{subscription_end_date}'] = (string) $subscription->get_date_to_display( 'end' );
		}

		// Réservation (WooCommerce Bookings).
		if ( is_a( $subject, 'WC_Booking' ) ) {
			$product                  = method_exists( $subject, 'get_product' ) ? $subject->get_product() : null;
			$out['{booking_product}'] = $product instanceof \WC_Product ? $product->get_name() : '';
			if ( method_exists( $subject, 'get_start_date' ) ) {
				$out['{booking_date}'] = (string) $subject->get_start_date();
			} elseif ( method_exists( $subject, 'get_start' ) && $subject->get_start() ) {
				$out['{booking_date}'] = date_i18n( wc_date_format() . ' ' . wc_time_format(), (int) $subject->get_start() );
			}
		}

		// Adhésion (WooCommerce Memberships).
		if ( is_a( $subject, 'WC_Memberships_User_Membership' ) && method_exists( $subject, 'get_plan' ) ) {
			$plan                     = $subject->get_plan();
			$out['{membership_plan}'] = is_object( $plan ) && method_exists( $plan, 'get_name' ) ? (string) $plan->get_name() : '';
		}

		// Réservation d'un invité : nom saisi à la réservation.
		if ( '' === $values['{customer_first_name}'] && method_exists( $subject, 'get_customer' ) ) {
			$customer = $subject->get_customer();
			if ( is_object( $customer ) && ! empty( $customer->name ) ) {
				$out['{customer_first_name}'] = (string) $customer->name;
			}
		}

		return $out;
	}

	/**
	 * Remplace les placeholders dans un texte brut et renvoie du HTML sûr.
	 *
	 * @param string         $text  Texte brut saisi dans l'admin.
	 * @param \WC_Email|null $email E-mail en cours de rendu.
	 */
	public function to_html( string $text, ?\WC_Email $email ): string {
		return $this->replace( esc_html( $text ), $email );
	}

	/**
	 * Remplace les placeholders dans un HTML déjà assaini (pied de page).
	 *
	 * @param string         $html  HTML assaini.
	 * @param \WC_Email|null $email E-mail en cours de rendu.
	 */
	public function replace( string $html, ?\WC_Email $email ): string {
		if ( ! str_contains( $html, '{' ) ) {
			return $html;
		}

		$map = array();
		foreach ( $this->values( $email ) as $placeholder => $value ) {
			if ( is_array( $value ) && isset( $value['html'] ) ) {
				$map[ $placeholder ] = wp_kses_post( (string) $value['html'] );
			} elseif ( is_array( $value ) ) {
				$url                 = is_string( $value['url'] ?? null ) ? $value['url'] : '';
				$label               = is_string( $value['label'] ?? null ) ? $value['label'] : $url;
				$map[ $placeholder ] = '' === $url ? '' : sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
			} else {
				$map[ $placeholder ] = esc_html( (string) $value );
			}
		}
		$html = strtr( $html, $map );
		$html = $this->replace_order_meta( $html, $email );

		// « Bonjour , » quand le prénom est vide.
		return (string) preg_replace( '/[ \x{00A0}]+([,.])/u', '$1', $html );
	}

	/**
	 * Remplace {order_meta:clé} par la métadonnée de la commande (valeurs scalaires uniquement, échappées).
	 *
	 * @param string         $html  HTML.
	 * @param \WC_Email|null $email E-mail en cours de rendu.
	 */
	private function replace_order_meta( string $html, ?\WC_Email $email ): string {
		if ( ! str_contains( $html, '{order_meta:' ) ) {
			return $html;
		}
		$order = $email && $email->object instanceof \WC_Order ? $email->object : null;

		return (string) preg_replace_callback(
			'/\{order_meta:([A-Za-z0-9_\-]{1,100})\}/',
			static function ( array $m ) use ( $order ): string {
				$value = $order ? $order->get_meta( $m[1] ) : '';
				return is_scalar( $value ) ? esc_html( (string) $value ) : '';
			},
			$html
		);
	}

	/**
	 * URL de suivi du colis, si une extension d'expédition la fournit.
	 *
	 * @param \WC_Order $order Commande.
	 */
	public function tracking_url( \WC_Order $order ): string {
		$url = '';

		// WooCommerce Shipment Tracking.
		$items = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( is_array( $items ) && $items && class_exists( 'WC_Shipment_Tracking_Actions' ) ) {
			$item      = end( $items );
			$formatted = \WC_Shipment_Tracking_Actions::get_instance()->get_formatted_tracking_item( $order->get_id(), $item );
			$url       = (string) ( $formatted['formatted_tracking_link'] ?? '' );
		}

		if ( '' === $url ) {
			$url = (string) $order->get_meta( '_tracking_url' );
		}

		/**
		 * URL de suivi pour les extensions d'expédition non détectées (Colissimo, GLS…).
		 *
		 * @param string    $url   URL détectée (peut être vide).
		 * @param \WC_Order $order Commande.
		 *
		 * @since 1.0.0
		 */
		$url = (string) apply_filters( 'bb_email_tracking_url', $url, $order );

		return esc_url_raw( $url );
	}
}
