<?php
/**
 * Neutralise les « email improvements » de WooCommerce (nouveau design 2025) tant que le plugin est actif.
 *
 * L'option stockée n'est jamais modifiée : on la court-circuite à la lecture. À la désactivation du plugin,
 * le filtre disparaît et le réglage d'origine du site s'applique à nouveau, sans rien avoir à restaurer.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibilité avec les nouveautés e-mail de WooCommerce.
 */
final class EmailImprovements {

	/**
	 * Options de fonctionnalités WooCommerce forcées à « no ».
	 * L'éditeur d'e-mails en blocs contourne entièrement les templates PHP : il est neutralisé aussi.
	 */
	private const FEATURE_OPTIONS = array(
		'woocommerce_feature_email_improvements_enabled',
		'woocommerce_feature_block_email_editor_enabled',
	);

	/**
	 * Hooks (posés au chargement du fichier principal, avant WooCommerce).
	 */
	public static function register(): void {
		/**
		 * Options de fonctionnalités e-mail WooCommerce neutralisées par le plugin.
		 *
		 * @param string[] $options Noms d'options.
		 *
		 * @since 1.0.0
		 */
		$options = (array) apply_filters( 'bb_email_disabled_wc_features', self::FEATURE_OPTIONS );

		foreach ( $options as $option ) {
			add_filter( 'pre_option_' . $option, array( self::class, 'force_no' ) );
		}
	}

	/**
	 * Valeur forcée.
	 */
	public static function force_no(): string {
		return 'no';
	}
}
