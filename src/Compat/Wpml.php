<?php
/**
 * Compatibilité WPML / Polylang.
 *
 * - WPML : les textes sont déclarés en admin-texts dans wpml-config.xml, get_option() renvoie donc
 *   la traduction de la langue courante. On bascule sur la langue de la commande pendant le rendu.
 * - Polylang : chaînes enregistrées via pll_register_string(), traduites via pll_translate_string().
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Compat;

use BB\WooMailLayout\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Langue de rendu = langue de la commande (ou du client), pas celle de l'admin.
 */
final class Wpml {

	private const PLL_GROUP = 'BB Woo Mail Layout';

	/**
	 * Langue courante du rendu (code), null si aucune bascule.
	 *
	 * @var string|null
	 */
	private ?string $language = null;

	/**
	 * Langue WPML à restaurer.
	 *
	 * @var string|null
	 */
	private ?string $previous_wpml = null;

	/**
	 * Une locale a-t-elle été basculée ?
	 *
	 * @var bool
	 */
	private bool $switched_locale = false;

	/**
	 * Hooks.
	 */
	public function register(): void {
		if ( function_exists( 'pll_register_string' ) ) {
			add_action( 'admin_init', array( $this, 'register_polylang_strings' ) );
		}
	}

	/**
	 * Enregistre les textes éditables dans Polylang (Langues → Traductions).
	 */
	public function register_polylang_strings(): void {
		$settings = Options::all();
		foreach ( array( 'contact_hours', 'footer_legal_name', 'footer_address', 'footer_text' ) as $key ) {
			if ( '' !== (string) $settings[ $key ] ) {
				pll_register_string( $key, (string) $settings[ $key ], self::PLL_GROUP, in_array( $key, array( 'footer_address', 'footer_text' ), true ) );
			}
		}
		foreach ( $settings['intros'] as $id => $text ) {
			if ( '' !== (string) $text ) {
				pll_register_string( 'intro_' . $id, (string) $text, self::PLL_GROUP, true );
			}
		}
	}

	/**
	 * Traduit un texte de réglage dans la langue du rendu (Polylang ; WPML traduit déjà via get_option).
	 *
	 * @param string $name  Nom de la chaîne.
	 * @param string $value Valeur source.
	 */
	public function translate( string $name, string $value ): string {
		if ( '' === $value || ! function_exists( 'pll_translate_string' ) ) {
			return $value;
		}
		$language = $this->language ?? ( function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '' );
		return '' !== $language ? (string) pll_translate_string( $value, $language ) : $value;
	}

	/**
	 * Bascule langue et locale sur celles de l'objet de l'e-mail.
	 *
	 * @param mixed $subject Objet de l'e-mail : commande, utilisateur ou autre.
	 */
	public function switch_for( $subject ): void {
		$language = $this->language_of( $subject );
		$locale   = $language ? $this->locale_of( $language ) : '';

		if ( $subject instanceof \WP_User && '' === $locale ) {
			$locale = get_user_locale( $subject );
		}

		if ( $language && has_action( 'wpml_switch_language' ) ) {
			/**
			 * Hook WPML : langue courante.
			 *
			 * @since 1.0.0
			 */
			$this->previous_wpml = (string) apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API WPML.
			/**
			 * Hook WPML : bascule de langue.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wpml_switch_language', $language ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API WPML.
		}
		$this->language = $language ? $language : null;

		if ( '' !== $locale && determine_locale() !== $locale ) {
			$this->switched_locale = switch_to_locale( $locale );
		}
	}

	/**
	 * Restaure la langue et la locale d'origine.
	 */
	public function restore(): void {
		if ( null !== $this->previous_wpml ) {
			/**
			 * Hook WPML : bascule de langue.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wpml_switch_language', $this->previous_wpml ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API WPML.
			$this->previous_wpml = null;
		}
		if ( $this->switched_locale ) {
			restore_previous_locale();
			$this->switched_locale = false;
		}
		$this->language = null;
	}

	/**
	 * Code langue de la commande / de l'utilisateur.
	 *
	 * @param mixed $subject Objet de l'e-mail.
	 */
	private function language_of( $subject ): string {
		if ( $subject instanceof \WC_Order ) {
			$language = (string) $subject->get_meta( 'wpml_language' );
			if ( '' === $language && function_exists( 'pll_get_post_language' ) ) {
				$language = (string) pll_get_post_language( $subject->get_id() );
			}
			return $language;
		}
		// Utilisateur : pas de code langue, switch_for() se rabat sur get_user_locale().
		return '';
	}

	/**
	 * Locale WordPress d'un code langue WPML / Polylang.
	 *
	 * @param string $language Code langue (ex. « en »).
	 */
	private function locale_of( string $language ): string {
		/**
		 * Hook WPML : langues actives.
		 *
		 * @since 1.0.0
		 */
		$wpml = apply_filters( 'wpml_active_languages', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API WPML.
		if ( is_array( $wpml ) && isset( $wpml[ $language ]['default_locale'] ) ) {
			return (string) $wpml[ $language ]['default_locale'];
		}
		if ( function_exists( 'PLL' ) && isset( PLL()->model ) ) {
			$pll = PLL()->model->get_language( $language );
			if ( $pll && ! empty( $pll->locale ) ) {
				return (string) $pll->locale;
			}
		}
		return '';
	}
}
