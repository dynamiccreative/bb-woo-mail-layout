<?php
/**
 * Onglet « Mise en forme bleuebuzz » dans WooCommerce → Réglages → E-mails (Settings API WooCommerce).
 *
 * L'interface (sous-onglets, cartes, aperçu en direct) est rendue d'un bloc par le champ `bb_wml_app`.
 * Chaque réglage reste déclaré comme champ `bb_wml_value` (sans rendu) : WooCommerce l'enregistre
 * et le fait passer par sanitize(), comme avant.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Settings;

use BB\WooMailLayout\Email\DefaultTexts;
use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Email\LayoutRenderer;
use BB\WooMailLayout\Email\Placeholders;
use BB\WooMailLayout\Email\Simulator;

defined( 'ABSPATH' ) || exit;

/**
 * Champs, rendu de l'interface, assainissement.
 */
final class SettingsPage {

	public const SECTION = 'bb_mail_layout';

	public const NONCE_ACTION = 'bb_wml_admin';

	/** E-mail affiché par défaut dans l'aperçu. */
	private const PREVIEW_EMAIL = 'customer_processing_order';

	/**
	 * Constructeur.
	 *
	 * @param EmailRegistry $registry Registre des e-mails.
	 */
	public function __construct( private EmailRegistry $registry ) {}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_sections_email', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_email', array( $this, 'settings' ), 10, 2 );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . BB_WML_OPTION, array( $this, 'sanitize' ), 10, 3 );
		add_action( 'woocommerce_update_options_email_' . self::SECTION, array( $this, 'after_save' ) );

		add_action( 'woocommerce_admin_field_bb_wml_app', array( $this, 'render_app' ) );
		add_action( 'woocommerce_admin_field_bb_wml_value', array( $this, 'field_value' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BB_WML_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * URL de la page de réglages.
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . self::SECTION );
	}

	/**
	 * Sommes-nous sur notre onglet ?
	 */
	private static function is_current_page(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lecture de navigation uniquement.
		return isset( $_GET['page'], $_GET['tab'], $_GET['section'] )
			&& 'wc-settings' === $_GET['page']
			&& 'email' === $_GET['tab']
			&& self::SECTION === $_GET['section'];
		// phpcs:enable
	}

	/**
	 * Ajoute l'onglet.
	 *
	 * @param mixed $sections Sections de l'onglet E-mails.
	 * @return array<string,string>
	 */
	public function add_section( $sections ) {
		$sections                  = is_array( $sections ) ? $sections : array();
		$sections[ self::SECTION ] = __( 'Mise en forme bleuebuzz', 'bb-woo-mail-layout' );
		return $sections;
	}

	/**
	 * Champs de l'onglet.
	 *
	 * @param array<int,array<string,mixed>> $settings Champs WooCommerce.
	 * @param string                         $section  Section courante.
	 * @return array<int,array<string,mixed>>
	 */
	public function settings( $settings, $section = '' ) {
		return self::SECTION === $section ? $this->fields() : $settings;
	}

	/**
	 * Définition des champs : l'interface complète, puis un champ enregistrable par clé du schéma.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fields(): array {
		$fields = array(
			array(
				'id'        => 'bb_wml_app',
				'type'      => 'bb_wml_app',
				'is_option' => false,
			),
		);
		foreach ( array_keys( Options::schema() ) as $key ) {
			$fields[] = array(
				'id'   => self::id( $key ),
				'type' => 'bb_wml_value',
			);
		}
		return $fields;
	}

	/**
	 * Nom de champ dans l'option unique : bb_woo_mail_layout[clé].
	 *
	 * @param string $key Clé.
	 */
	private static function id( string $key ): string {
		return BB_WML_OPTION . '[' . $key . ']';
	}

	/**
	 * Clé d'un champ à partir de son id.
	 *
	 * @param string $id Id du champ.
	 */
	private static function key( string $id ): string {
		return preg_match( '/\[([^\]]+)\]$/', $id, $m ) ? $m[1] : '';
	}

	/**
	 * Assainissement de chaque sous-clé, à partir de la valeur brute postée.
	 *
	 * @param mixed               $value     Valeur pré-assainie par WooCommerce.
	 * @param array<string,mixed> $option    Définition du champ.
	 * @param mixed               $raw_value Valeur brute.
	 * @return mixed
	 */
	public function sanitize( $value, $option, $raw_value ) {
		$key = self::key( (string) ( $option['id'] ?? '' ) );
		return '' === $key ? null : Options::sanitize_field( $key, $raw_value );
	}

	/**
	 * Après enregistrement : cohérence logo_id / logo_url, contrôle du logo.
	 */
	public function after_save(): void {
		$settings = Options::all();
		$logo_id  = (int) $settings['logo_id'];
		$logo_url = (string) $settings['logo_url'];

		if ( '' === $logo_url ) {
			$logo_id = 0;
		} elseif ( ! $logo_id || wp_get_attachment_url( $logo_id ) !== $logo_url ) {
			$logo_id = attachment_url_to_postid( $logo_url );
		}
		if ( $logo_id !== (int) $settings['logo_id'] ) {
			$settings['logo_id'] = $logo_id;
			update_option( BB_WML_OPTION, $settings );
		}

		LogoChecker::run();
	}

	/*
	 * ------------------------------------------------------------------
	 * Interface
	 * ------------------------------------------------------------------
	 */

	/**
	 * Champ enregistrable : rendu par render_app(), rien à afficher ici.
	 */
	public function field_value(): void {}

	/**
	 * Interface complète de l'onglet.
	 */
	public function render_app(): void {
		// Le bouton d'enregistrement est dans notre barre fixe ; WooCommerce garde son nonce.
		$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- drapeau de WooCommerce.

		$settings = Options::all();
		$orders   = ( new Simulator( $this->registry ) )->recent_orders( 20 );
		$emails   = $this->registry->all();
		$status   = LogoChecker::status();
		$layouts  = LayoutRenderer::available_layouts();
		?>
		<div class="bb-wml" id="bb-wml">
			<div class="bb-wml-head">
				<div>
					<h2><?php esc_html_e( 'Mise en forme bleuebuzz', 'bb-woo-mail-layout' ); ?></h2>
					<div class="bb-wml-chips">
						<span class="bb-wml-chip"><?php esc_html_e( 'Layout', 'bb-woo-mail-layout' ); ?> <b id="bb-wml-sum-layout"><?php echo esc_html( $layouts[ $settings['layout'] ]['label'] ?? '' ); ?></b></span>
						<span class="bb-wml-chip"><b id="bb-wml-sum-mails"></b> <?php esc_html_e( 'e-mails mis en forme', 'bb-woo-mail-layout' ); ?></span>
						<?php if ( $status && '' !== $settings['logo_url'] && $status['url'] === $settings['logo_url'] ) : ?>
							<span class="bb-wml-chip is-<?php echo esc_attr( $status['state'] ); ?>" title="<?php echo esc_attr( $status['message'] ); ?>">
								<span class="bb-wml-dot"></span>
								<?php
								echo esc_html(
									'ok' === $status['state'] ? __( 'Logo vérifié', 'bb-woo-mail-layout' )
										: ( 'error' === $status['state'] ? __( 'Logo inaccessible', 'bb-woo-mail-layout' ) : __( 'Logo non vérifié', 'bb-woo-mail-layout' ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>
				<button type="button" class="button" data-bb-goto="outils"><?php esc_html_e( 'Envoyer un e-mail de test', 'bb-woo-mail-layout' ); ?></button>
			</div>

			<div class="bb-wml-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Sections', 'bb-woo-mail-layout' ); ?>">
				<?php
				foreach ( array(
					'apparence' => __( 'Apparence', 'bb-woo-mail-layout' ),
					'contenu'   => __( 'Contenu', 'bb-woo-mail-layout' ),
					'emails'    => __( 'E-mails', 'bb-woo-mail-layout' ),
					'outils'    => __( 'Outils', 'bb-woo-mail-layout' ),
				) as $tab => $label ) :
					?>
					<button type="button" class="bb-wml-tab" role="tab" id="bb-wml-tab-<?php echo esc_attr( $tab ); ?>" aria-controls="bb-wml-panel-<?php echo esc_attr( $tab ); ?>" aria-selected="<?php echo 'apparence' === $tab ? 'true' : 'false'; ?>" data-bb-tab="<?php echo esc_attr( $tab ); ?>">
						<?php echo esc_html( $label ); ?>
						<?php if ( 'emails' === $tab ) : ?>
							<span class="bb-wml-count"><?php echo (int) count( $emails ); ?></span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="bb-wml-work">
				<div class="bb-wml-panels">
					<?php
					$this->panel_appearance( $settings, $layouts, $status );
					$this->panel_content( $settings );
					$this->panel_emails( $settings, $emails, $layouts );
					$this->panel_tools( $emails, $orders );
					?>
				</div>
				<?php $this->preview( $emails, $orders ); ?>
			</div>

			<div class="bb-wml-savebar submit">
				<span class="bb-wml-savebar__state" id="bb-wml-dirty" hidden><?php esc_html_e( 'Modifications non enregistrées', 'bb-woo-mail-layout' ); ?></span>
				<button type="submit" name="save" class="button button-primary button-large" value="<?php esc_attr_e( 'Enregistrer les modifications', 'bb-woo-mail-layout' ); ?>"><?php esc_html_e( 'Enregistrer les modifications', 'bb-woo-mail-layout' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet Apparence : layout, logo, couleurs, police.
	 *
	 * @param array<string,mixed>                                $settings Réglages.
	 * @param array<string,array<string,mixed>>                  $layouts  Layouts disponibles.
	 * @param array{url:string,state:string,message:string}|null $status Contrôle du logo.
	 */
	private function panel_appearance( array $settings, array $layouts, ?array $status ): void {
		$descriptions = array(
			'classique' => __( 'Fond clair, bandeau coloré sous le logo.', 'bb-woo-mail-layout' ),
			'sobre'     => __( 'Tout blanc, simple filet de couleur.', 'bb-woo-mail-layout' ),
			'ecommerce' => __( 'Barre d’accent, photos produit dans la commande.', 'bb-woo-mail-layout' ),
		);
		$logo_url     = (string) $settings['logo_url'];
		?>
		<section class="bb-wml-panel" id="bb-wml-panel-apparence" role="tabpanel" aria-labelledby="bb-wml-tab-apparence">
			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'Layout', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Layout général des e-mails activés. Un e-mail peut avoir le sien dans l’onglet E-mails.', 'bb-woo-mail-layout' ); ?></p></div></div>
				<div class="bb-wml-card__body">
					<div class="bb-wml-layouts" role="radiogroup" aria-label="<?php esc_attr_e( 'Layout', 'bb-woo-mail-layout' ); ?>">
						<?php foreach ( $layouts as $slug => $layout ) : ?>
							<label class="bb-wml-layout">
								<input type="radio" name="<?php echo esc_attr( self::id( 'layout' ) ); ?>" value="<?php echo esc_attr( $slug ); ?>" data-label="<?php echo esc_attr( $layout['label'] ); ?>" <?php checked( $settings['layout'], $slug ); ?>>
								<?php self::thumb( (string) $slug ); ?>
								<strong><?php echo esc_html( $layout['label'] ); ?></strong>
								<?php if ( isset( $descriptions[ $slug ] ) ) : ?>
									<small><?php echo esc_html( $descriptions[ $slug ] ); ?></small>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="bb-wml-card">
				<div class="bb-wml-card__head">
					<div><h3><?php esc_html_e( 'Logo', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Affiché en tête d’e-mail, avec un lien vers la boutique.', 'bb-woo-mail-layout' ); ?></p></div>
					<?php self::switch_input( 'show_logo', __( 'Afficher le logo', 'bb-woo-mail-layout' ), 'yes' === $settings['show_logo'] ); ?>
				</div>
				<div class="bb-wml-card__body" data-bb-show-if="show_logo">
					<div class="bb-wml-media">
						<div class="bb-wml-media__box">
							<img class="bb-wml-media__preview" src="<?php echo esc_url( $logo_url ); ?>" alt="" <?php echo '' === $logo_url ? 'hidden' : ''; ?>>
							<span class="bb-wml-media__empty" <?php echo '' === $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Aucun logo', 'bb-woo-mail-layout' ); ?></span>
						</div>
						<div class="bb-wml-media__actions">
							<button type="button" class="button bb-wml-media__choose"><?php esc_html_e( 'Choisir une image', 'bb-woo-mail-layout' ); ?></button>
							<button type="button" class="button-link bb-wml-media__remove"><?php esc_html_e( 'Retirer', 'bb-woo-mail-layout' ); ?></button>
							<span class="bb-wml-hint"><?php esc_html_e( 'Image de la médiathèque de ce site (PNG ou JPG).', 'bb-woo-mail-layout' ); ?></span>
						</div>
					</div>
					<div class="bb-wml-field">
						<label for="bb-wml-logo-url"><?php esc_html_e( 'URL de l’image', 'bb-woo-mail-layout' ); ?></label>
						<input type="url" id="bb-wml-logo-url" name="<?php echo esc_attr( self::id( 'logo_url' ) ); ?>" value="<?php echo esc_attr( $logo_url ); ?>">
						<input type="hidden" id="bb-wml-logo-id" name="<?php echo esc_attr( self::id( 'logo_id' ) ); ?>" value="<?php echo (int) $settings['logo_id']; ?>">
					</div>
					<div class="bb-wml-row">
						<div class="bb-wml-field">
							<label for="bb-wml-logo-w"><?php esc_html_e( 'Largeur maximale', 'bb-woo-mail-layout' ); ?></label>
							<div class="bb-wml-suffix"><input type="number" id="bb-wml-logo-w" name="<?php echo esc_attr( self::id( 'logo_max_width' ) ); ?>" value="<?php echo (int) $settings['logo_max_width']; ?>" min="50" max="600" step="10"><span>px</span></div>
						</div>
						<div class="bb-wml-field">
							<label for="bb-wml-logo-h"><?php esc_html_e( 'Hauteur maximale', 'bb-woo-mail-layout' ); ?></label>
							<div class="bb-wml-suffix"><input type="number" id="bb-wml-logo-h" name="<?php echo esc_attr( self::id( 'logo_max_height' ) ); ?>" value="<?php echo (int) $settings['logo_max_height']; ?>" min="20" max="300" step="5"><span>px</span></div>
						</div>
					</div>
					<p class="bb-wml-hint">
						<?php esc_html_e( 'La limite la plus contraignante l’emporte. Le logo n’est jamais agrandi au-delà de sa taille réelle.', 'bb-woo-mail-layout' ); ?>
						<span id="bb-wml-logo-size"></span>
					</p>
					<?php if ( $status && '' !== $logo_url && $status['url'] === $logo_url ) : ?>
						<p class="bb-wml-logo-status is-<?php echo esc_attr( $status['state'] ); ?>"><?php echo esc_html( $status['message'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="bb-wml-card">
				<div class="bb-wml-card__head">
					<div><h3><?php esc_html_e( 'Couleurs', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Cliquez sur une pastille pour choisir, ou saisissez le code hexadécimal.', 'bb-woo-mail-layout' ); ?></p></div>
					<button type="button" class="button-link" id="bb-wml-reset-colors"><?php esc_html_e( 'Rétablir les couleurs par défaut', 'bb-woo-mail-layout' ); ?></button>
				</div>
				<div class="bb-wml-card__body">
					<div class="bb-wml-swatches">
						<?php
						self::swatch( 'color_primary', __( 'Principale', 'bb-woo-mail-layout' ), __( 'Bandeau, titres, liens', 'bb-woo-mail-layout' ), $settings );
						self::swatch( 'color_button', __( 'Boutons', 'bb-woo-mail-layout' ), '', $settings );
						self::swatch( 'color_text', __( 'Texte', 'bb-woo-mail-layout' ), '', $settings );
						self::swatch( 'color_border', __( 'Bordures', 'bb-woo-mail-layout' ), __( 'Vide : teinte calculée depuis le texte', 'bb-woo-mail-layout' ), $settings );
						self::swatch( 'color_background', __( 'Fond extérieur', 'bb-woo-mail-layout' ), __( 'Layout Classique uniquement', 'bb-woo-mail-layout' ), $settings );
						?>
					</div>
				</div>
			</div>

			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'Police', 'bb-woo-mail-layout' ); ?></h3></div></div>
				<div class="bb-wml-card__body">
					<div class="bb-wml-row">
						<div class="bb-wml-field">
							<label for="bb-wml-font"><?php esc_html_e( 'Police du texte', 'bb-woo-mail-layout' ); ?></label>
							<select id="bb-wml-font" name="<?php echo esc_attr( self::id( 'font' ) ); ?>">
								<?php
								foreach ( array(
									'arial'     => 'Arial',
									'helvetica' => 'Helvetica',
									'georgia'   => 'Georgia',
									'verdana'   => 'Verdana',
									'trebuchet' => 'Trebuchet MS',
									'google'    => __( 'Google Font (repli sur Arial)', 'bb-woo-mail-layout' ),
								) as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="bb-wml-field" data-bb-show-if="font=google">
							<label for="bb-wml-google-font"><?php esc_html_e( 'Nom de la Google Font', 'bb-woo-mail-layout' ); ?></label>
							<input type="text" id="bb-wml-google-font" name="<?php echo esc_attr( self::id( 'google_font' ) ); ?>" value="<?php echo esc_attr( (string) $settings['google_font'] ); ?>" placeholder="Montserrat">
							<p class="bb-wml-hint"><?php esc_html_e( 'Affichée par Apple Mail et iOS. Gmail et Outlook affichent la police de repli.', 'bb-woo-mail-layout' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Onglet Contenu : blocs (interrupteur + réglages), CSS personnalisé.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 */
	private function panel_content( array $settings ): void {
		?>
		<section class="bb-wml-panel" id="bb-wml-panel-contenu" role="tabpanel" aria-labelledby="bb-wml-tab-contenu" hidden>
			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'Blocs de l’e-mail', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Activez un bloc pour le régler. Un bloc désactivé n’apparaît dans aucun e-mail.', 'bb-woo-mail-layout' ); ?></p></div></div>
				<div class="bb-wml-card__body is-flush">

					<?php self::block_open( 'show_intro', __( 'Paragraphe d’introduction', 'bb-woo-mail-layout' ), __( 'Texte sous le titre, réglé e-mail par e-mail dans l’onglet E-mails.', 'bb-woo-mail-layout' ), $settings, false ); ?>
						<button type="button" class="button-link" data-bb-goto="emails"><?php esc_html_e( 'Modifier les textes', 'bb-woo-mail-layout' ); ?></button>
					</div></div>

					<?php self::block_open( 'show_help', __( 'Besoin d’aide ?', 'bb-woo-mail-layout' ), __( 'Masqué automatiquement si aucune information n’est renseignée.', 'bb-woo-mail-layout' ), $settings ); ?>
						<div class="bb-wml-row">
							<?php self::text_field( 'contact_phone', __( 'Téléphone', 'bb-woo-mail-layout' ), $settings ); ?>
							<?php self::text_field( 'contact_email', __( 'E-mail', 'bb-woo-mail-layout' ), $settings, 'email' ); ?>
						</div>
						<?php self::text_field( 'contact_hours', __( 'Horaires', 'bb-woo-mail-layout' ), $settings, 'text', __( 'Du lundi au vendredi, de 9 h à 18 h', 'bb-woo-mail-layout' ) ); ?>
					</div></div>

					<?php self::block_open( 'show_social', __( 'Réseaux sociaux', 'bb-woo-mail-layout' ), __( 'Seuls les réseaux dont l’URL est renseignée sont affichés.', 'bb-woo-mail-layout' ), $settings ); ?>
						<div class="bb-wml-social">
							<?php
							foreach ( array(
								'facebook'  => 'Facebook',
								'instagram' => 'Instagram',
								'linkedin'  => 'LinkedIn',
								'tiktok'    => 'TikTok',
								'youtube'   => 'YouTube',
							) as $network => $label ) :
								?>
								<div class="bb-wml-social__row">
									<label for="bb-wml-social-<?php echo esc_attr( $network ); ?>"><img src="<?php echo esc_url( BB_WML_URL . 'assets/icons/' . $network . '.png' ); ?>" alt="" width="20" height="20"><?php echo esc_html( $label ); ?></label>
									<input type="url" id="bb-wml-social-<?php echo esc_attr( $network ); ?>" data-bb-social name="<?php echo esc_attr( self::id( 'social_' . $network ) ); ?>" value="<?php echo esc_attr( (string) $settings[ 'social_' . $network ] ); ?>" placeholder="https://">
								</div>
							<?php endforeach; ?>
						</div>
					</div></div>

					<?php self::block_open( 'show_featured', __( 'Produits mis en avant', 'bb-woo-mail-layout' ), __( 'Jusqu’à 3 produits (photo, titre, prix) sous la commande, dans les e-mails envoyés au client.', 'bb-woo-mail-layout' ), $settings ); ?>
						<?php self::text_field( 'featured_title', __( 'Titre du bloc', 'bb-woo-mail-layout' ), $settings, 'text', __( 'Vous aimerez aussi', 'bb-woo-mail-layout' ) ); ?>
						<div class="bb-wml-field">
							<label for="bb-wml-featured-products"><?php esc_html_e( 'Produits (3 maximum)', 'bb-woo-mail-layout' ); ?></label>
							<select id="bb-wml-featured-products" class="wc-product-search" multiple="multiple" style="width:100%;"
								name="<?php echo esc_attr( self::id( 'featured_products' ) ); ?>[]"
								data-placeholder="<?php esc_attr_e( 'Rechercher un produit…', 'bb-woo-mail-layout' ); ?>"
								data-action="woocommerce_json_search_products_and_variations"
								data-maximum-selection-length="<?php echo (int) Options::MAX_FEATURED; ?>">
								<?php foreach ( (array) $settings['featured_products'] as $id ) : ?>
									<?php $product = wc_get_product( (int) $id ); ?>
									<?php if ( $product ) : ?>
										<option value="<?php echo (int) $id; ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
							<p class="bb-wml-hint"><?php esc_html_e( 'Les produits non publiés ou masqués du catalogue sont ignorés.', 'bb-woo-mail-layout' ); ?></p>
						</div>
					</div></div>

					<?php self::block_open( 'show_footer', __( 'Pied de page', 'bb-woo-mail-layout' ), __( 'Nom légal, adresse et mentions.', 'bb-woo-mail-layout' ), $settings ); ?>
						<div class="bb-wml-row">
							<?php self::text_field( 'footer_legal_name', __( 'Nom légal', 'bb-woo-mail-layout' ), $settings ); ?>
							<div class="bb-wml-field">
								<label for="bb-wml-footer-address"><?php esc_html_e( 'Adresse', 'bb-woo-mail-layout' ); ?></label>
								<textarea id="bb-wml-footer-address" rows="2" name="<?php echo esc_attr( self::id( 'footer_address' ) ); ?>"><?php echo esc_textarea( (string) $settings['footer_address'] ); ?></textarea>
							</div>
						</div>
						<label class="bb-wml-check">
							<input type="checkbox" name="<?php echo esc_attr( self::id( 'footer_show_site_link' ) ); ?>" value="yes" <?php checked( 'yes', $settings['footer_show_site_link'] ); ?>>
							<?php esc_html_e( 'Afficher le lien vers la boutique', 'bb-woo-mail-layout' ); ?>
						</label>
						<div class="bb-wml-field bb-wml-editor">
							<label for="bb_wml_footer_text"><?php esc_html_e( 'Mentions', 'bb-woo-mail-layout' ); ?></label>
							<?php
							wp_editor(
								(string) $settings['footer_text'],
								'bb_wml_footer_text',
								array(
									'textarea_name' => self::id( 'footer_text' ),
									'textarea_rows' => 4,
									'media_buttons' => false,
									'teeny'         => false,
									'quicktags'     => array( 'buttons' => 'strong,link' ),
									'tinymce'       => array(
										'toolbar1'       => 'bold,link,unlink',
										'toolbar2'       => '',
										'valid_elements' => 'strong/b,em,br,p,a[href|title|target|rel]',
									),
								)
							);
							?>
							<p class="bb-wml-hint"><?php esc_html_e( 'Gras, lien et saut de ligne uniquement. Placeholders autorisés.', 'bb-woo-mail-layout' ); ?></p>
						</div>
					</div></div>

				</div>
			</div>

			<details class="bb-wml-card bb-wml-advanced" <?php echo '' !== (string) $settings['custom_css'] ? 'open' : ''; ?>>
				<summary><?php esc_html_e( 'Avancé : CSS personnalisé', 'bb-woo-mail-layout' ); ?></summary>
				<div class="bb-wml-card__body">
					<p class="bb-wml-hint"><?php esc_html_e( 'Ajouté après le CSS du layout (il l’emporte). Balises, @import et scripts sont retirés. Jetons (cliquer pour insérer) :', 'bb-woo-mail-layout' ); ?></p>
					<div class="bb-wml-tokens" data-bb-target="bb-wml-custom-css">
						<?php foreach ( array( 'primary', 'button', 'text', 'muted', 'border', 'soft', 'background', 'font' ) as $token ) : ?>
							<button type="button" class="bb-wml-token">{{<?php echo esc_html( $token ); ?>}}</button>
						<?php endforeach; ?>
					</div>
					<textarea id="bb-wml-custom-css" class="bb-wml-code" rows="10" name="<?php echo esc_attr( self::id( 'custom_css' ) ); ?>" placeholder="<?php echo esc_attr( ".bb-heading { letter-spacing: .5px; }\n.bb-featured-price { color: {{button}}; }" ); ?>"><?php echo esc_textarea( (string) $settings['custom_css'] ); ?></textarea>
				</div>
			</details>
		</section>
		<?php
	}

	/**
	 * Onglet E-mails : activation, layout et texte d'introduction, e-mail par e-mail.
	 *
	 * @param array<string,mixed>               $settings Réglages.
	 * @param array<string,array<string,mixed>> $emails   E-mails du registre.
	 * @param array<string,array<string,mixed>> $layouts  Layouts disponibles.
	 */
	private function panel_emails( array $settings, array $emails, array $layouts ): void {
		$texts    = new DefaultTexts();
		$defaults = $texts->by_id();
		$intros   = (array) $settings['intros'];
		$chosen   = (array) $settings['email_layouts'];
		$general  = (string) ( $layouts[ $settings['layout'] ]['label'] ?? '' );
		?>
		<section class="bb-wml-panel" id="bb-wml-panel-emails" role="tabpanel" aria-labelledby="bb-wml-tab-emails" hidden>
			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'E-mails mis en forme', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Tous les e-mails déclarés dans WooCommerce, extensions comprises. Un e-mail désactivé garde le rendu WooCommerce natif. Les sujets et titres se règlent dans chaque e-mail WooCommerce.', 'bb-woo-mail-layout' ); ?></p></div></div>
				<div class="bb-wml-toolbar">
					<div class="bb-wml-seg" role="group" aria-label="<?php esc_attr_e( 'Filtrer par destinataire', 'bb-woo-mail-layout' ); ?>">
						<button type="button" aria-pressed="true" data-bb-filter="all"><?php esc_html_e( 'Tous', 'bb-woo-mail-layout' ); ?></button><button type="button" aria-pressed="false" data-bb-filter="client"><?php esc_html_e( 'Client', 'bb-woo-mail-layout' ); ?></button><button type="button" aria-pressed="false" data-bb-filter="admin"><?php esc_html_e( 'Administrateur', 'bb-woo-mail-layout' ); ?></button>
					</div>
					<input type="search" id="bb-wml-mail-search" class="bb-wml-search" placeholder="<?php esc_attr_e( 'Rechercher un e-mail', 'bb-woo-mail-layout' ); ?>" aria-label="<?php esc_attr_e( 'Rechercher un e-mail', 'bb-woo-mail-layout' ); ?>">
					<div class="bb-wml-bulk">
						<button type="button" class="button-link" data-bb-bulk="on"><?php esc_html_e( 'Tout activer', 'bb-woo-mail-layout' ); ?></button>
						<button type="button" class="button-link" data-bb-bulk="off"><?php esc_html_e( 'Tout désactiver', 'bb-woo-mail-layout' ); ?></button>
					</div>
				</div>
				<div class="bb-wml-toolbar is-tokens">
					<span class="bb-wml-hint"><?php esc_html_e( 'Placeholders des introductions (cliquer pour insérer dans le texte ouvert) :', 'bb-woo-mail-layout' ); ?></span>
					<div class="bb-wml-tokens" data-bb-target="intro">
						<?php foreach ( Placeholders::documented() as $placeholder => $label ) : ?>
							<button type="button" class="bb-wml-token" title="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $placeholder ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="bb-wml-mails">
					<?php foreach ( $emails as $id => $email ) : ?>
						<?php
						$enabled = $this->registry->is_enabled( $id );
						$custom  = '' !== (string) ( $intros[ $id ] ?? '' );
						?>
						<div class="bb-wml-mail<?php echo $enabled ? '' : ' is-off'; ?>" data-bb-mail="<?php echo esc_attr( $id ); ?>" data-bb-client="<?php echo $email['customer'] ? '1' : '0'; ?>" data-bb-search="<?php echo esc_attr( strtolower( $email['title'] . ' ' . $id ) ); ?>">
							<div class="bb-wml-mail__main">
								<label class="bb-wml-switch">
									<input type="hidden" name="<?php echo esc_attr( self::id( 'emails' ) . '[' . $id . ']' ); ?>" value="no">
									<input type="checkbox" name="<?php echo esc_attr( self::id( 'emails' ) . '[' . $id . ']' ); ?>" value="yes" data-bb-mail-toggle <?php checked( $enabled ); ?>>
									<span aria-hidden="true"></span>
									<span class="screen-reader-text">
										<?php
										/* translators: %s: titre de l'e-mail. */
										echo esc_html( sprintf( __( 'Mettre en forme : %s', 'bb-woo-mail-layout' ), $email['title'] ) );
										?>
									</span>
								</label>
								<div class="bb-wml-mail__title">
									<strong><?php echo esc_html( $email['title'] ); ?></strong>
									<code><?php echo esc_html( $id ); ?></code>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( $email['class'] ) ) ); ?>"><?php esc_html_e( 'Sujet et titre', 'bb-woo-mail-layout' ); ?></a>
								</div>
								<span class="bb-wml-pill <?php echo $email['customer'] ? 'is-client' : 'is-admin'; ?>"><?php echo $email['customer'] ? esc_html__( 'Client', 'bb-woo-mail-layout' ) : esc_html__( 'Administrateur', 'bb-woo-mail-layout' ); ?></span>
								<span class="bb-wml-src"><?php echo esc_html( '' !== $email['source'] ? $email['source'] : '—' ); ?></span>
								<?php
								/* translators: %s: titre de l'e-mail. */
								$layout_label = sprintf( __( 'Layout : %s', 'bb-woo-mail-layout' ), $email['title'] );
								?>
								<select class="bb-wml-mail__layout" data-bb-mail-layout name="<?php echo esc_attr( self::id( 'email_layouts' ) . '[' . $id . ']' ); ?>" aria-label="<?php echo esc_attr( $layout_label ); ?>">
									<option value="" data-bb-general>
										<?php
										/* translators: %s: nom du layout général. */
										echo esc_html( sprintf( __( 'Général (%s)', 'bb-woo-mail-layout' ), $general ) );
										?>
									</option>
									<?php foreach ( $layouts as $slug => $layout ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) ( $chosen[ $id ] ?? '' ), (string) $slug ); ?>><?php echo esc_html( $layout['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="bb-wml-intro-btn<?php echo $custom ? ' is-custom' : ''; ?>" aria-expanded="false" aria-controls="bb-wml-intro-<?php echo esc_attr( $id ); ?>">
									<span class="bb-wml-dot" aria-hidden="true"></span><span class="bb-wml-intro-btn__label"><?php echo $custom ? esc_html__( 'Intro personnalisée', 'bb-woo-mail-layout' ) : esc_html__( 'Intro par défaut', 'bb-woo-mail-layout' ); ?></span>
								</button>
							</div>
							<div class="bb-wml-mail__intro" id="bb-wml-intro-<?php echo esc_attr( $id ); ?>" hidden>
								<textarea rows="4" data-bb-intro name="<?php echo esc_attr( self::id( 'intros' ) . '[' . $id . ']' ); ?>" placeholder="<?php echo esc_attr( $defaults[ $id ] ?? $texts->generic( $email['customer'] ) ); ?>" aria-label="<?php echo esc_attr( $email['title'] ); ?>"><?php echo esc_textarea( (string) ( $intros[ $id ] ?? '' ) ); ?></textarea>
								<p class="bb-wml-hint"><?php esc_html_e( 'Vide : le texte par défaut (en gris) est utilisé. Une ligne vide sépare deux paragraphes.', 'bb-woo-mail-layout' ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
					<p class="bb-wml-empty" id="bb-wml-mails-empty" hidden><?php esc_html_e( 'Aucun e-mail ne correspond à cette recherche.', 'bb-woo-mail-layout' ); ?></p>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Onglet Outils : e-mail de test, import / export.
	 * Les champs n'ont pas d'attribut name : ils ne sont pas enregistrés avec le formulaire WooCommerce.
	 *
	 * @param array<string,array<string,mixed>> $emails E-mails du registre.
	 * @param \WC_Order[]                       $orders Commandes récentes.
	 */
	private function panel_tools( array $emails, array $orders ): void {
		?>
		<section class="bb-wml-panel bb-wml-tools" id="bb-wml-panel-outils" role="tabpanel" aria-labelledby="bb-wml-tab-outils" hidden>
			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'E-mail de test', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Envoie l’e-mail choisi avec les données d’une vraie commande. Les modifications non enregistrées sont prises en compte.', 'bb-woo-mail-layout' ); ?></p></div></div>
				<div class="bb-wml-card__body">
					<div class="bb-wml-row">
						<div class="bb-wml-field">
							<label for="bb-wml-tool-email"><?php esc_html_e( 'E-mail', 'bb-woo-mail-layout' ); ?></label>
							<?php $this->email_select( 'bb-wml-tool-email', $emails ); ?>
						</div>
						<div class="bb-wml-field">
							<label for="bb-wml-tool-order"><?php esc_html_e( 'Commande utilisée comme jeu de données', 'bb-woo-mail-layout' ); ?></label>
							<?php self::order_select( 'bb-wml-tool-order', $orders ); ?>
						</div>
					</div>
					<div class="bb-wml-field">
						<label for="bb-wml-tool-to"><?php esc_html_e( 'Destinataire du test', 'bb-woo-mail-layout' ); ?></label>
						<input type="email" id="bb-wml-tool-to" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
					</div>
					<p class="bb-wml-actions">
						<button type="button" class="button button-primary" id="bb-wml-send-test"><?php esc_html_e( 'Envoyer l’e-mail de test', 'bb-woo-mail-layout' ); ?></button>
						<span class="bb-wml-status" id="bb-wml-tools-status" role="status" aria-live="polite"></span>
					</p>
				</div>
			</div>

			<div class="bb-wml-card">
				<div class="bb-wml-card__head"><div><h3><?php esc_html_e( 'Import / export', 'bb-woo-mail-layout' ); ?></h3><p><?php esc_html_e( 'Copier la mise en forme d’un site à l’autre.', 'bb-woo-mail-layout' ); ?></p></div></div>
				<div class="bb-wml-card__body bb-wml-transfer">
					<p class="bb-wml-actions">
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . ImportExport::EXPORT_ACTION ), ImportExport::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Exporter les réglages (JSON)', 'bb-woo-mail-layout' ); ?></a>
					</p>
					<p class="bb-wml-actions">
						<input type="file" id="bb-wml-import-file" accept="application/json,.json">
						<button type="button" class="button" id="bb-wml-import"><?php esc_html_e( 'Importer…', 'bb-woo-mail-layout' ); ?></button>
					</p>
					<div class="bb-wml-confirm" id="bb-wml-import-confirm" hidden>
						<span><?php esc_html_e( 'L’import remplace tous les réglages de cette page. Les clés inconnues sont refusées.', 'bb-woo-mail-layout' ); ?></span>
						<button type="button" class="button button-primary" id="bb-wml-import-yes"><?php esc_html_e( 'Remplacer les réglages', 'bb-woo-mail-layout' ); ?></button>
						<button type="button" class="button-link" id="bb-wml-import-no"><?php esc_html_e( 'Annuler', 'bb-woo-mail-layout' ); ?></button>
					</div>
					<span class="bb-wml-status" id="bb-wml-import-status" role="status" aria-live="polite"></span>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Colonne d'aperçu (iframe sandboxée, 600 px).
	 *
	 * @param array<string,array<string,mixed>> $emails E-mails du registre.
	 * @param \WC_Order[]                       $orders Commandes récentes.
	 */
	private function preview( array $emails, array $orders ): void {
		?>
		<aside class="bb-wml-card bb-wml-preview" aria-label="<?php esc_attr_e( 'Aperçu', 'bb-woo-mail-layout' ); ?>">
			<div class="bb-wml-card__head">
				<h3><?php esc_html_e( 'Aperçu', 'bb-woo-mail-layout' ); ?></h3>
				<span class="bb-wml-live" id="bb-wml-preview-status" role="status" aria-live="polite"><?php esc_html_e( 'Mis à jour en direct', 'bb-woo-mail-layout' ); ?></span>
			</div>
			<div class="bb-wml-preview__bar">
				<?php $this->email_select( 'bb-wml-preview-email', $emails, __( 'E-mail à prévisualiser', 'bb-woo-mail-layout' ) ); ?>
				<?php self::order_select( 'bb-wml-preview-order', $orders, __( 'Commande utilisée pour l’aperçu', 'bb-woo-mail-layout' ) ); ?>
				<div class="bb-wml-seg" role="group" aria-label="<?php esc_attr_e( 'Largeur de l’aperçu', 'bb-woo-mail-layout' ); ?>">
					<button type="button" aria-pressed="true" data-bb-device="desktop"><?php esc_html_e( 'Ordinateur', 'bb-woo-mail-layout' ); ?></button><button type="button" aria-pressed="false" data-bb-device="mobile"><?php esc_html_e( 'Mobile', 'bb-woo-mail-layout' ); ?></button>
				</div>
			</div>
			<div class="bb-wml-stage" id="bb-wml-stage">
				<iframe id="bb-wml-preview-frame" class="bb-wml-preview-frame" sandbox="" title="<?php esc_attr_e( 'Aperçu de l’e-mail', 'bb-woo-mail-layout' ); ?>"></iframe>
				<p class="bb-wml-stage__error" id="bb-wml-preview-error" hidden></p>
			</div>
		</aside>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * Morceaux d'interface
	 * ------------------------------------------------------------------
	 */

	/**
	 * Interrupteur enregistré sous bb_woo_mail_layout[clé].
	 *
	 * @param string $key     Clé.
	 * @param string $label   Libellé (lecteurs d'écran).
	 * @param bool   $checked Coché.
	 */
	private static function switch_input( string $key, string $label, bool $checked ): void {
		?>
		<label class="bb-wml-switch">
			<input type="checkbox" id="bb-wml-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::id( $key ) ); ?>" value="yes" data-bb-key="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?>>
			<span aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Début d'un bloc « interrupteur + réglages » (à refermer par deux </div>).
	 *
	 * @param string              $key      Clé du booléen.
	 * @param string              $title    Titre.
	 * @param string              $desc     Description.
	 * @param array<string,mixed> $settings Réglages.
	 * @param bool                $has_body Le bloc a des réglages dépliables.
	 */
	private static function block_open( string $key, string $title, string $desc, array $settings, bool $has_body = true ): void {
		$on = 'yes' === $settings[ $key ];
		?>
		<div class="bb-wml-block<?php echo $on ? '' : ' is-off'; ?>" data-bb-block="<?php echo esc_attr( $key ); ?>">
			<div class="bb-wml-block__head">
				<?php self::switch_input( $key, $title, $on ); ?>
				<div class="bb-wml-block__txt"><strong><?php echo esc_html( $title ); ?></strong><span><?php echo esc_html( $desc ); ?></span></div>
				<span class="bb-wml-block__state" data-bb-summary="<?php echo esc_attr( $key ); ?>"></span>
			</div>
			<div class="<?php echo $has_body ? 'bb-wml-block__body' : 'bb-wml-block__link'; ?>">
		<?php
	}

	/**
	 * Champ texte simple.
	 *
	 * @param string              $key         Clé.
	 * @param string              $label       Libellé.
	 * @param array<string,mixed> $settings    Réglages.
	 * @param string              $type        Type d'input.
	 * @param string              $placeholder Placeholder.
	 */
	private static function text_field( string $key, string $label, array $settings, string $type = 'text', string $placeholder = '' ): void {
		$html_id = 'bb-wml-' . str_replace( '_', '-', $key );
		?>
		<div class="bb-wml-field">
			<label for="<?php echo esc_attr( $html_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $html_id ); ?>" name="<?php echo esc_attr( self::id( $key ) ); ?>" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		</div>
		<?php
	}

	/**
	 * Pastille de couleur : sélecteur natif (non enregistré) + code hexadécimal (enregistré).
	 *
	 * @param string              $key      Clé.
	 * @param string              $label    Libellé.
	 * @param string              $desc     Précision.
	 * @param array<string,mixed> $settings Réglages.
	 */
	private static function swatch( string $key, string $label, string $desc, array $settings ): void {
		$value   = (string) $settings[ $key ];
		$html_id = 'bb-wml-' . str_replace( '_', '-', $key );
		?>
		<div class="bb-wml-swatch" data-bb-swatch="<?php echo esc_attr( $key ); ?>">
			<input type="color" class="bb-wml-swatch__pick" value="<?php echo esc_attr( '' !== $value ? $value : '#e5e5e5' ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<div class="bb-wml-swatch__txt">
				<label for="<?php echo esc_attr( $html_id ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php if ( '' !== $desc ) : ?>
					<span class="bb-wml-hint"><?php echo esc_html( $desc ); ?></span>
				<?php endif; ?>
				<input type="text" id="<?php echo esc_attr( $html_id ); ?>" class="bb-wml-swatch__hex" name="<?php echo esc_attr( self::id( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="7" spellcheck="false" placeholder="<?php echo 'color_border' === $key ? esc_attr__( 'Auto', 'bb-woo-mail-layout' ) : '#000000'; ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Vignette d'un layout.
	 *
	 * @param string $slug Slug du layout.
	 */
	private static function thumb( string $slug ): void {
		$known = in_array( $slug, array( 'classique', 'sobre', 'ecommerce' ), true ) ? $slug : 'generic';
		?>
		<span class="bb-wml-thumb is-<?php echo esc_attr( $known ); ?>" aria-hidden="true">
			<?php if ( 'classique' === $known ) : ?>
				<span class="band"></span><span class="paper"><i class="w60"></i><i></i><i class="w80"></i><i class="w40"></i></span>
			<?php elseif ( 'sobre' === $known ) : ?>
				<i class="w40"></i><span class="rule"></span><i class="w60"></i><i></i><i class="w80"></i>
			<?php elseif ( 'ecommerce' === $known ) : ?>
				<span class="bar"></span><i class="w60"></i><span class="prods"><i></i><i></i><i></i></span><i class="w80"></i>
			<?php else : ?>
				<i class="w60"></i><i></i><i class="w80"></i><i class="w40"></i>
			<?php endif; ?>
		</span>
		<?php
	}

	/**
	 * Liste déroulante des e-mails.
	 *
	 * @param string                            $html_id Id HTML.
	 * @param array<string,array<string,mixed>> $emails  E-mails du registre.
	 * @param string                            $label   Libellé (aria), si pas de <label>.
	 */
	private function email_select( string $html_id, array $emails, string $label = '' ): void {
		?>
		<select id="<?php echo esc_attr( $html_id ); ?>" data-bb-email-select <?php echo '' !== $label ? 'aria-label="' . esc_attr( $label ) . '"' : ''; ?>>
			<?php foreach ( $emails as $id => $email ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr( $email['title'] ); ?>" <?php selected( self::PREVIEW_EMAIL, $id ); ?>>
					<?php echo esc_html( $email['title'] . ( $this->registry->is_enabled( $id ) ? '' : ' — ' . __( 'rendu natif', 'bb-woo-mail-layout' ) ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Liste déroulante des commandes récentes.
	 *
	 * @param string      $html_id Id HTML.
	 * @param \WC_Order[] $orders  Commandes.
	 * @param string      $label   Libellé (aria), si pas de <label>.
	 */
	private static function order_select( string $html_id, array $orders, string $label = '' ): void {
		?>
		<select id="<?php echo esc_attr( $html_id ); ?>" <?php echo '' !== $label ? 'aria-label="' . esc_attr( $label ) . '"' : ''; ?>>
			<?php if ( ! $orders ) : ?>
				<option value="0"><?php esc_html_e( 'Aucune commande sur ce site', 'bb-woo-mail-layout' ); ?></option>
			<?php endif; ?>
			<?php foreach ( $orders as $order ) : ?>
				<option value="<?php echo (int) $order->get_id(); ?>">
					<?php
					echo esc_html(
						sprintf(
							'#%1$s — %2$s — %3$s — %4$s',
							$order->get_order_number(),
							$order->get_formatted_billing_full_name(),
							$order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
							wc_get_order_status_name( $order->get_status() )
						)
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * Assets, liens
	 * ------------------------------------------------------------------
	 */

	/**
	 * Classe sur <body> pour cibler la barre d'enregistrement et le chrome WooCommerce.
	 *
	 * @param string $classes Classes existantes.
	 */
	public function body_class( $classes ): string {
		return self::is_current_page() ? $classes . ' bb-wml-page' : (string) $classes;
	}

	/**
	 * Scripts de l'onglet uniquement.
	 */
	public function enqueue(): void {
		if ( ! self::is_current_page() ) {
			return;
		}

		wp_enqueue_media();
		// Recherche de produits (champ « Produits mis en avant ») : scripts WooCommerce standard.
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'bb-wml-settings', BB_WML_URL . 'assets/admin/settings.css', array(), BB_WML_VERSION );
		wp_enqueue_script( 'bb-wml-settings', BB_WML_URL . 'assets/admin/settings.js', array( 'media-editor' ), BB_WML_VERSION, true );

		$defaults = Options::defaults();
		wp_localize_script(
			'bb-wml-settings',
			'bbWml',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'option'   => BB_WML_OPTION,
				'defaults' => array(
					'color_primary'    => $defaults['color_primary'],
					'color_button'     => $defaults['color_button'],
					'color_text'       => $defaults['color_text'],
					'color_border'     => $defaults['color_border'],
					'color_background' => $defaults['color_background'],
				),
				'i18n'     => array(
					'chooseLogo'    => __( 'Choisir le logo', 'bb-woo-mail-layout' ),
					'useImage'      => __( 'Utiliser cette image', 'bb-woo-mail-layout' ),
					'live'          => __( 'Mis à jour en direct', 'bb-woo-mail-layout' ),
					'loading'       => __( 'Mise à jour…', 'bb-woo-mail-layout' ),
					'sending'       => __( 'Envoi en cours…', 'bb-woo-mail-layout' ),
					'importing'     => __( 'Import en cours…', 'bb-woo-mail-layout' ),
					'error'         => __( 'Une erreur est survenue.', 'bb-woo-mail-layout' ),
					'chooseFile'    => __( 'Choisissez d’abord un fichier JSON exporté depuis cette page.', 'bb-woo-mail-layout' ),
					'introCustom'   => __( 'Intro personnalisée', 'bb-woo-mail-layout' ),
					'introDefault'  => __( 'Intro par défaut', 'bb-woo-mail-layout' ),
					'nativeSuffix'  => __( 'rendu natif', 'bb-woo-mail-layout' ),
					/* translators: %s: nom du layout général. */
					'generalLayout' => __( 'Général (%s)', 'bb-woo-mail-layout' ),
					/* translators: 1: largeur, 2: hauteur (px). */
					'logoSize'      => __( 'Rendu actuel : %1$s × %2$s px.', 'bb-woo-mail-layout' ),
					/* translators: %d: nombre d'informations renseignées. */
					'helpFilled'    => __( '%d / 3 renseignés', 'bb-woo-mail-layout' ),
					'helpEmpty'     => __( 'Vide : bloc masqué', 'bb-woo-mail-layout' ),
					/* translators: %d: nombre de réseaux. */
					'socialCount'   => __( '%d réseau(x)', 'bb-woo-mail-layout' ),
					/* translators: %d: nombre de produits. */
					'featuredCount' => __( '%d / 3 produits', 'bb-woo-mail-layout' ),
				),
			)
		);
	}

	/**
	 * Lien « Réglages » dans la liste des extensions.
	 *
	 * @param string[] $links Liens.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Réglages', 'bb-woo-mail-layout' ) ) );
		return $links;
	}
}
