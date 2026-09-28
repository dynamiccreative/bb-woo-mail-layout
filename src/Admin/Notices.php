<?php
/**
 * Notices admin : logo injoignable, plugin tiers de personnalisation d'e-mails actif.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Admin;

use BB\WooMailLayout\Settings\LogoChecker;
use BB\WooMailLayout\Settings\Options;
use BB\WooMailLayout\Settings\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Affichées aux utilisateurs ayant la capacité manage_woocommerce.
 */
final class Notices {

	/** Fragments de dossiers de plugins connus pour remplacer les templates d'e-mails WooCommerce. */
	private const CONFLICT_PATTERNS = array(
		'email-customizer',
		'yaymail',
		'email-designer',
		'decorator',
		'email-template-customizer',
		'mailpoet-woocommerce-email',
	);

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Affichage.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$this->logo_notice();
		$this->conflict_notice();
	}

	/**
	 * Logo en erreur.
	 */
	private function logo_notice(): void {
		$status = LogoChecker::status();
		if ( ! $status || 'error' !== $status['state'] || ! Options::is_on( 'show_logo' ) || Options::get( 'logo_url' ) !== $status['url'] ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'BB Woo Mail Layout :', 'bb-woo-mail-layout' ),
			esc_html( $status['message'] ),
			esc_url( SettingsPage::url() ),
			esc_html__( 'Corriger le logo', 'bb-woo-mail-layout' )
		);
	}

	/**
	 * Plugin concurrent actif.
	 */
	private function conflict_notice(): void {
		$conflicts = $this->conflicting_plugins();
		if ( ! $conflicts ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'BB Woo Mail Layout :', 'bb-woo-mail-layout' ),
			esc_html(
				sprintf(
					/* translators: %s: liste de plugins. */
					__( 'ces extensions modifient aussi les e-mails WooCommerce et peuvent entrer en conflit : %s. Désactivez-les après avoir reporté leurs réglages (voir la procédure de migration).', 'bb-woo-mail-layout' ),
					implode( ', ', $conflicts )
				)
			)
		);
	}

	/**
	 * Noms des plugins actifs en conflit.
	 *
	 * @return string[]
	 */
	private function conflicting_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/**
		 * Fragments de chemins de plugins considérés comme concurrents.
		 *
		 * @param string[] $patterns Fragments.
		 *
		 * @since 1.0.0
		 */
		$patterns = (array) apply_filters( 'bb_email_conflicting_plugins', self::CONFLICT_PATTERNS );
		$plugins  = get_plugins();
		$names    = array();

		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			foreach ( $patterns as $pattern ) {
				if ( is_string( $file ) && str_contains( strtolower( $file ), (string) $pattern ) ) {
					$names[] = $plugins[ $file ]['Name'] ?? $file;
					break;
				}
			}
		}
		return array_unique( $names );
	}
}
