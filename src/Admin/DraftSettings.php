<?php
/**
 * Réglages non enregistrés : l'aperçu et l'e-mail de test utilisent l'état courant du formulaire.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Admin;

use BB\WooMailLayout\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Court-circuite la lecture de l'option pendant la requête AJAX, sans jamais l'écrire.
 */
final class DraftSettings {

	/**
	 * Applique les réglages postés sous `bb_woo_mail_layout[…]` (avec `draft=1`) jusqu'à la fin de la requête.
	 * À appeler après la vérification du nonce et des droits.
	 */
	public static function apply_from_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié par l'appelant.
		if ( empty( $_POST['draft'] ) || ! isset( $_POST[ BB_WML_OPTION ] ) || ! is_array( $_POST[ BB_WML_OPTION ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- assaini clé par clé par Options::draft().
		$draft = Options::draft( wp_unslash( $_POST[ BB_WML_OPTION ] ) );
		// phpcs:enable

		add_filter( 'pre_option_' . BB_WML_OPTION, static fn() => $draft, PHP_INT_MAX );
	}
}
