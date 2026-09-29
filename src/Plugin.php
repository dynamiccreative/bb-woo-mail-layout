<?php
/**
 * Point d'entrée du plugin : enregistrement des services.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout;

use BB\WooMailLayout\Admin\Notices;
use BB\WooMailLayout\Admin\Preview;
use BB\WooMailLayout\Admin\TestEmail;
use BB\WooMailLayout\Compat\Wpml;
use BB\WooMailLayout\Email\DefaultTexts;
use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Email\LayoutRenderer;
use BB\WooMailLayout\Email\Placeholders;
use BB\WooMailLayout\Settings\ImportExport;
use BB\WooMailLayout\Settings\LogoChecker;
use BB\WooMailLayout\Settings\Options;
use BB\WooMailLayout\Settings\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton du plugin.
 */
final class Plugin {

	public const MIN_WC_VERSION = '8.0';

	/**
	 * Instance unique.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Registre des e-mails WooCommerce.
	 *
	 * @var EmailRegistry
	 */
	private EmailRegistry $registry;

	/**
	 * Rendu du layout.
	 *
	 * @var LayoutRenderer
	 */
	private LayoutRenderer $renderer;

	/**
	 * Placeholders.
	 *
	 * @var Placeholders
	 */
	private Placeholders $placeholders;


	/**
	 * Instance unique.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Démarrage sur `plugins_loaded`.
	 */
	public static function boot(): void {
		self::instance()->init();
	}

	/**
	 * Activation : migration des options, planification du contrôle du logo.
	 */
	public static function activate(): void {
		Options::migrate();
		if ( ! wp_next_scheduled( LogoChecker::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', LogoChecker::CRON_HOOK );
		}
	}

	/**
	 * Désactivation : les options sont conservées (supprimées seulement à la désinstallation).
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( LogoChecker::CRON_HOOK );
	}

	/**
	 * Constructeur privé (singleton).
	 */
	private function __construct() {}

	/**
	 * Enregistre les services si WooCommerce est présent et compatible.
	 */
	private function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Avant le contrôle WooCommerce : le plugin reste mis à jour même si WooCommerce est inactif.
		self::register_updater();

		if ( ! self::woocommerce_is_compatible() ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		Options::maybe_migrate();

		$this->registry     = new EmailRegistry();
		$this->placeholders = new Placeholders();
		$wpml               = new Wpml();
		$this->renderer     = new LayoutRenderer( $this->registry, $this->placeholders, new DefaultTexts(), $wpml );

		$this->renderer->register();
		$wpml->register();
		( new LogoChecker() )->register();

		if ( is_admin() ) {
			( new SettingsPage( $this->registry ) )->register();
			( new Preview( $this->registry ) )->register();
			( new TestEmail( $this->registry ) )->register();
			( new ImportExport() )->register();
			( new Notices() )->register();
		}
	}

	/**
	 * Mises à jour depuis GitHub (mécanisme maison des plugins Dynamic Creative / bleuebuzz).
	 *
	 * La version publiée est celle de l'en-tête du fichier principal sur la branche `main` :
	 * pousser une nouvelle `Version` sur `main` suffit à proposer la mise à jour aux sites.
	 * Dépôt public : aucun jeton requis ; pour un dépôt privé, renseigner l'option
	 * `bb_wml_github_access_token`.
	 */
	private static function register_updater(): void {
		require_once BB_WML_DIR . 'lib/GitHubUpdater.php';

		// La bibliothèque retire « WP_PLUGIN_DIR/ » du chemin puis le découpe en dossier/fichier :
		// sous Windows, __FILE__ contient des antislashs et ce découpage échoue (erreur fatale).
		$updater = new \BB_WML_GitHubUpdater( WP_PLUGIN_DIR . '/' . plugin_basename( BB_WML_FILE ) );
		$updater->setBranch( 'main' );
		$updater->setAccessToken( (string) get_option( 'bb_wml_github_access_token', '' ) );
		$updater->setPluginIcon( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/icon-256x256.png' );
		$updater->setPluginBannerSmall( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/banner-1544x500.png' );
		$updater->setPluginBannerLarge( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/banner-1544x500.png' );
		$updater->setChangelog( 'CHANGELOG.md' );
		$updater->add();
	}

	/**
	 * WooCommerce est-il actif, en version suffisante ?
	 */
	private static function woocommerce_is_compatible(): bool {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			return false;
		}
		return version_compare( (string) constant( 'WC_VERSION' ), self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Charge les traductions du plugin (chaînes source en français).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'bb-woo-mail-layout', false, dirname( plugin_basename( BB_WML_FILE ) ) . '/languages' );
	}

	/**
	 * Notice si WooCommerce est absent ou trop ancien.
	 */
	public function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: version minimale de WooCommerce. */
					__( 'BB Woo Mail Layout nécessite WooCommerce %s ou supérieur. Le plugin est inactif.', 'bb-woo-mail-layout' ),
					self::MIN_WC_VERSION
				)
			)
		);
	}

	/**
	 * Registre des e-mails.
	 */
	public function registry(): EmailRegistry {
		return $this->registry;
	}

	/**
	 * Rendu du layout (utilisé par les templates).
	 */
	public function renderer(): LayoutRenderer {
		return $this->renderer;
	}

	/**
	 * Placeholders.
	 */
	public function placeholders(): Placeholders {
		return $this->placeholders;
	}
}
