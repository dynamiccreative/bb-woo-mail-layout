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
	 * Textes rédigés, indexés par identifiant : e-mails natifs puis extensions courantes.
	 *
	 * @return array<string, string>
	 */
	public function by_id(): array {
		return array_merge( $this->native(), $this->extensions() );
	}

	/**
	 * Textes des e-mails natifs.
	 *
	 * @return array<string, string>
	 */
	private function native(): array {
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
	 * Textes des e-mails de WooCommerce Subscriptions, Bookings et Memberships.
	 *
	 * Identifiants relevés dans le code des extensions (Memberships : l'identifiant est le nom de la classe).
	 * Bookings : booking_pending_confirmation et booking_notification sont déduits de la convention de nommage.
	 *
	 * @return array<string, string>
	 */
	private function extensions(): array {
		return array(
			// WooCommerce Subscriptions : administrateur.
			'new_renewal_order'                          => __( "Nouvelle commande de renouvellement d’abonnement de {customer_first_name} {customer_last_name}.\n\nCommande n° {order_number} du {order_date}, d’un montant de {order_total}. {order_url}", 'bb-woo-mail-layout' ),
			'new_switch_order'                           => __( '{customer_first_name} {customer_last_name} a modifié son abonnement (commande n° {order_number}, {order_total}). {order_url}', 'bb-woo-mail-layout' ),
			'cancelled_subscription'                     => __( 'L’abonnement n° {order_number} de {customer_first_name} {customer_last_name} a été annulé. {order_url}', 'bb-woo-mail-layout' ),
			'expired_subscription'                       => __( 'L’abonnement n° {order_number} de {customer_first_name} {customer_last_name} est arrivé à expiration. {order_url}', 'bb-woo-mail-layout' ),
			'suspended_subscription'                     => __( 'L’abonnement n° {order_number} de {customer_first_name} {customer_last_name} a été suspendu. {order_url}', 'bb-woo-mail-layout' ),
			'reactivated_subscription'                   => __( 'L’abonnement n° {order_number} de {customer_first_name} {customer_last_name} a été réactivé. {order_url}', 'bb-woo-mail-layout' ),
			'payment_retry'                              => __( 'Le paiement du renouvellement n° {order_number} de {customer_first_name} {customer_last_name} a échoué. Une nouvelle tentative de paiement automatique est programmée. {order_url}', 'bb-woo-mail-layout' ),

			// WooCommerce Subscriptions : client.
			'customer_processing_renewal_order'          => __( "Bonjour {customer_first_name},\n\nMerci ! Le renouvellement de votre abonnement est bien enregistré (commande n° {order_number}). Vous en trouverez le récapitulatif ci-dessous.", 'bb-woo-mail-layout' ),
			'customer_completed_renewal_order'           => __( "Bonjour {customer_first_name},\n\nVotre commande de renouvellement n° {order_number} est terminée. Merci pour votre fidélité !", 'bb-woo-mail-layout' ),
			'customer_on_hold_renewal_order'             => __( "Bonjour {customer_first_name},\n\nNous avons bien reçu votre commande de renouvellement n° {order_number}. Elle est en attente jusqu’à la confirmation de votre paiement.", 'bb-woo-mail-layout' ),
			'customer_completed_switch_order'            => __( "Bonjour {customer_first_name},\n\nLa modification de votre abonnement est bien prise en compte. Vous trouverez ci-dessous le détail de la commande n° {order_number}.", 'bb-woo-mail-layout' ),
			'customer_renewal_invoice'                   => __( "Bonjour {customer_first_name},\n\nLe renouvellement de votre abonnement est à régler (commande n° {order_number}, {order_total}). Vous pouvez le payer dès maintenant : {payment_url}", 'bb-woo-mail-layout' ),
			'customer_payment_retry'                     => __( "Bonjour {customer_first_name},\n\nLe paiement du renouvellement de votre abonnement (commande n° {order_number}) n’a pas abouti. Une nouvelle tentative sera faite automatiquement ; vous pouvez aussi régler la commande dès maintenant : {payment_url}", 'bb-woo-mail-layout' ),
			'customer_notification_auto_renewal'         => __( "Bonjour {customer_first_name},\n\nPetit rappel : votre abonnement n° {order_number} sera renouvelé automatiquement le {next_payment_date}. Aucune action n’est nécessaire de votre part. {order_url}", 'bb-woo-mail-layout' ),
			'customer_notification_manual_renewal'       => __( "Bonjour {customer_first_name},\n\nVotre abonnement n° {order_number} arrive à échéance le {next_payment_date}. Pensez à le renouveler depuis votre espace client pour ne pas l’interrompre. {order_url}", 'bb-woo-mail-layout' ),
			'customer_notification_auto_trial_expiry'    => __( "Bonjour {customer_first_name},\n\nVotre période d’essai se termine bientôt. Votre abonnement n° {order_number} se poursuivra automatiquement, avec un premier paiement le {next_payment_date}. {order_url}", 'bb-woo-mail-layout' ),
			'customer_notification_manual_trial_expiry'  => __( "Bonjour {customer_first_name},\n\nVotre période d’essai se termine bientôt. Pour poursuivre votre abonnement n° {order_number}, un paiement sera nécessaire le {next_payment_date}. {order_url}", 'bb-woo-mail-layout' ),
			'customer_notification_subscription_expiry'  => __( "Bonjour {customer_first_name},\n\nVotre abonnement n° {order_number} prendra fin le {subscription_end_date}. {order_url}", 'bb-woo-mail-layout' ),

			// WooCommerce Bookings.
			'new_booking'                                => __( "Nouvelle réservation de {customer_first_name} {customer_last_name} : « {booking_product} », le {booking_date}.\n\nSi elle doit être confirmée, validez-la depuis l’administration. {order_url}", 'bb-woo-mail-layout' ),
			'admin_booking_cancelled'                    => __( 'La réservation de {customer_first_name} {customer_last_name} (« {booking_product} », le {booking_date}) a été annulée. {order_url}', 'bb-woo-mail-layout' ),
			'booking_confirmed'                          => __( "Bonjour {customer_first_name},\n\nBonne nouvelle : votre réservation « {booking_product} » du {booking_date} est confirmée. Vous en trouverez le détail ci-dessous.", 'bb-woo-mail-layout' ),
			'booking_cancelled'                          => __( "Bonjour {customer_first_name},\n\nVotre réservation « {booking_product} » du {booking_date} a été annulée. S’il s’agit d’une erreur, n’hésitez pas à nous contacter.", 'bb-woo-mail-layout' ),
			'booking_reminder'                           => __( "Bonjour {customer_first_name},\n\nPetit rappel : votre réservation « {booking_product} » a lieu le {booking_date}. Nous avons hâte de vous accueillir !", 'bb-woo-mail-layout' ),
			'booking_pending_confirmation'               => __( "Bonjour {customer_first_name},\n\nNous avons bien reçu votre demande de réservation « {booking_product} » pour le {booking_date}. Elle est en attente de confirmation : vous recevrez un e-mail dès qu’elle sera validée.", 'bb-woo-mail-layout' ),
			'booking_notification'                       => __( "Bonjour {customer_first_name},\n\nVoici un message concernant votre réservation « {booking_product} » du {booking_date} :", 'bb-woo-mail-layout' ),

			// WooCommerce Memberships.
			'WC_Memberships_User_Membership_Activated_Email' => __( "Bonjour {customer_first_name},\n\nVotre adhésion « {membership_plan} » est désormais active. Profitez dès maintenant de vos avantages membres sur {site_title}.", 'bb-woo-mail-layout' ),
			'WC_Memberships_User_Membership_Ending_Soon_Email' => __( "Bonjour {customer_first_name},\n\nVotre adhésion « {membership_plan} » arrive bientôt à échéance. Renouvelez-la pour continuer à profiter de vos avantages.", 'bb-woo-mail-layout' ),
			'WC_Memberships_User_Membership_Ended_Email' => __( "Bonjour {customer_first_name},\n\nVotre adhésion « {membership_plan} » a pris fin. Vous pouvez la renouveler à tout moment depuis votre espace client.", 'bb-woo-mail-layout' ),
			'WC_Memberships_User_Membership_Renewal_Reminder_Email' => __( "Bonjour {customer_first_name},\n\nVotre adhésion « {membership_plan} » a pris fin il y a quelque temps. Envie de retrouver vos avantages membres ? Renouvelez-la en quelques clics.", 'bb-woo-mail-layout' ),
			'WC_Memberships_User_Membership_Note_Email'  => __( "Bonjour {customer_first_name},\n\nUne note a été ajoutée à votre adhésion « {membership_plan} » :", 'bb-woo-mail-layout' ),
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
