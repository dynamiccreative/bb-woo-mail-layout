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

	/**
	 * Placeholders documentés (pour l'aide de l'admin).
	 *
	 * @return array<string, string> placeholder => description.
	 */
	public static function documented(): array {
		return array(
			'{site_title}'          => __( 'Nom du site', 'bb-woo-mail-layout' ),
			'{site_url}'            => __( 'Lien vers le site', 'bb-woo-mail-layout' ),
			'{customer_first_name}' => __( 'Prénom du client', 'bb-woo-mail-layout' ),
			'{customer_last_name}'  => __( 'Nom du client', 'bb-woo-mail-layout' ),
			'{order_number}'        => __( 'Numéro de commande', 'bb-woo-mail-layout' ),
			'{order_date}'          => __( 'Date de commande', 'bb-woo-mail-layout' ),
			'{order_total}'         => __( 'Total de la commande', 'bb-woo-mail-layout' ),
			'{order_url}'           => __( 'Lien « Voir ma commande »', 'bb-woo-mail-layout' ),
			'{tracking_url}'        => __( 'Lien de suivi du colis (si fourni par l’extension d’expédition)', 'bb-woo-mail-layout' ),
			'{admin_email}'         => __( 'E-mail de l’administrateur', 'bb-woo-mail-layout' ),
			'{shop_phone}'          => __( 'Téléphone de la boutique', 'bb-woo-mail-layout' ),
		);
	}

	/**
	 * Valeurs pour un e-mail donné.
	 *
	 * @param \WC_Email|null $email E-mail en cours de rendu.
	 * @return array<string, mixed> Valeurs (texte ou lien [ url, label ]) ; le filtre peut en ajouter.
	 */
	public function values( ?\WC_Email $email ): array {
		$object = $email ? $email->object : null;
		$order  = $object instanceof \WC_Order ? $object : null;
		$user   = $object instanceof \WP_User ? $object : null;

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
			'{email_title}'         => $email ? $email->get_title() : '',
		);

		if ( $order ) {
			$values['{customer_first_name}'] = $order->get_billing_first_name();
			$values['{customer_last_name}']  = $order->get_billing_last_name();
			$values['{order_number}']        = (string) $order->get_order_number();
			$values['{order_date}']          = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
			$values['{order_total}']         = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );

			$is_admin_email        = ! $email->is_customer_email();
			$values['{order_url}'] = array(
				'url'   => $is_admin_email ? $order->get_edit_order_url() : $order->get_view_order_url(),
				'label' => $is_admin_email ? __( 'Voir la commande', 'bb-woo-mail-layout' ) : __( 'Voir ma commande', 'bb-woo-mail-layout' ),
			);

			$tracking = $this->tracking_url( $order );
			if ( '' !== $tracking ) {
				$values['{tracking_url}'] = array(
					'url'   => $tracking,
					'label' => __( 'Suivre mon colis', 'bb-woo-mail-layout' ),
				);
			}
		} elseif ( $user ) {
			$values['{customer_first_name}'] = $user->first_name ? $user->first_name : $user->display_name;
			$values['{customer_last_name}']  = (string) $user->last_name;
		}

		/**
		 * Ajoute ou modifie des placeholders.
		 *
		 * @param array<string, mixed> $values Placeholder => valeur (texte brut) ou lien [ 'url' => …, 'label' => … ].
		 * @param \WC_Email|null                                        $email  E-mail en cours de rendu.
		 *
		 * @since 1.0.0
		 */
		return (array) apply_filters( 'bb_email_placeholders', $values, $email );
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
			if ( is_array( $value ) ) {
				$url                 = is_string( $value['url'] ?? null ) ? $value['url'] : '';
				$label               = is_string( $value['label'] ?? null ) ? $value['label'] : $url;
				$map[ $placeholder ] = '' === $url ? '' : sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
			} else {
				$map[ $placeholder ] = esc_html( (string) $value );
			}
		}
		$html = strtr( $html, $map );

		// « Bonjour , » quand le prénom est vide.
		return (string) preg_replace( '/[ \x{00A0}]+([,.])/u', '$1', $html );
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
