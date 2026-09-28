<?php
/**
 * Découverte des e-mails WooCommerce (natifs + extensions) et statut d'activation.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Email;

use BB\WooMailLayout\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registre dynamique : aucune liste codée en dur.
 */
final class EmailRegistry {

	/** E-mails natifs WooCommerce qui disposent d'un texte d'intro rédigé. */
	public const NATIVE_IDS = array(
		'new_order',
		'cancelled_order',
		'failed_order',
		'customer_on_hold_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_cancelled_order',
		'customer_failed_order',
		'customer_refunded_order',
		'customer_note',
		'customer_invoice',
		'customer_new_account',
		'customer_reset_password',
	);

	/**
	 * Cache par requête.
	 *
	 * @var array<string, array{id:string,title:string,class:string,customer:bool,source:string,native:bool}>|null
	 */
	private ?array $cache = null;

	/**
	 * Tous les e-mails déclarés dans WC()->mailer()->get_emails().
	 *
	 * @return array<string, array{id:string,title:string,class:string,customer:bool,source:string,native:bool}>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$this->cache = array();
		foreach ( $this->wc_emails() as $class => $email ) {
			if ( ! $email instanceof \WC_Email || '' === (string) $email->id ) {
				continue;
			}
			$this->cache[ $email->id ] = array(
				'id'       => (string) $email->id,
				'title'    => $email->get_title(),
				'class'    => (string) $class,
				'customer' => $email->is_customer_email(),
				'source'   => $this->source_of( $email ),
				'native'   => in_array( $email->id, self::NATIVE_IDS, true ),
			);
		}
		return $this->cache;
	}

	/**
	 * Instance WC_Email par identifiant.
	 *
	 * @param string $id Identifiant (ex. customer_processing_order).
	 */
	public function get( string $id ): ?\WC_Email {
		foreach ( $this->wc_emails() as $email ) {
			if ( $email instanceof \WC_Email && $email->id === $id ) {
				return $email;
			}
		}
		return null;
	}

	/**
	 * Le layout du plugin s'applique-t-il à cet e-mail ?
	 *
	 * @param string $id Identifiant de l'e-mail.
	 */
	public function is_enabled( string $id ): bool {
		$emails  = Options::get( 'emails' );
		$enabled = 'no' !== ( is_array( $emails ) ? ( $emails[ $id ] ?? 'yes' ) : 'yes' );

		/**
		 * Force l'activation / la désactivation du layout pour un e-mail.
		 *
		 * @param bool   $enabled Statut issu des réglages.
		 * @param string $id      Identifiant de l'e-mail.
		 *
		 * @since 1.0.0
		 */
		return (bool) apply_filters( 'bb_email_enabled', $enabled, $id );
	}

	/**
	 * E-mails WooCommerce (tableau alimenté par les extensions : contenu non garanti).
	 *
	 * @return array<string, mixed>
	 */
	private function wc_emails(): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		/**
		 * Liste brute, potentiellement modifiée par des extensions.
		 *
		 * @var mixed $emails
		 */
		$emails = WC()->mailer()->get_emails();
		return is_array( $emails ) ? $emails : array();
	}

	/**
	 * Nom du plugin (ou thème) qui déclare la classe de l'e-mail.
	 *
	 * @param \WC_Email $email E-mail.
	 */
	private function source_of( \WC_Email $email ): string {
		try {
			$file = ( new \ReflectionClass( $email ) )->getFileName();
		} catch ( \ReflectionException $e ) {
			return '';
		}
		if ( ! $file ) {
			return '';
		}

		$file        = wp_normalize_path( $file );
		$plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );

		if ( str_starts_with( $file, $plugins_dir ) ) {
			$slug = strtok( substr( $file, strlen( $plugins_dir ) ), '/' );
			return $this->plugin_name( (string) $slug );
		}
		if ( str_starts_with( $file, wp_normalize_path( WPMU_PLUGIN_DIR ) ) ) {
			return __( 'Must-use plugin', 'bb-woo-mail-layout' );
		}
		if ( str_starts_with( $file, wp_normalize_path( get_theme_root() ) ) ) {
			return __( 'Thème', 'bb-woo-mail-layout' );
		}
		return '';
	}

	/**
	 * Nom lisible d'un dossier de plugin.
	 *
	 * @param string $slug Dossier du plugin.
	 */
	private function plugin_name( string $slug ): string {
		static $names = null;

		if ( null === $names ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$names = array();
			foreach ( get_plugins() as $path => $data ) {
				$names[ strtok( $path, '/' ) ] = $data['Name'];
			}
		}
		return $names[ $slug ] ?? $slug;
	}
}
