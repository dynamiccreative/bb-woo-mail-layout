<?php
/**
 * Commandes WP-CLI : déploiement des réglages et e-mails de test en série.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Cli;

use BB\WooMailLayout\Admin\TestEmail;
use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Settings\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Gère la mise en forme des e-mails WooCommerce (BB Woo Mail Layout).
 *
 * ## EXAMPLES
 *
 *     # Copier la mise en forme d'un site à l'autre
 *     wp bb-mail export --file=reglages.json
 *     wp bb-mail import reglages.json --yes
 *
 *     # Recevoir toute la série d'e-mails mis en forme
 *     wp bb-mail test --all --to=moi@exemple.fr
 */
final class Command {

	/**
	 * Constructeur.
	 *
	 * @param EmailRegistry $registry Registre des e-mails.
	 */
	public function __construct( private EmailRegistry $registry ) {}

	/**
	 * Liste les e-mails WooCommerce détectés et leur statut dans le plugin.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Format de sortie.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param string[]             $args       Arguments positionnels.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$rows = array();
		foreach ( $this->registry->all() as $id => $email ) {
			$rows[] = array(
				'id'           => $id,
				'title'        => $email['title'],
				'recipient'    => $email['customer'] ? 'client' : 'admin',
				'source'       => $email['source'],
				'mis_en_forme' => $this->registry->is_enabled( $id ) ? 'oui' : 'non',
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', array_column( $rows, 'id' ) ) );
			return;
		}
		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'title', 'recipient', 'source', 'mis_en_forme' ) );
	}

	/**
	 * Exporte les réglages en JSON (même format que l'export de l'admin).
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Fichier de destination. Sans cette option, le JSON est écrit sur la sortie standard.
	 *
	 * @param string[]             $args       Arguments positionnels.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function export( array $args, array $assoc_args ): void {
		$json = (string) wp_json_encode( ImportExport::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( empty( $assoc_args['file'] ) ) {
			\WP_CLI::line( $json );
			return;
		}
		if ( false === file_put_contents( $assoc_args['file'], $json . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fichier local choisi par l'opérateur WP-CLI.
			\WP_CLI::error( sprintf( 'Impossible d’écrire %s.', $assoc_args['file'] ) );
		}
		\WP_CLI::success( sprintf( 'Réglages exportés dans %s.', $assoc_args['file'] ) );
	}

	/**
	 * Importe un export JSON (remplace tous les réglages ; rien n'est modifié en cas d'erreur).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Fichier JSON exporté depuis l'admin ou `wp bb-mail export`. « - » lit l'entrée standard.
	 *
	 * [--yes]
	 * : Ne pas demander de confirmation.
	 *
	 * @param string[]             $args       Arguments positionnels.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0];
		$json = '-' === $file ? stream_get_contents( STDIN ) : ( is_readable( $file ) ? file_get_contents( $file ) : false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local choisi par l'opérateur WP-CLI.
		if ( false === $json ) {
			\WP_CLI::error( sprintf( 'Fichier illisible : %s.', $file ) );
		}

		$errors = ImportExport::validate( (string) $json );
		if ( $errors ) {
			\WP_CLI::error( 'Import refusé : ' . implode( ' ', array_map( 'html_entity_decode', $errors ) ) );
		}

		\WP_CLI::confirm( 'L’import remplace tous les réglages de BB Woo Mail Layout. Continuer ?', $assoc_args );
		ImportExport::import( (string) $json );
		\WP_CLI::success( 'Réglages importés.' );
	}

	/**
	 * Envoie des e-mails de test via le pipeline réel de WooCommerce (wp_mail → SMTP configuré).
	 *
	 * ## OPTIONS
	 *
	 * [<email>...]
	 * : Identifiants des e-mails (voir `wp bb-mail list --format=ids`).
	 *
	 * [--all]
	 * : Tous les e-mails mis en forme par le plugin.
	 *
	 * [--to=<address>]
	 * : Destinataire. Par défaut : l'adresse de l'administrateur du site.
	 *
	 * [--order=<id>]
	 * : Commande utilisée comme jeu de données. Par défaut : une commande fictive (rien n'est enregistré).
	 *
	 * ## EXAMPLES
	 *
	 *     wp bb-mail test customer_processing_order --order=1234
	 *     wp bb-mail test --all --to=moi@exemple.fr
	 *
	 * @param string[]             $args       Arguments positionnels.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function test( array $args, array $assoc_args ): void {
		$ids = ! empty( $assoc_args['all'] )
			? array_values( array_filter( array_keys( $this->registry->all() ), array( $this->registry, 'is_enabled' ) ) )
			: $args;
		if ( ! $ids ) {
			\WP_CLI::error( 'Indiquez au moins un e-mail, ou --all.' );
		}

		$to     = (string) ( $assoc_args['to'] ?? get_option( 'admin_email' ) );
		$order  = absint( $assoc_args['order'] ?? 0 );
		$sender = new TestEmail( $this->registry );
		$failed = 0;

		foreach ( $ids as $id ) {
			if ( ! $this->registry->get( $id ) ) {
				\WP_CLI::warning( sprintf( '%s : e-mail inconnu.', $id ) );
				++$failed;
				continue;
			}
			try {
				\WP_CLI::log( html_entity_decode( $sender->send( $id, $order, $to ) ) );
			} catch ( \Throwable $e ) {
				\WP_CLI::warning( sprintf( '%s : %s', $id, html_entity_decode( $e->getMessage() ) ) );
				++$failed;
			}
		}

		if ( $failed ) {
			\WP_CLI::error( sprintf( '%1$d e-mail(s) sur %2$d non envoyé(s).', $failed, count( $ids ) ) );
		}
		\WP_CLI::success( sprintf( '%1$d e-mail(s) envoyé(s) à %2$s.', count( $ids ), $to ) );
	}
}
