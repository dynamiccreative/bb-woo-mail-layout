<?php
/**
 * Textes d'introduction français rédigés, par e-mail.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Email;

defined( 'ABSPATH' ) || exit;

/**
 * Intro par défaut (surchargeable dans l'admin puis par le filtre `bb_email_intro_text`).
 *
 * Une ligne vide sépare deux paragraphes ; un retour à la ligne simple donne un <br>.
 */
final class DefaultTexts {

	/**
	 * Texte par défaut pour un e-mail (tient compte de son état : remboursement partiel, paiement dû…).
	 *
	 * @param \WC_Email $email E-mail.
	 */
	public function for_email( \WC_Email $email ): string {
		$order = $email->object instanceof \WC_Order ? $email->object : null;

		if ( 'customer_refunded_order' === $email->id && ! empty( $email->partial_refund ) ) {
			return __( "Bonjour {customer_first_name},\n\nVotre commande n° {order_number} a été partiellement remboursée. Le montant sera crédité sur votre moyen de paiement d’origine dans les prochains jours.", 'bb-woo-mail-layout' );
		}

		if ( 'customer_invoice' === $email->id && $order && $order->needs_payment() ) {
			return $order->has_status( 'failed' )
				? __( "Bonjour {customer_first_name},\n\nLe paiement de votre commande n° {order_number} du {order_date} n’a pas abouti. Vous pouvez le relancer à tout moment grâce au bouton ci-dessous.", 'bb-woo-mail-layout' )
				: __( "Bonjour {customer_first_name},\n\nUne commande a été créée pour vous sur {site_title}. Vous en trouverez le détail ci-dessous, ainsi qu’un lien pour la régler quand vous le souhaitez.", 'bb-woo-mail-layout' );
		}

		$texts = $this->by_id();
		if ( isset( $texts[ $email->id ] ) ) {
			return $texts[ $email->id ];
		}

		return $this->generic( $email->is_customer_email() );
	}

	/**
	 * Textes des e-mails natifs, indexés par identifiant.
	 *
	 * @return array<string, string>
	 */
	public function by_id(): array {
		return array(
			'new_order'                 => __( "Vous avez reçu une nouvelle commande de {customer_first_name} {customer_last_name}.\n\nCommande n° {order_number} du {order_date}, d’un montant de {order_total}. {order_url}", 'bb-woo-mail-layout' ),
			'cancelled_order'           => __( 'La commande n° {order_number} de {customer_first_name} {customer_last_name} a été annulée. {order_url}', 'bb-woo-mail-layout' ),
			'failed_order'              => __( 'Le paiement de la commande n° {order_number} de {customer_first_name} {customer_last_name} a échoué. La commande est en attente de paiement. {order_url}', 'bb-woo-mail-layout' ),
			'customer_on_hold_order'    => __( "Bonjour {customer_first_name},\n\nNous avons bien reçu votre commande n° {order_number}. Elle est en attente jusqu’à la confirmation de votre paiement. Vous en trouverez le récapitulatif ci-dessous.", 'bb-woo-mail-layout' ),
			'customer_processing_order' => __( "Bonjour {customer_first_name},\n\nMerci pour votre commande ! Nous avons bien reçu votre commande n° {order_number} et nous la préparons dès maintenant. Vous en trouverez le récapitulatif ci-dessous.", 'bb-woo-mail-layout' ),
			'customer_completed_order'  => __( "Bonjour {customer_first_name},\n\nBonne nouvelle : votre commande n° {order_number} est terminée. Si elle doit vous être livrée, elle est désormais en route. {tracking_url}\n\nMerci pour votre confiance !", 'bb-woo-mail-layout' ),
			'customer_cancelled_order'  => __( "Bonjour {customer_first_name},\n\nVotre commande n° {order_number} a été annulée. S’il s’agit d’une erreur, n’hésitez pas à nous contacter.", 'bb-woo-mail-layout' ),
			'customer_failed_order'     => __( "Bonjour {customer_first_name},\n\nLe paiement de votre commande n° {order_number} n’a malheureusement pas abouti. Vous pouvez réessayer depuis votre espace client ou nous contacter si le problème persiste.", 'bb-woo-mail-layout' ),
			'customer_refunded_order'   => __( "Bonjour {customer_first_name},\n\nVotre commande n° {order_number} a été remboursée. Le montant sera crédité sur votre moyen de paiement d’origine dans les prochains jours.", 'bb-woo-mail-layout' ),
			'customer_note'             => __( "Bonjour {customer_first_name},\n\nUne note a été ajoutée à votre commande n° {order_number} :", 'bb-woo-mail-layout' ),
			'customer_invoice'          => __( "Bonjour {customer_first_name},\n\nVoici le détail de votre commande n° {order_number} passée le {order_date}.", 'bb-woo-mail-layout' ),
			'customer_new_account'      => __( "Bonjour {customer_first_name},\n\nMerci d’avoir créé votre compte sur {site_title}. Depuis votre espace client, vous pouvez suivre vos commandes et gérer vos informations personnelles.", 'bb-woo-mail-layout' ),
			'customer_reset_password'   => __( "Bonjour {customer_first_name},\n\nNous avons reçu une demande de réinitialisation du mot de passe de votre compte sur {site_title}. Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail : votre mot de passe restera inchangé.", 'bb-woo-mail-layout' ),
		);
	}

	/**
	 * Intro générique pour les e-mails d'extensions tierces, construite depuis leur titre.
	 *
	 * @param bool $customer Vrai si l'e-mail est destiné au client.
	 */
	public function generic( bool $customer ): string {
		return $customer
			? __( "Bonjour {customer_first_name},\n\nVous trouverez ci-dessous les informations concernant : « {email_title} ».", 'bb-woo-mail-layout' )
			: __( 'Notification de la boutique {site_title} : « {email_title} ».', 'bb-woo-mail-layout' );
	}
}
