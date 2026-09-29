<?php
/**
 * Onglet « Mise en forme bleuebuzz » dans WooCommerce → Réglages → E-mails (Settings API WooCommerce).
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
 * Champs, rendu des champs personnalisés, assainissement.
 */
final class SettingsPage {

	public const SECTION = 'bb_mail_layout';

	public const NONCE_ACTION = 'bb_wml_admin';

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

		add_action( 'woocommerce_admin_field_bb_wml_media', array( $this, 'field_media' ) );
		add_action( 'woocommerce_admin_field_bb_wml_hidden', array( $this, 'field_hidden' ) );
		add_action( 'woocommerce_admin_field_bb_wml_editor', array( $this, 'field_editor' ) );
		add_action( 'woocommerce_admin_field_bb_wml_emails', array( $this, 'field_emails' ) );
		add_action( 'woocommerce_admin_field_bb_wml_intros', array( $this, 'field_intros' ) );
		add_action( 'woocommerce_admin_field_bb_wml_tools', array( $this, 'field_tools' ) );
		add_action( 'woocommerce_admin_field_bb_wml_products', array( $this, 'field_products' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
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
	 * Définition des champs (types WooCommerce standard + quelques types bb_wml_*).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fields(): array {
		$d       = Options::defaults();
		$layouts = array_map( static fn( $layout ) => $layout['label'], LayoutRenderer::available_layouts() );

		$fields = array(
			array(
				'title' => __( 'Mise en forme bleuebuzz', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'desc'  => __( 'Layout appliqué aux e-mails WooCommerce. Les sujets et titres se règlent toujours dans chaque e-mail WooCommerce (onglet « Options e-mail »).', 'bb-woo-mail-layout' ),
				'id'    => 'bb_wml_general',
			),
			array(
				'title'   => __( 'Layout', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'layout' ),
				'type'    => 'select',
				'options' => $layouts,
				'default' => $d['layout'],
				'desc'    => __( 'Classique : fond clair et bandeau coloré. Sobre : tout blanc, filet de couleur. E-commerce : barre d’accent et photos produit dans le tableau de commande.', 'bb-woo-mail-layout' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bb_wml_general',
			),

			array(
				'title' => __( 'Logo', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'id'    => 'bb_wml_logo',
			),
			array(
				'title'   => __( 'Bandeau logo', 'bb-woo-mail-layout' ),
				'desc'    => __( 'Afficher le logo en tête d’e-mail (lien vers la boutique)', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'show_logo' ),
				'type'    => 'checkbox',
				'default' => $d['show_logo'],
			),
			array(
				'title' => __( 'Image', 'bb-woo-mail-layout' ),
				'desc'  => __( 'Image de la médiathèque de ce site (PNG ou JPG). Hauteur affichée : 80 px maximum.', 'bb-woo-mail-layout' ),
				'id'    => self::id( 'logo_url' ),
				'type'  => 'bb_wml_media',
			),
			array(
				'id'   => self::id( 'logo_id' ),
				'type' => 'bb_wml_hidden',
			),
			array(
				'title'             => __( 'Largeur maximale (px)', 'bb-woo-mail-layout' ),
				'id'                => self::id( 'logo_max_width' ),
				'type'              => 'number',
				'default'           => $d['logo_max_width'],
				'css'               => 'width:90px;',
				'custom_attributes' => array(
					'min'  => 50,
					'max'  => 600,
					'step' => 10,
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bb_wml_logo',
			),

			array(
				'title' => __( 'Couleurs et police', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'id'    => 'bb_wml_style',
			),
			array(
				'title'   => __( 'Couleur principale', 'bb-woo-mail-layout' ),
				'desc'    => __( 'Bandeau, titres, liens.', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'color_primary' ),
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => $d['color_primary'],
			),
			array(
				'title'   => __( 'Couleur des boutons', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'color_button' ),
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => $d['color_button'],
			),
			array(
				'title'   => __( 'Couleur du texte', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'color_text' ),
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => $d['color_text'],
			),
			array(
				'title'   => __( 'Couleur des bordures', 'bb-woo-mail-layout' ),
				'desc'    => __( 'Tableau de commande, adresses, séparateurs. Vide : teinte calculée depuis la couleur du texte.', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'color_border' ),
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => '',
			),
			array(
				'title'   => __( 'Couleur de fond extérieur', 'bb-woo-mail-layout' ),
				'desc'    => __( 'Layout Classique uniquement.', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'color_background' ),
				'type'    => 'color',
				'css'     => 'width:6em;',
				'default' => $d['color_background'],
			),
			array(
				'title'   => __( 'Police', 'bb-woo-mail-layout' ),
				'id'      => self::id( 'font' ),
				'type'    => 'select',
				'default' => $d['font'],
				'options' => array(
					'arial'     => 'Arial',
					'helvetica' => 'Helvetica',
					'georgia'   => 'Georgia',
					'verdana'   => 'Verdana',
					'trebuchet' => 'Trebuchet MS',
					'google'    => __( 'Google Font (repli sur Arial)', 'bb-woo-mail-layout' ),
				),
			),
			array(
				'title'       => __( 'Nom de la Google Font', 'bb-woo-mail-layout' ),
				'desc'        => __( 'Ex. : Montserrat. Affichée par Apple Mail et iOS ; Gmail et Outlook affichent la police de repli.', 'bb-woo-mail-layout' ),
				'id'          => self::id( 'google_font' ),
				'type'        => 'text',
				'placeholder' => 'Montserrat',
				'default'     => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bb_wml_style',
			),

			array(
				'title' => __( 'Blocs affichés', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'id'    => 'bb_wml_blocks',
			),
			array(
				'title'         => __( 'Blocs', 'bb-woo-mail-layout' ),
				'desc'          => __( 'Paragraphe d’introduction', 'bb-woo-mail-layout' ),
				'id'            => self::id( 'show_intro' ),
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'start',
			),
			array(
				'desc'          => __( 'Bloc « Besoin d’aide ? »', 'bb-woo-mail-layout' ),
				'id'            => self::id( 'show_help' ),
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			),
			array(
				'desc'          => __( 'Réseaux sociaux', 'bb-woo-mail-layout' ),
				'id'            => self::id( 'show_social' ),
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			),
			array(
				'desc'          => __( 'Pied de page', 'bb-woo-mail-layout' ),
				'id'            => self::id( 'show_footer' ),
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'end',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bb_wml_blocks',
			),

			array(
				'title' => __( 'Besoin d’aide ?', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'desc'  => __( 'Le bloc n’apparaît que si au moins une information est renseignée.', 'bb-woo-mail-layout' ),
				'id'    => 'bb_wml_help',
			),
			array(
				'title' => __( 'Téléphone', 'bb-woo-mail-layout' ),
				'id'    => self::id( 'contact_phone' ),
				'type'  => 'text',
			),
			array(
				'title' => __( 'E-mail', 'bb-woo-mail-layout' ),
				'id'    => self::id( 'contact_email' ),
				'type'  => 'email',
			),
			array(
				'title'       => __( 'Horaires', 'bb-woo-mail-layout' ),
				'id'          => self::id( 'contact_hours' ),
				'type'        => 'text',
				'placeholder' => __( 'Du lundi au vendredi, de 9 h à 18 h', 'bb-woo-mail-layout' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bb_wml_help',
			),

			array(
				'title' => __( 'Réseaux sociaux', 'bb-woo-mail-layout' ),
				'type'  => 'title',
				'desc'  => __( 'Seuls les réseaux dont l’URL est renseignée sont affichés.', 'bb-woo-mail-layout' ),
				'id'    => 'bb_wml_social',
			),
		);

		foreach ( array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'tiktok'    => 'TikTok',
			'youtube'   => 'YouTube',
		) as $network => $label ) {
			$fields[] = array(
				'title'       => $label,
				'id'          => self::id( 'social_' . $network ),
				'type'        => 'url',
				'placeholder' => 'https://',
				'css'         => 'width:25em;',
			);
		}

		return array_merge(
			$fields,
			array(
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_social',
				),

				array(
					'title' => __( 'Pied de page', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'id'    => 'bb_wml_footer',
				),
				array(
					'title' => __( 'Nom légal', 'bb-woo-mail-layout' ),
					'id'    => self::id( 'footer_legal_name' ),
					'type'  => 'text',
				),
				array(
					'title' => __( 'Adresse', 'bb-woo-mail-layout' ),
					'id'    => self::id( 'footer_address' ),
					'type'  => 'textarea',
					'css'   => 'width:25em;height:4.5em;',
				),
				array(
					'title'   => __( 'Lien vers le site', 'bb-woo-mail-layout' ),
					'desc'    => __( 'Afficher le lien vers la boutique', 'bb-woo-mail-layout' ),
					'id'      => self::id( 'footer_show_site_link' ),
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				array(
					'title' => __( 'Mentions', 'bb-woo-mail-layout' ),
					'desc'  => __( 'Gras, lien et saut de ligne uniquement. Placeholders autorisés.', 'bb-woo-mail-layout' ),
					'id'    => self::id( 'footer_text' ),
					'type'  => 'bb_wml_editor',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_footer',
				),

				array(
					'title' => __( 'Produits mis en avant', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'desc'  => __( 'Jusqu’à 3 produits (photo, titre, prix) affichés sous la commande, dans les e-mails envoyés au client.', 'bb-woo-mail-layout' ),
					'id'    => 'bb_wml_featured',
				),
				array(
					'title'   => __( 'Bloc produits', 'bb-woo-mail-layout' ),
					'desc'    => __( 'Afficher les produits mis en avant', 'bb-woo-mail-layout' ),
					'id'      => self::id( 'show_featured' ),
					'type'    => 'checkbox',
					'default' => 'no',
				),
				array(
					'title'       => __( 'Titre du bloc', 'bb-woo-mail-layout' ),
					'id'          => self::id( 'featured_title' ),
					'type'        => 'text',
					'placeholder' => __( 'Vous aimerez aussi', 'bb-woo-mail-layout' ),
				),
				array(
					'title' => __( 'Produits', 'bb-woo-mail-layout' ),
					'desc'  => __( '3 produits maximum. Les produits non publiés ou masqués du catalogue sont ignorés.', 'bb-woo-mail-layout' ),
					'id'    => self::id( 'featured_products' ),
					'type'  => 'bb_wml_products',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_featured',
				),

				array(
					'title' => __( 'CSS personnalisé', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'desc'  => __( 'Ajouté après le CSS du layout (il l’emporte). Jetons disponibles : {{primary}}, {{button}}, {{text}}, {{muted}}, {{border}}, {{soft}}, {{background}}, {{font}}. Balises, @import et scripts sont retirés.', 'bb-woo-mail-layout' ),
					'id'    => 'bb_wml_css',
				),
				array(
					'title'       => __( 'CSS', 'bb-woo-mail-layout' ),
					'id'          => self::id( 'custom_css' ),
					'type'        => 'textarea',
					'css'         => 'width:100%;max-width:720px;height:12em;font-family:Consolas,Monaco,monospace;',
					'placeholder' => ".bb-heading { letter-spacing: .5px; }\n.bb-featured-price { color: {{button}}; }",
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_css',
				),

				array(
					'title' => __( 'E-mails mis en forme', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'desc'  => __( 'Tous les e-mails déclarés dans WooCommerce, extensions comprises. Un e-mail décoché garde le rendu WooCommerce natif.', 'bb-woo-mail-layout' ),
					'id'    => 'bb_wml_emails',
				),
				array(
					'id'   => self::id( 'emails' ),
					'type' => 'bb_wml_emails',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_emails',
				),

				array(
					'title' => __( 'Textes d’introduction', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'desc'  => __( 'Laisser vide pour utiliser le texte par défaut (affiché en gris). Une ligne vide sépare deux paragraphes.', 'bb-woo-mail-layout' ),
					'id'    => 'bb_wml_intros',
				),
				array(
					'id'   => self::id( 'intros' ),
					'type' => 'bb_wml_intros',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_intros',
				),

				array(
					'title' => __( 'Prévisualisation, test, import / export', 'bb-woo-mail-layout' ),
					'type'  => 'title',
					'id'    => 'bb_wml_tools',
				),
				array(
					'id'        => 'bb_wml_tools',
					'type'      => 'bb_wml_tools',
					'is_option' => false,
				),
				array(
					'type' => 'sectionend',
					'id'   => 'bb_wml_tools',
				),
			)
		);
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
	 * Champs personnalisés
	 * ------------------------------------------------------------------
	 */

	/**
	 * Sélecteur de logo (médiathèque).
	 *
	 * @param array<string,mixed> $field Champ.
	 */
	public function field_media( $field ): void {
		$url    = (string) Options::get( 'logo_url' );
		$id     = (int) Options::get( 'logo_id' );
		$status = LogoChecker::status();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label for="bb-wml-logo-url"><?php echo esc_html( $field['title'] ); ?></label></th>
			<td class="forminp">
				<div class="bb-wml-media">
					<img class="bb-wml-media__preview" src="<?php echo esc_url( $url ); ?>" alt="" <?php echo '' === $url ? 'hidden' : ''; ?>>
					<input type="url" id="bb-wml-logo-url" class="regular-text" name="<?php echo esc_attr( self::id( 'logo_url' ) ); ?>" value="<?php echo esc_attr( $url ); ?>">
					<input type="hidden" id="bb-wml-logo-id" name="<?php echo esc_attr( self::id( 'logo_id' ) ); ?>" value="<?php echo (int) $id; ?>">
					<button type="button" class="button bb-wml-media__choose"><?php esc_html_e( 'Choisir une image', 'bb-woo-mail-layout' ); ?></button>
					<button type="button" class="button-link bb-wml-media__remove"><?php esc_html_e( 'Retirer', 'bb-woo-mail-layout' ); ?></button>
				</div>
				<p class="description"><?php echo esc_html( $field['desc'] ?? '' ); ?></p>
				<?php if ( $status && '' !== $url && $status['url'] === $url ) : ?>
					<p class="bb-wml-logo-status is-<?php echo esc_attr( $status['state'] ); ?>"><?php echo esc_html( $status['message'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Champ logo_id : l'input caché est rendu par field_media(), rien à afficher ici.
	 */
	public function field_hidden(): void {}

	/**
	 * Sélecteur de produits (recherche WooCommerce), 3 maximum.
	 *
	 * @param array<string,mixed> $field Champ.
	 */
	public function field_products( $field ): void {
		$ids = (array) Options::get( 'featured_products' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label for="bb-wml-featured-products"><?php echo esc_html( $field['title'] ); ?></label></th>
			<td class="forminp">
				<select id="bb-wml-featured-products" class="wc-product-search" multiple="multiple" style="width:50%;min-width:320px;"
					name="<?php echo esc_attr( self::id( 'featured_products' ) ); ?>[]"
					data-placeholder="<?php esc_attr_e( 'Rechercher un produit…', 'bb-woo-mail-layout' ); ?>"
					data-action="woocommerce_json_search_products_and_variations"
					data-maximum-selection-length="<?php echo (int) Options::MAX_FEATURED; ?>">
					<?php foreach ( $ids as $id ) : ?>
						<?php $product = wc_get_product( (int) $id ); ?>
						<?php if ( $product ) : ?>
							<option value="<?php echo (int) $id; ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html( $field['desc'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Éditeur restreint du pied de page (gras, lien).
	 *
	 * @param array<string,mixed> $field Champ.
	 */
	public function field_editor( $field ): void {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label for="bb_wml_footer_text"><?php echo esc_html( $field['title'] ); ?></label></th>
			<td class="forminp bb-wml-editor">
				<?php
				wp_editor(
					(string) Options::get( 'footer_text' ),
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
				<p class="description"><?php echo esc_html( $field['desc'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Tableau d'activation par e-mail.
	 */
	public function field_emails(): void {
		$name = self::id( 'emails' );
		?>
		<tr valign="top">
			<td class="forminp" colspan="2" style="padding-left:0;">
				<table class="widefat striped bb-wml-emails">
					<thead>
						<tr>
							<th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Actif', 'bb-woo-mail-layout' ); ?></span></th>
							<th><?php esc_html_e( 'E-mail', 'bb-woo-mail-layout' ); ?></th>
							<th><?php esc_html_e( 'ID', 'bb-woo-mail-layout' ); ?></th>
							<th><?php esc_html_e( 'Destinataire', 'bb-woo-mail-layout' ); ?></th>
							<th><?php esc_html_e( 'Source', 'bb-woo-mail-layout' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'bb-woo-mail-layout' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->registry->all() as $id => $email ) : ?>
							<?php $enabled = $this->registry->is_enabled( $id ); ?>
							<tr>
								<th class="check-column">
									<input type="hidden" name="<?php echo esc_attr( $name . '[' . $id . ']' ); ?>" value="no">
									<input type="checkbox" id="bb-wml-email-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . '[' . $id . ']' ); ?>" value="yes" <?php checked( $enabled ); ?>>
								</th>
								<td>
									<label for="bb-wml-email-<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $email['title'] ); ?></strong></label>
									<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( $email['class'] ) ) ); ?>"><?php esc_html_e( 'Sujet et titre', 'bb-woo-mail-layout' ); ?></a>
								</td>
								<td><code><?php echo esc_html( $id ); ?></code></td>
								<td><?php echo $email['customer'] ? esc_html__( 'Client', 'bb-woo-mail-layout' ) : esc_html__( 'Administrateur', 'bb-woo-mail-layout' ); ?></td>
								<td><?php echo esc_html( '' !== $email['source'] ? $email['source'] : '—' ); ?></td>
								<td>
									<?php if ( $enabled ) : ?>
										<span class="bb-wml-badge is-on"><?php esc_html_e( 'Layout bleuebuzz', 'bb-woo-mail-layout' ); ?></span>
									<?php else : ?>
										<span class="bb-wml-badge"><?php esc_html_e( 'Rendu WooCommerce', 'bb-woo-mail-layout' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Textes d'introduction par e-mail.
	 */
	public function field_intros(): void {
		$name     = self::id( 'intros' );
		$intros   = (array) Options::get( 'intros' );
		$texts    = new DefaultTexts();
		$defaults = $texts->by_id();
		?>
		<tr valign="top">
			<td class="forminp" colspan="2" style="padding-left:0;">
				<p class="bb-wml-placeholders">
					<?php esc_html_e( 'Placeholders :', 'bb-woo-mail-layout' ); ?>
					<?php foreach ( Placeholders::documented() as $placeholder => $label ) : ?>
						<code title="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $placeholder ); ?></code>
					<?php endforeach; ?>
				</p>
				<table class="widefat striped bb-wml-intros">
					<tbody>
						<?php foreach ( $this->registry->all() as $id => $email ) : ?>
							<tr>
								<th scope="row">
									<label for="bb-wml-intro-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $email['title'] ); ?></label>
									<br><code><?php echo esc_html( $id ); ?></code>
								</th>
								<td>
									<textarea id="bb-wml-intro-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . '[' . $id . ']' ); ?>" rows="3" class="large-text" placeholder="<?php echo esc_attr( $defaults[ $id ] ?? $texts->generic( $email['customer'] ) ); ?>"><?php echo esc_textarea( (string) ( $intros[ $id ] ?? '' ) ); ?></textarea>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Prévisualisation, envoi de test, import / export.
	 * Les champs n'ont pas d'attribut name : ils ne sont pas soumis avec le formulaire WooCommerce.
	 */
	public function field_tools(): void {
		$simulator = new Simulator( $this->registry );
		$orders    = $simulator->recent_orders( 20 );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Aperçu et e-mail de test', 'bb-woo-mail-layout' ); ?></th>
			<td class="forminp bb-wml-tools">
				<p class="description"><?php esc_html_e( 'L’aperçu et le test utilisent les réglages enregistrés : enregistrez vos modifications avant.', 'bb-woo-mail-layout' ); ?></p>
				<p>
					<label for="bb-wml-tool-email"><?php esc_html_e( 'E-mail', 'bb-woo-mail-layout' ); ?></label><br>
					<select id="bb-wml-tool-email">
						<?php foreach ( $this->registry->all() as $id => $email ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( 'customer_processing_order', $id ); ?>>
								<?php echo esc_html( $email['title'] . ( $this->registry->is_enabled( $id ) ? '' : ' — ' . __( 'rendu natif', 'bb-woo-mail-layout' ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="bb-wml-tool-order"><?php esc_html_e( 'Commande utilisée comme jeu de données', 'bb-woo-mail-layout' ); ?></label><br>
					<select id="bb-wml-tool-order">
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
				</p>
				<p>
					<label for="bb-wml-tool-to"><?php esc_html_e( 'Destinataire du test', 'bb-woo-mail-layout' ); ?></label><br>
					<input type="email" id="bb-wml-tool-to" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
				</p>
				<p>
					<button type="button" class="button" id="bb-wml-preview"><?php esc_html_e( 'Prévisualiser', 'bb-woo-mail-layout' ); ?></button>
					<button type="button" class="button button-primary" id="bb-wml-send-test"><?php esc_html_e( 'Envoyer un e-mail de test', 'bb-woo-mail-layout' ); ?></button>
					<span class="bb-wml-status" id="bb-wml-tools-status" role="status" aria-live="polite"></span>
				</p>
				<iframe id="bb-wml-preview-frame" class="bb-wml-preview-frame" sandbox="" title="<?php esc_attr_e( 'Aperçu de l’e-mail', 'bb-woo-mail-layout' ); ?>" hidden></iframe>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Import / export', 'bb-woo-mail-layout' ); ?></th>
			<td class="forminp bb-wml-transfer">
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . ImportExport::EXPORT_ACTION ), ImportExport::EXPORT_ACTION ) ); ?>"><?php esc_html_e( 'Exporter les réglages (JSON)', 'bb-woo-mail-layout' ); ?></a>
				</p>
				<p>
					<input type="file" id="bb-wml-import-file" accept="application/json,.json">
					<button type="button" class="button" id="bb-wml-import"><?php esc_html_e( 'Importer', 'bb-woo-mail-layout' ); ?></button>
					<span class="bb-wml-status" id="bb-wml-import-status" role="status" aria-live="polite"></span>
				</p>
				<p class="description"><?php esc_html_e( 'L’import remplace tous les réglages de cette page. Les clés inconnues sont refusées.', 'bb-woo-mail-layout' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * Assets, liens
	 * ------------------------------------------------------------------
	 */

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
		wp_localize_script(
			'bb-wml-settings',
			'bbWml',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'chooseLogo'    => __( 'Choisir le logo', 'bb-woo-mail-layout' ),
					'useImage'      => __( 'Utiliser cette image', 'bb-woo-mail-layout' ),
					'loading'       => __( 'Génération de l’aperçu…', 'bb-woo-mail-layout' ),
					'sending'       => __( 'Envoi en cours…', 'bb-woo-mail-layout' ),
					'importing'     => __( 'Import en cours…', 'bb-woo-mail-layout' ),
					'error'         => __( 'Une erreur est survenue.', 'bb-woo-mail-layout' ),
					'chooseFile'    => __( 'Choisissez un fichier JSON.', 'bb-woo-mail-layout' ),
					'confirmImport' => __( 'Remplacer tous les réglages de mise en forme par ceux du fichier ?', 'bb-woo-mail-layout' ),
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
