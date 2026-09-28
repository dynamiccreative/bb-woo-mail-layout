<?php
/**
 * Import / export des réglages en JSON (duplication d'un site à l'autre).
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Export téléchargeable, import validé (schéma strict, clés inconnues refusées, aucune exécution).
 */
final class ImportExport {

	public const EXPORT_ACTION = 'bb_wml_export';

	public const IMPORT_ACTION = 'bb_wml_import';

	private const PLUGIN_ID = 'bb-woo-mail-layout';

	private const MAX_BYTES = 262144;

	/** Clés autorisées à la racine du fichier. */
	private const ROOT_KEYS = array( 'plugin', 'schema', 'version', 'exported_at', 'source', 'settings' );

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'wp_ajax_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
	}

	/**
	 * Téléchargement du JSON.
	 */
	public function handle_export(): void {
		check_admin_referer( self::EXPORT_ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'bb-woo-mail-layout' ), '', array( 'response' => 403 ) );
		}

		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$filename = sprintf( 'bb-woo-mail-layout-%s-%s.json', sanitize_file_name( $host ), gmdate( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( self::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON téléchargé.
		exit;
	}

	/**
	 * Import AJAX.
	 */
	public function handle_import(): void {
		check_ajax_referer( SettingsPage::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'bb-woo-mail-layout' ) ), 403 );
		}

		$payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validé par import().
		$errors  = self::import( is_string( $payload ) ? $payload : '' );

		if ( $errors ) {
			wp_send_json_error(
				array(
					'message' => __( 'Import refusé :', 'bb-woo-mail-layout' ) . ' ' . implode( ' ', $errors ),
					'errors'  => $errors,
				)
			);
		}
		wp_send_json_success( array( 'message' => __( 'Réglages importés.', 'bb-woo-mail-layout' ) ) );
	}

	/**
	 * Contenu exporté. logo_id est propre au site : il est recalculé à l'import.
	 *
	 * @return array<string,mixed>
	 */
	public static function export_data(): array {
		$settings = Options::all();
		unset( $settings['_version'], $settings['logo_id'] );

		return array(
			'plugin'      => self::PLUGIN_ID,
			'schema'      => Options::SCHEMA_VERSION,
			'version'     => BB_WML_VERSION,
			'exported_at' => gmdate( 'c' ),
			'source'      => home_url( '/' ),
			'settings'    => $settings,
		);
	}

	/**
	 * Valide puis applique un export. Rien n'est modifié si une erreur est trouvée.
	 *
	 * @param string $json Contenu du fichier.
	 * @return string[] Erreurs (vide = succès).
	 */
	public static function import( string $json ): array {
		$errors = self::validate( $json );
		if ( $errors ) {
			return $errors;
		}

		$settings            = json_decode( $json, true )['settings'];
		$settings['logo_id'] = '' !== (string) ( $settings['logo_url'] ?? '' ) ? attachment_url_to_postid( (string) $settings['logo_url'] ) : 0;

		Options::replace( $settings );
		LogoChecker::run();
		return array();
	}

	/**
	 * Validation de schéma.
	 *
	 * @param string $json Contenu du fichier.
	 * @return string[] Erreurs.
	 */
	public static function validate( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array( __( 'Fichier vide.', 'bb-woo-mail-layout' ) );
		}
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return array( __( 'Fichier trop volumineux.', 'bb-woo-mail-layout' ) );
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array( __( 'JSON invalide.', 'bb-woo-mail-layout' ) );
		}
		if ( ( $data['plugin'] ?? '' ) !== self::PLUGIN_ID ) {
			return array( __( 'Ce fichier n’est pas un export BB Woo Mail Layout.', 'bb-woo-mail-layout' ) );
		}

		$errors = array();

		$unknown_root = array_diff( array_keys( $data ), self::ROOT_KEYS );
		if ( $unknown_root ) {
			/* translators: %s: liste de clés. */
			$errors[] = sprintf( __( 'Clés inconnues : %s.', 'bb-woo-mail-layout' ), implode( ', ', $unknown_root ) );
		}
		if ( ! is_int( $data['schema'] ?? null ) || $data['schema'] > Options::SCHEMA_VERSION ) {
			$errors[] = __( 'Version de schéma non prise en charge : mettez le plugin à jour sur ce site.', 'bb-woo-mail-layout' );
		}
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$errors[] = __( 'Section « settings » manquante.', 'bb-woo-mail-layout' );
			return $errors;
		}

		$schema  = Options::schema();
		$unknown = array_diff( array_keys( $data['settings'] ), array_keys( $schema ) );
		if ( $unknown ) {
			/* translators: %s: liste de clés. */
			$errors[] = sprintf( __( 'Réglages inconnus : %s.', 'bb-woo-mail-layout' ), implode( ', ', $unknown ) );
		}

		foreach ( $data['settings'] as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}
			$is_map = str_starts_with( $schema[ $key ], 'map_' );
			$valid  = $is_map
				? is_array( $value ) && count( array_filter( $value, 'is_scalar' ) ) === count( $value )
				: is_scalar( $value );
			if ( ! $valid ) {
				/* translators: %s: clé de réglage. */
				$errors[] = sprintf( __( 'Type invalide pour « %s ».', 'bb-woo-mail-layout' ), $key );
			}
		}

		return $errors;
	}
}
