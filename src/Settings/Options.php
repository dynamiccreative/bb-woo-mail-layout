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

	/** Nombre maximal de produits mis en avant. */
	public const MAX_FEATURED = 3;

	/**
	 * Source des produits mis en avant. Hors « manual », les produits choisis complètent la sélection automatique.
	 */
	public const FEATURED_SOURCES = array( 'manual', 'cross_sells', 'related' );

	/** Taille maximale du CSS personnalisé (octets). */
	public const MAX_CUSTOM_CSS = 20000;

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
			'logo_max_height'       => 'logo_height',
			'color_primary'         => 'color',
			'color_button'          => 'color',
			'color_text'            => 'color',
			'color_background'      => 'color',
			'color_border'          => 'color_optional',
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
			'show_featured'         => 'bool',
			'featured_title'        => 'text',
			'featured_products'     => 'ids',
			'featured_source'       => 'featured_source',
			'custom_css'            => 'css',
			'emails'                => 'map_bool',
			'intros'                => 'map_textarea',
			'email_layouts'         => 'map_layout',
			'preheaders'            => 'map_text',
			'button_labels'         => 'map_text',
			'button_urls'           => 'map_link',
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
			'logo_max_height'       => 80,
			'color_primary'         => '#1f4e79',
			'color_button'          => '#1f4e79',
			'color_text'            => '#333333',
			'color_background'      => '#f3f4f6',
			'color_border'          => '',
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
			'show_featured'         => 'no',
			'featured_title'        => '',
			'featured_products'     => array(),
			'featured_source'       => 'manual',
			'custom_css'            => '',
			'emails'                => array(),
			'intros'                => array(),
			'email_layouts'         => array(),
			'preheaders'            => array(),
			'button_labels'         => array(),
			'button_urls'           => array(),
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

		foreach ( array( 'emails', 'intros', 'email_layouts', 'preheaders', 'button_labels', 'button_urls', 'featured_products' ) as $map ) {
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
	 * Réglages complets à partir de l'état d'un formulaire non enregistré (aperçu, e-mail de test).
	 *
	 * Comme à l'enregistrement : une case décochée n'est pas postée (« no »), un sélecteur de produits
	 * vide non plus ; les autres clés absentes gardent leur valeur enregistrée.
	 *
	 * @param array<string, mixed> $raw Valeurs brutes postées (déjà déslashées).
	 * @return array<string, mixed>
	 */
	public static function draft( array $raw ): array {
		$draft = self::all();
		foreach ( self::schema() as $key => $type ) {
			if ( array_key_exists( $key, $raw ) ) {
				$draft[ $key ] = self::sanitize_field( $key, $raw[ $key ] );
			} elseif ( 'bool' === $type ) {
				$draft[ $key ] = 'no';
			} elseif ( 'ids' === $type ) {
				$draft[ $key ] = array();
			}
		}
		return $draft;
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
		if ( str_starts_with( $schema[ $key ], 'map_' ) ) {
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

			case 'logo_height':
				$height = is_numeric( $raw ) ? absint( $raw ) : (int) $fallback;
				return max( 20, min( 300, $height ) );

			case 'color':
				$color = is_string( $raw ) ? sanitize_hex_color( trim( $raw ) ) : null;
				return $color ? strtolower( $color ) : $fallback;

			case 'color_optional':
				$color = is_string( $raw ) ? sanitize_hex_color( trim( $raw ) ) : null;
				return $color ? strtolower( $color ) : '';

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

			case 'featured_source':
				return is_string( $raw ) && in_array( $raw, self::FEATURED_SOURCES, true ) ? $raw : $fallback;

			case 'font':
				return is_string( $raw ) && array_key_exists( $raw, self::FONTS ) ? $raw : $fallback;

			case 'google_font':
				return is_string( $raw ) ? trim( (string) preg_replace( '/[^A-Za-z0-9 ]/', '', $raw ) ) : '';

			case 'ids':
				$raw = is_string( $raw ) ? explode( ',', $raw ) : $raw;
				$ids = array_filter( array_map( 'absint', is_array( $raw ) ? $raw : array() ) );
				return array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_FEATURED );

			case 'css':
				return is_string( $raw ) ? self::sanitize_css( $raw ) : '';

			case 'map_bool':
				$out = array();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $flag ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id ) {
						$out[ $id ] = in_array( $flag, array( 'yes', '1', 1, true ), true ) ? 'yes' : 'no';
					}
				}
				return $out;

			case 'map_layout':
				// Slug de layout par e-mail ; '' = layout général (conservé pour que la fusion l'emporte sur l'ancienne valeur).
				$out     = array();
				$layouts = LayoutRenderer::available_layouts();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $slug ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id && is_string( $slug ) ) {
						$out[ $id ] = array_key_exists( $slug, $layouts ) ? $slug : '';
					}
				}
				return $out;

			case 'map_textarea':
			case 'map_text':
				$out = array();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $text ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id && is_scalar( $text ) ) {
						$out[ $id ] = 'map_text' === $type ? sanitize_text_field( (string) $text ) : sanitize_textarea_field( (string) $text );
					}
				}
				return $out;

			case 'map_link':
				// Lien du bouton par e-mail : un placeholder seul ({order_url}…) ou une URL http(s).
				$out = array();
				foreach ( is_array( $raw ) ? $raw : array() as $id => $link ) {
					$id = self::sanitize_email_id( (string) $id );
					if ( '' !== $id && is_scalar( $link ) ) {
						$out[ $id ] = self::sanitize_link( (string) $link );
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
	 * Lien de bouton : placeholder seul (ex. « {tracking_url} ») ou URL http(s) ; sinon vide.
	 *
	 * @param string $link Valeur saisie.
	 */
	public static function sanitize_link( string $link ): string {
		$link = trim( $link );
		if ( preg_match( '/^\{[a-z_]+\}$/', $link ) ) {
			return $link;
		}
		return esc_url_raw( $link, array( 'http', 'https' ) );
	}

	/**
	 * CSS personnalisé : texte brut, sans balise ni construction exécutable.
	 *
	 * Le CSS est écrit dans le <style> de l'e-mail : on retire toute balise (dont « </style> »),
	 * les @import (appels externes) et les constructions historiques de scripts dans le CSS.
	 *
	 * @param string $css CSS saisi.
	 */
	public static function sanitize_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = (string) preg_replace( '/@import[^;]*;?/i', '', $css );
		$css = (string) preg_replace( '/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $css );
		$css = str_replace( '<', '', $css );
		return trim( substr( $css, 0, self::MAX_CUSTOM_CSS ) );
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
