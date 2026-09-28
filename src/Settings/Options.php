<?php
/**
 * Accès typé à l'option unique `bb_woo_mail_layout`.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Settings;

use BB\WooMailLayout\Email\LayoutRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture, valeurs par défaut, assainissement et migration des réglages.
 */
final class Options {

	/** Version du schéma d'options (incrémenter + ajouter une étape dans migrate()). */
	public const SCHEMA_VERSION = 1;

	/** Polices sûres pour l'e-mail. */
	public const FONTS = array(
		'arial'     => 'Arial, Helvetica, sans-serif',
		'helvetica' => "'Helvetica Neue', Helvetica, Arial, sans-serif",
		'georgia'   => "Georgia, 'Times New Roman', Times, serif",
		'verdana'   => 'Verdana, Geneva, sans-serif',
		'trebuchet' => "'Trebuchet MS', Helvetica, Arial, sans-serif",
		'google'    => '',
	);

	public const SOCIAL_NETWORKS = array( 'facebook', 'instagram', 'linkedin', 'tiktok', 'youtube' );

	/**
	 * Schéma : clé => type d'assainissement.
	 *
	 * @return array<string, string>
	 */
	public static function schema(): array {
		return array(
			'layout'                => 'layout',
			'show_logo'             => 'bool',
			'logo_url'              => 'url',
			'logo_id'               => 'int',
			'logo_max_width'        => 'logo_width',
			'color_primary'         => 'color',
			'color_button'          => 'color',
			'color_text'            => 'color',
			'color_background'      => 'color',
			'font'                  => 'font',
			'google_font'           => 'google_font',
			'show_intro'            => 'bool',
			'show_help'             => 'bool',
			'show_social'           => 'bool',
			'show_footer'           => 'bool',
			'contact_phone'         => 'text',
			'contact_email'         => 'email',
			'contact_hours'         => 'text',
			'social_facebook'       => 'url',
			'social_instagram'      => 'url',
			'social_linkedin'       => 'url',
			'social_tiktok'         => 'url',
			'social_youtube'        => 'url',
			'footer_legal_name'     => 'text',
			'footer_address'        => 'textarea',
			'footer_show_site_link' => 'bool',
			'footer_text'           => 'html',
			'emails'                => 'map_bool',
			'intros'                => 'map_textarea',
		);
	}

	/**
	 * Valeurs par défaut.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'_version'              => self::SCHEMA_VERSION,
			'layout'                => 'classique',
			'show_logo'             => 'yes',
			'logo_url'              => '',
			'logo_id'               => 0,
			'logo_max_width'        => 200,
			'color_primary'         => '#1f4e79',
			'color_button'          => '#1f4e79',
			'color_text'            => '#333333',
			'color_background'      => '#f3f4f6',
			'font'                  => 'arial',
			'google_font'           => '',
			'show_intro'            => 'yes',
			'show_help'             => 'yes',
			'show_social'           => 'yes',
			'show_footer'           => 'yes',
			'contact_phone'         => '',
			'contact_email'         => '',
			'contact_hours'         => '',
			'social_facebook'       => '',
			'social_instagram'      => '',
			'social_linkedin'       => '',
			'social_tiktok'         => '',
			'social_youtube'        => '',
			'footer_legal_name'     => '',
			'footer_address'        => '',
			'footer_show_site_link' => 'yes',
			'footer_text'           => '',
			'emails'                => array(),
			'intros'                => array(),
		);
	}

	/**
	 * Tous les réglages, fusionnés avec les valeurs par défaut.
	 *
	 * Pas de cache statique : sous WPML, get_option() renvoie les chaînes de la langue courante.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( BB_WML_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$all    = array_merge( self::defaults(), $stored );

		foreach ( array( 'emails', 'intros' ) as $map ) {
			$all[ $map ] = is_array( $all[ $map ] ) ? $all[ $map ] : array();
		}
		return $all;
	}

	/**
	 * Un réglage.
	 *
	 * @param string $key Clé.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Un booléen stocké en 'yes' / 'no'.
	 *
	 * @param string $key Clé.
	 */
	public static function is_on( string $key ): bool {
		return 'yes' === self::get( $key );
	}

	/**
	 * Remplace intégralement les réglages (après assainissement).
	 *
	 * @param array<string, mixed> $values Valeurs brutes.
	 */
	public static function replace( array $values ): void {
		$clean = array( '_version' => self::SCHEMA_VERSION );
		foreach ( self::schema() as $key => $type ) {
			$clean[ $key ] = array_key_exists( $key, $values )
				? self::sanitize_value( $type, $values[ $key ], self::defaults()[ $key ] )
				: self::defaults()[ $key ];
		}
		update_option( BB_WML_OPTION, $clean );
	}

	/**
	 * Assainit une valeur d'après sa clé. Utilisé par la page de réglages et l'import.
	 *
	 * @param string $key Clé du schéma.
	 * @param mixed  $raw Valeur brute (déjà déslashée).
	 * @return mixed
	 */
	public static function sanitize_field( string $key, $raw ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return null;
		}
		$value = self::sanitize_value( $schema[ $key ], $raw, self::defaults()[ $key ] );

		// Les tableaux par e-mail sont fusionnés : un e-mail absent du formulaire garde sa valeur.
		if ( in_array( $schema[ $key ], array( 'map_bool', 'map_textarea' ), true ) ) {
			$current = self::get( $key );
			$value   = array_merge( is_array( $current ) ? $current : array(), $value );
		}
		return $value;
	}

	/**
	 * Assainissement par type.
	 *
	 * @param string $type    Type du schéma.
	 * @param mixed  $raw     Valeur brute.
	 * @param mixed  $fallback Valeur par défaut.
	 * @return mixed
	 */
	public static function sanitize_value( string $type, $raw, $fallback ) {
		switch ( $type ) {
			case 'bool':
				return in_array( $raw, array( 'yes', '1', 1, true ), true ) ? 'yes' : 'no';

			case 'int':
				return is_numeric( $raw ) ? absint( $raw ) : 0;

			case 'logo_width':
				$width = is_numeric( $raw ) ? absint( $raw ) : (int) $fallback;
				return max( 50, min( 600, $width ) );

			case 'color':
				$color = is_string( $raw ) ? sanitize_hex_color( trim( $raw ) ) : null;
				return $color ? strtolower( $color ) : $fallback;

			case 'url':
				return is_string( $raw ) ? esc_url_raw( trim( $raw ), array( 'http', 'https' ) ) : '';

			case 'email':
				return is_string( $raw ) ? sanitize_email( $raw ) : '';

			case 'text':
				return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';

			case 'textarea':
				return is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';

			case 'html':
				return is_string( $raw ) ? trim( wp_kses( $raw, self::allowed_footer_html() ) ) : '';

			case 'layout':
				return is_string( $raw ) && array_key_exists( $raw, LayoutRenderer::available_layouts() ) ? $raw : $fallback;

			case 'font':
				return is_string( $raw ) && array_key_exists( $raw, self::FONTS ) ? $raw : $fallback;

			case 'google_font':
				return is_string( $raw ) ? trim( (string) preg_replace( '/[^A-Za-z0-9 ]/', '', $raw ) ) : '';

			case 'map_bool':
				$out = array();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $flag ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id ) {
						$out[ $id ] = in_array( $flag, array( 'yes', '1', 1, true ), true ) ? 'yes' : 'no';
					}
				}
				return $out;

			case 'map_textarea':
				$out = array();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $text ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id && is_scalar( $text ) ) {
						$out[ $id ] = sanitize_textarea_field( (string) $text );
					}
				}
				return $out;
		}
		return $fallback;
	}

	/**
	 * Identifiant d'e-mail WooCommerce (lettres, chiffres, _ et -).
	 *
	 * @param string $id Identifiant brut.
	 */
	public static function sanitize_email_id( string $id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $id );
	}

	/**
	 * Liste blanche HTML du pied de page : gras, lien, saut de ligne.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_footer_html(): array {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'br'     => array(),
			'p'      => array(),
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	/**
	 * Pile de polices CSS du réglage courant.
	 *
	 * @param array<string, mixed> $settings Réglages.
	 */
	public static function font_stack( array $settings ): string {
		if ( 'google' === $settings['font'] && '' !== $settings['google_font'] ) {
			return "'" . $settings['google_font'] . "', " . self::FONTS['arial'];
		}
		return self::FONTS[ $settings['font'] ] ?? self::FONTS['arial'];
	}

	/**
	 * Migration à l'activation.
	 */
	public static function migrate(): void {
		$stored = get_option( BB_WML_OPTION, null );

		if ( ! is_array( $stored ) ) {
			add_option( BB_WML_OPTION, self::defaults(), '', false );
			return;
		}

		$version = (int) ( $stored['_version'] ?? 0 );
		if ( $version >= self::SCHEMA_VERSION ) {
			return;
		}

		// Étapes futures : if ( $version < 2 ) { ... }.
		$stored             = array_merge( self::defaults(), $stored );
		$stored['_version'] = self::SCHEMA_VERSION;
		update_option( BB_WML_OPTION, $stored, false );
	}

	/**
	 * Migration à chaud (mise à jour sans réactivation).
	 */
	public static function maybe_migrate(): void {
		$stored = get_option( BB_WML_OPTION, null );
		if ( ! is_array( $stored ) || (int) ( $stored['_version'] ?? 0 ) < self::SCHEMA_VERSION ) {
			self::migrate();
		}
	}
}
