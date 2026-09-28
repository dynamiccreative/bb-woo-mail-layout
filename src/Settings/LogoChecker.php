<?php
/**
 * Vérifie que l'URL du logo répond en 200 (bug constaté : logo hébergé sur un domaine tiers cassé).
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Contrôle à l'enregistrement, à l'import et quotidiennement (cron).
 */
final class LogoChecker {

	public const CRON_HOOK = 'bb_wml_daily_logo_check';

	public const STATUS_OPTION = 'bb_woo_mail_layout_logo_status';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( self::class, 'run' ) );
	}

	/**
	 * Vérifie le logo réglé et mémorise le résultat.
	 */
	public static function run(): void {
		$url = (string) Options::get( 'logo_url' );
		if ( '' === $url || ! Options::is_on( 'show_logo' ) ) {
			delete_option( self::STATUS_OPTION );
			return;
		}
		update_option( self::STATUS_OPTION, self::check( $url ), false );
	}

	/**
	 * Dernier résultat.
	 *
	 * @return array{url:string,state:string,code:int,message:string,checked:int}|null
	 */
	public static function status(): ?array {
		$status = get_option( self::STATUS_OPTION );
		return is_array( $status ) && isset( $status['url'], $status['state'], $status['message'] ) ? $status : null;
	}

	/**
	 * Contrôle HTTP d'une URL d'image.
	 *
	 * @param string $url URL du logo.
	 * @return array{url:string,state:string,code:int,message:string,checked:int} state : ok | error | unverified.
	 */
	public static function check( string $url ): array {
		$result = array(
			'url'     => $url,
			'state'   => 'error',
			'code'    => 0,
			'message' => '',
			'checked' => time(),
		);

		$args     = array(
			'timeout'     => 8,
			'redirection' => 3,
		);
		$response = wp_safe_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Certains serveurs refusent HEAD : on retente en GET (réponse tronquée).
		if ( is_wp_error( $response ) || in_array( $code, array( 403, 405, 501 ), true ) ) {
			$response = wp_safe_remote_get( $url, $args + array( 'limit_response_size' => 2048 ) );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( is_wp_error( $response ) ) {
			if ( null !== self::local_path( $url ) ) {
				$result['state']   = 'unverified';
				$result['message'] = __( 'Le site n’a pas pu se joindre lui-même pour vérifier le logo, mais le fichier existe bien sur le serveur.', 'bb-woo-mail-layout' );
				return $result;
			}
			/* translators: %s: message d'erreur HTTP. */
			$result['message'] = sprintf( __( 'Le logo est injoignable (%s). Les e-mails s’afficheront avec une image cassée.', 'bb-woo-mail-layout' ), $response->get_error_message() );
			return $result;
		}

		$result['code'] = $code;
		if ( 200 !== $code ) {
			/* translators: %d: code HTTP. */
			$result['message'] = sprintf( __( 'L’URL du logo répond %d au lieu de 200. Les e-mails s’afficheront avec une image cassée.', 'bb-woo-mail-layout' ), $code );
			return $result;
		}

		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( '' !== $type && ! str_starts_with( strtolower( $type ), 'image/' ) ) {
			/* translators: %s: type MIME. */
			$result['message'] = sprintf( __( 'L’URL du logo ne renvoie pas une image (%s).', 'bb-woo-mail-layout' ), $type );
			return $result;
		}

		$result['state']   = 'ok';
		$result['message'] = __( 'Logo vérifié : l’image répond correctement.', 'bb-woo-mail-layout' );
		return $result;
	}

	/**
	 * Chemin du fichier local correspondant à une URL de ce site (médiathèque ou arborescence WordPress).
	 *
	 * @param string $url URL.
	 */
	public static function local_path( string $url ): ?string {
		// Un domaine tiers mort ne doit jamais passer pour un fichier local.
		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return null;
		}

		$uploads = wp_get_upload_dir();
		$map     = array(
			trailingslashit( $uploads['baseurl'] ) => trailingslashit( $uploads['basedir'] ),
			trailingslashit( site_url() )          => trailingslashit( ABSPATH ),
		);
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );

		foreach ( $map as $base_url => $base_dir ) {
			$base_path = (string) wp_parse_url( $base_url, PHP_URL_PATH );
			if ( str_starts_with( $path, $base_path ) ) {
				$file = wp_normalize_path( $base_dir . rawurldecode( substr( $path, strlen( $base_path ) ) ) );
				if ( ! str_contains( $file, '..' ) && is_file( $file ) ) {
					return $file;
				}
			}
		}
		return null;
	}
}
