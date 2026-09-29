<?php
/**
 * Envoi d'un e-mail de test via le pipeline réel de WooCommerce (WC_Email::send → wp_mail → SMTP configuré).
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Admin;

use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Email\Simulator;
use BB\WooMailLayout\Settings\Options;
use BB\WooMailLayout\Settings\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoint admin-ajax.
 */
final class TestEmail {

	public const ACTION = 'bb_wml_send_test';

	/**
	 * Dernière erreur wp_mail.
	 *
	 * @var string
	 */
	private string $mail_error = '';

	/**
	 * Constructeur.
	 *
	 * @param EmailRegistry $registry Registre des e-mails.
	 */
	public function __construct( private EmailRegistry $registry ) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Envoie l'e-mail choisi à l'adresse indiquée.
	 */
	public function handle(): void {
		check_ajax_referer( SettingsPage::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'bb-woo-mail-layout' ) ), 403 );
		}

		$email_id = Options::sanitize_email_id( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
		$order_id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		$to       = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$to       = '' !== $to ? $to : (string) get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Adresse destinataire invalide.', 'bb-woo-mail-layout' ) ) );
		}

		DraftSettings::apply_from_request();

		$simulator = new Simulator( $this->registry );
		add_action( 'wp_mail_failed', array( $this, 'capture_error' ) );

		try {
			$email = $simulator->prepare( $email_id, $order_id );
			$email->setup_locale();
			try {
				/* translators: %s: sujet de l'e-mail. */
				$subject = sprintf( __( '[Test] %s', 'bb-woo-mail-layout' ), $email->get_subject() );
				$sent    = $email->send( $to, $subject, $email->get_content(), $email->get_headers(), $email->get_attachments() );
			} finally {
				$email->restore_locale();
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		if ( ! $sent ) {
			wp_send_json_error(
				array(
					'message' => '' !== $this->mail_error
						/* translators: %s: erreur renvoyée par wp_mail. */
						? sprintf( __( 'Échec de l’envoi : %s', 'bb-woo-mail-layout' ), $this->mail_error )
						: __( 'Échec de l’envoi (wp_mail a renvoyé false). Vérifiez la configuration SMTP.', 'bb-woo-mail-layout' ),
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: 1: titre de l'e-mail, 2: destinataire. */
				'message' => sprintf( __( 'E-mail « %1$s » envoyé à %2$s.', 'bb-woo-mail-layout' ), $email->get_title(), $to ),
			)
		);
	}

	/**
	 * Mémorise l'erreur wp_mail.
	 *
	 * @param mixed $error Erreur transmise par wp_mail_failed (WP_Error attendu).
	 */
	public function capture_error( $error ): void {
		if ( $error instanceof \WP_Error ) {
			$this->mail_error = $error->get_error_message();
		}
	}
}
