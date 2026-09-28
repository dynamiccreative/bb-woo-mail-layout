<?php
/**
 * Prévisualisation : rendu HTML d'un e-mail avec une commande réelle, affiché dans une iframe sandboxée.
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
 * Endpoint admin-ajax. Le HTML est injecté côté client en `srcdoc` d'une iframe `sandbox=""` (aucun JS exécuté).
 */
final class Preview {

	public const ACTION = 'bb_wml_preview';

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
	 * Renvoie le HTML de l'e-mail.
	 */
	public function handle(): void {
		if ( ! check_ajax_referer( SettingsPage::NONCE_ACTION, 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			self::respond( esc_html__( 'Accès refusé.', 'bb-woo-mail-layout' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessus.
		$email_id = Options::sanitize_email_id( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' );
		$order_id = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		// phpcs:enable

		$simulator = new Simulator( $this->registry );
		try {
			$html = $simulator->render( $simulator->prepare( $email_id, $order_id ) );
		} catch ( \Throwable $e ) {
			self::respond( esc_html( $e->getMessage() ), 422 );
		}

		self::respond( $html );
	}

	/**
	 * Sortie HTML brute, scripts interdits.
	 *
	 * @param string $html   HTML.
	 * @param int    $status Code HTTP.
	 */
	private static function respond( string $html, int $status = 200 ): never {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( "Content-Security-Policy: script-src 'none'; object-src 'none'" );
		header( 'X-Robots-Tag: noindex' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de l'e-mail, affiché en iframe sandboxée.
		exit;
	}
}
