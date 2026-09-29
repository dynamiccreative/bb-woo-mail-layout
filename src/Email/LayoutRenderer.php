<?php
/**
 * Rendu du layout : redirection des templates WooCommerce, CSS, variables de vue.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Email;

use BB\WooMailLayout\Compat\Wpml;
use BB\WooMailLayout\Settings\LogoChecker;
use BB\WooMailLayout\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Remplace email-header / email-footer / styles par le layout actif, pour les seuls e-mails activés.
 */
final class LayoutRenderer {

	private const HEADER = 'emails/email-header.php';
	private const FOOTER = 'emails/email-footer.php';

	/** Longueur maximale du pré-en-tête (les clients mail en affichent 90 à 140 caractères). */
	private const PREHEADER_MAX = 140;

	/**
	 * E-mails en cours de rendu (templates imbriqués).
	 *
	 * @var array<int, \WC_Email>
	 */
	private array $stack = array();

	/**
	 * E-mail signalé par l'action woocommerce_email_header (e-mails tiers sans template Woo).
	 *
	 * @var \WC_Email|null
	 */
	private ?\WC_Email $header_email = null;

	/**
	 * Cache des layouts disponibles.
	 *
	 * @var array<string, array{label:string, path:string, images?:bool}>|null
	 */
	private static ?array $layouts = null;

	/**
	 * Constructeur.
	 *
	 * @param EmailRegistry $registry     Registre des e-mails.
	 * @param Placeholders  $placeholders Placeholders.
	 * @param DefaultTexts  $texts        Textes par défaut.
	 * @param Wpml          $wpml         Compatibilité WPML / Polylang.
	 */
	public function __construct(
		private EmailRegistry $registry,
		private Placeholders $placeholders,
		private DefaultTexts $texts,
		private Wpml $wpml
	) {}

	/**
	 * Hooks WooCommerce. Uniquement des filtres liés au rendu des e-mails : aucun impact front.
	 */
	public function register(): void {
		add_filter( 'wc_get_template', array( $this, 'filter_template' ), 20, 3 );
		add_action( 'woocommerce_before_template_part', array( $this, 'before_template_part' ), 1, 4 );
		add_action( 'woocommerce_after_template_part', array( $this, 'after_template_part' ), 999, 4 );
		add_action( 'woocommerce_email_header', array( $this, 'track_header_email' ), 1, 2 );
		add_action( 'woocommerce_email_footer', array( $this, 'untrack_header_email' ), PHP_INT_MAX );
		// Priorité basse : on remplace le CSS natif, les CSS ajoutés ensuite par des extensions sont conservés.
		add_filter( 'woocommerce_email_styles', array( $this, 'filter_styles' ), 5, 2 );
	}

	/**
	 * Layouts disponibles : dossiers de `layouts/` contenant header.php, footer.php et styles.css.
	 *
	 * @return array<string, array{label:string, path:string, images?:bool}>
	 */
	public static function available_layouts(): array {
		if ( null !== self::$layouts ) {
			return self::$layouts;
		}

		$layouts = array();
		foreach ( (array) glob( BB_WML_DIR . 'layouts/*', GLOB_ONLYDIR ) as $dir ) {
			$layout = self::read_layout( $dir );
			if ( $layout ) {
				$layouts[ basename( $dir ) ] = $layout;
			}
		}

		/**
		 * Layouts additionnels (ex. depuis un thème) : slug => [ 'label' => …, 'path' => dossier ].
		 *
		 * @param array<string, mixed> $layouts Layouts détectés (slug => [ 'label' => …, 'path' => … ]).
		 *
		 * @since 1.0.0
		 */
		$layouts = (array) apply_filters( 'bb_email_layouts', $layouts );

		self::$layouts = array_filter(
			$layouts,
			static fn( $layout ) => is_array( $layout ) && isset( $layout['path'] ) && is_readable( trailingslashit( $layout['path'] ) . 'header.php' )
		);
		return self::$layouts;
	}

	/**
	 * Lit un dossier de layout.
	 *
	 * @param string $dir Dossier.
	 * @return array{label:string, path:string, images:bool}|null
	 */
	private static function read_layout( string $dir ): ?array {
		$dir = trailingslashit( $dir );
		foreach ( array( 'header.php', 'footer.php', 'styles.css' ) as $file ) {
			if ( ! is_readable( $dir . $file ) ) {
				return null;
			}
		}
		$data = get_file_data(
			$dir . 'styles.css',
			array(
				'name'   => 'Layout Name',
				'images' => 'Product Images',
			)
		);
		return array(
			'label'  => '' !== $data['name'] ? $data['name'] : ucfirst( basename( $dir ) ),
			'path'   => $dir,
			'images' => 'yes' === strtolower( trim( $data['images'] ) ),
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Suivi de l'e-mail en cours de rendu
	 * ------------------------------------------------------------------
	 */

	/**
	 * Empile l'e-mail quand WooCommerce charge un template qui le reçoit en argument.
	 *
	 * @param string              $template_name Nom du template.
	 * @param string              $template_path Chemin.
	 * @param string              $located       Fichier.
	 * @param array<string,mixed> $args          Arguments.
	 */
	public function before_template_part( $template_name, $template_path, $located, $args ): void {
		$email = self::email_from_args( $template_name, $args );
		if ( ! $email ) {
			return;
		}
		$this->stack[] = $email;
		if ( 1 === count( $this->stack ) && $this->applies_to( $email ) && $this->uses_recipient_language( $email ) ) {
			// Réservation, adhésion : langue de la commande ou du client rattachés.
			$context = Placeholders::context( $email->object );
			$this->wpml->switch_for( $context['order'] ?? $context['user'] ?? $email->object );
		}
	}

	/**
	 * L'e-mail doit-il être rendu dans la langue de la commande ?
	 * Oui pour les e-mails client ; les e-mails admin restent dans la langue du site, sauf filtre.
	 *
	 * @param \WC_Email $email E-mail.
	 */
	private function uses_recipient_language( \WC_Email $email ): bool {
		if ( $email->is_customer_email() ) {
			return true;
		}

		/**
		 * Rendre aussi les e-mails admin dans la langue de la commande (désactivé par défaut).
		 *
		 * @param bool      $enabled Faux par défaut.
		 * @param \WC_Email $email   E-mail admin.
		 *
		 * @since 1.0.4
		 */
		return (bool) apply_filters( 'bb_email_admin_uses_order_language', false, $email );
	}

	/**
	 * Dépile.
	 *
	 * @param string              $template_name Nom du template.
	 * @param string              $template_path Chemin.
	 * @param string              $located       Fichier.
	 * @param array<string,mixed> $args          Arguments.
	 */
	public function after_template_part( $template_name, $template_path, $located, $args ): void {
		if ( ! self::email_from_args( $template_name, $args ) || ! $this->stack ) {
			return;
		}
		array_pop( $this->stack );
		if ( ! $this->stack ) {
			$this->wpml->restore();
		}
	}

	/**
	 * Mémorise l'e-mail passé à l'action woocommerce_email_header.
	 *
	 * @param string         $heading Titre.
	 * @param \WC_Email|null $email   E-mail.
	 */
	public function track_header_email( $heading = '', $email = null ): void {
		if ( $email instanceof \WC_Email ) {
			$this->header_email = $email;
		}
	}

	/**
	 * Oublie l'e-mail à la fin du pied de page.
	 */
	public function untrack_header_email(): void {
		$this->header_email = null;
	}

	/**
	 * E-mail en cours de rendu.
	 */
	public function current_email(): ?\WC_Email {
		$top = end( $this->stack );
		return $top instanceof \WC_Email ? $top : $this->header_email;
	}

	/**
	 * Le layout s'applique-t-il à cet e-mail ?
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function applies_to( ?\WC_Email $email ): bool {
		return $email instanceof \WC_Email
			&& 'plain' !== $email->get_email_type()
			&& $this->registry->is_enabled( (string) $email->id );
	}

	/**
	 * E-mail HTML transmis à un template.
	 *
	 * @param mixed $template_name Nom du template.
	 * @param mixed $args          Arguments.
	 */
	private static function email_from_args( $template_name, $args ): ?\WC_Email {
		if ( ! is_string( $template_name ) || ! str_starts_with( $template_name, 'emails/' ) || str_starts_with( $template_name, 'emails/plain/' ) ) {
			return null;
		}
		return is_array( $args ) && isset( $args['email'] ) && $args['email'] instanceof \WC_Email ? $args['email'] : null;
	}

	/*
	 * ------------------------------------------------------------------
	 * Redirection des templates et CSS
	 * ------------------------------------------------------------------
	 */

	/**
	 * Redirige header / footer (et les quelques contenus surchargés) vers le plugin.
	 *
	 * @param mixed $template      Fichier localisé par WooCommerce.
	 * @param mixed $template_name Nom du template.
	 * @param mixed $args          Arguments.
	 * @return mixed
	 */
	public function filter_template( $template, $template_name, $args ) {
		if ( ! is_string( $template_name ) || ! str_starts_with( $template_name, 'emails/' ) || str_starts_with( $template_name, 'emails/plain/' ) ) {
			return $template;
		}

		$email = self::email_from_args( $template_name, $args ) ?? $this->current_email();
		if ( ! $this->applies_to( $email ) ) {
			return $template;
		}

		if ( self::HEADER !== $template_name && self::FOOTER !== $template_name && ! in_array( $template_name, $this->overridden_templates(), true ) ) {
			return $template;
		}

		$file = BB_WML_DIR . 'templates/' . $template_name;
		return is_readable( $file ) ? $file : $template;
	}

	/**
	 * Templates de contenu surchargés (greeting anglais remplacé par l'intro, libellés FR).
	 *
	 * @return string[]
	 */
	private function overridden_templates(): array {
		static $templates = null;

		if ( null === $templates ) {
			$templates = array();
			foreach ( (array) glob( BB_WML_DIR . 'templates/emails/*.php' ) as $file ) {
				$name = 'emails/' . basename( $file );
				if ( self::HEADER !== $name && self::FOOTER !== $name ) {
					$templates[] = $name;
				}
			}
		}

		/**
		 * Liste des templates de contenu WooCommerce surchargés par le plugin.
		 *
		 * @param string[] $templates Ex. 'emails/customer-processing-order.php'.
		 *
		 * @since 1.0.0
		 */
		return (array) apply_filters( 'bb_email_override_templates', $templates );
	}

	/**
	 * Retire le CSS natif de WooCommerce (email-styles.php) pour les e-mails mis en forme.
	 *
	 * Le CSS du layout n'est pas passé ici mais écrit dans le <style> du <head> (voir header.php) :
	 * une extension qui remplace ensuite ce CSS ne peut pas l'écraser, et si l'inlining de WooCommerce
	 * échoue, le bloc <style> reste dans l'e-mail. L'inliner lit les <style> du document après ce CSS.
	 *
	 * @param mixed $css   CSS WooCommerce.
	 * @param mixed $email E-mail.
	 * @return mixed
	 */
	public function filter_styles( $css, $email = null ) {
		$email = $email instanceof \WC_Email ? $email : $this->current_email();
		return $this->applies_to( $email ) ? '' : $css;
	}

	/**
	 * CSS complet (base + layout), jetons remplacés.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function css( ?\WC_Email $email ): string {
		$settings = Options::all();
		$layout   = $this->layout_path( $email );

		$css  = (string) file_get_contents( BB_WML_DIR . 'layouts/base.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css .= "\n" . (string) file_get_contents( $layout . 'styles.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// CSS personnalisé en dernier : il l'emporte ; les jetons {{primary}}… y sont aussi remplacés.
		$custom = Options::sanitize_css( (string) $settings['custom_css'] );
		if ( '' !== $custom ) {
			$css .= "\n/* CSS personnalisé */\n" . $custom;
		}

		$tokens = array( '{{font}}' => Options::font_stack( $settings ) );
		foreach ( $this->colors( $settings ) as $name => $value ) {
			$tokens[ '{{' . $name . '}}' ] = $value;
		}

		/**
		 * CSS final du layout (avant inlining par WooCommerce).
		 *
		 * @param string         $css   CSS.
		 * @param \WC_Email|null $email E-mail.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( 'bb_email_css', strtr( $css, $tokens ), $email );
	}

	/*
	 * ------------------------------------------------------------------
	 * Rendu du header / footer
	 * ------------------------------------------------------------------
	 */

	/**
	 * Rendu de l'en-tête (appelé par templates/emails/email-header.php).
	 *
	 * @param string         $heading Titre de l'e-mail (réglage WooCommerce).
	 * @param \WC_Email|null $email   E-mail.
	 */
	public function render_header( string $heading, ?\WC_Email $email ): void {
		$email = $email ?? $this->current_email();
		$vars  = $this->view_vars( $email, $heading );
		// CSS du layout pour le <style> du <head> (calculé ici seulement : inutile au pied de page).
		$vars['css'] = str_replace( '</', '<\/', $this->css( $email ) );
		// Pré-en-tête et bouton d'action : propres à l'en-tête, comme le CSS.
		$vars['preheader_html'] = $this->preheader_html( $email );
		$vars['button_html']    = $this->action_button_html( $email );
		$this->include_part( 'header', $vars );

		/**
		 * Juste avant le contenu WooCommerce (après titre et intro).
		 *
		 * @param \WC_Email|null $email E-mail.
		 *
		 * @since 1.0.0
		 */
		do_action( 'bb_email_before_content', $email );
	}

	/**
	 * Rendu du pied (appelé par templates/emails/email-footer.php).
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function render_footer( ?\WC_Email $email ): void {
		$email = $email ?? $this->current_email();

		/**
		 * Juste après le contenu WooCommerce.
		 *
		 * @param \WC_Email|null $email E-mail.
		 *
		 * @since 1.0.0
		 */
		do_action( 'bb_email_after_content', $email );

		$this->include_part( 'footer', $this->view_vars( $email, '' ) );
	}

	/**
	 * Bouton « bulletproof » (table + bgcolor pour Outlook).
	 *
	 * @param string $url   Lien.
	 * @param string $label Libellé.
	 */
	public function button( string $url, string $label ): string {
		$colors = $this->colors( Options::all() );
		return sprintf(
			'<table role="presentation" class="bb-button-table" cellpadding="0" cellspacing="0" border="0"><tr><td class="bb-button-cell" align="center" bgcolor="%1$s"><a class="bb-button" href="%2$s" target="_blank">%3$s</a></td></tr></table>',
			esc_attr( $colors['button'] ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Inclut une partie du layout avec ses variables.
	 *
	 * @param string              $part header|footer.
	 * @param array<string,mixed> $v    Variables de vue.
	 */
	private function include_part( string $part, array $v ): void {
		$file = $this->layout_path( $v['email'] ) . $part . '.php';
		( static function ( string $bb_file, array $v ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $v est lu par le fichier inclus.
			include $bb_file;
		} )( $file, $v );
	}

	/**
	 * Dossier du layout pour un e-mail.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	private function layout_path( ?\WC_Email $email ): string {
		return trailingslashit( (string) $this->layout_for( $email )['path'] );
	}

	/**
	 * Le layout de cet e-mail affiche-t-il les photos produit dans le tableau de commande ?
	 * (en-tête « Product Images: yes » du styles.css du layout).
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function shows_product_images( ?\WC_Email $email ): bool {
		return ! empty( $this->layout_for( $email )['images'] );
	}

	/**
	 * Layout (label, dossier, options) d'un e-mail.
	 *
	 * @param \WC_Email|null $email E-mail.
	 * @return array<string, mixed>
	 */
	private function layout_for( ?\WC_Email $email ): array {
		$layouts = self::available_layouts();
		$slug    = (string) Options::get( 'layout' );

		// Layout choisi pour cet e-mail dans l'onglet E-mails ('' = layout général).
		$per_email = (array) Options::get( 'email_layouts' );
		if ( $email && isset( $layouts[ (string) ( $per_email[ $email->id ] ?? '' ) ] ) ) {
			$slug = (string) $per_email[ $email->id ];
		}

		/**
		 * Force un layout (slug) pour un e-mail.
		 *
		 * @param string         $layout Slug du layout réglé (layout de l'e-mail s'il en a un, sinon layout général).
		 * @param \WC_Email|null $email  E-mail.
		 *
		 * @since 1.0.0
		 */
		$slug = (string) apply_filters( 'bb_email_layout', $slug, $email );

		$layout = $layouts[ $slug ] ?? $layouts['classique'] ?? reset( $layouts );
		return is_array( $layout ) ? $layout : array();
	}

	/**
	 * Variables exposées aux fichiers header.php / footer.php d'un layout.
	 *
	 * @param \WC_Email|null $email   E-mail.
	 * @param string         $heading Titre.
	 * @return array<string, mixed>
	 */
	public function view_vars( ?\WC_Email $email, string $heading ): array {
		$settings = Options::all();
		foreach ( array( 'contact_hours', 'footer_legal_name', 'footer_address', 'footer_text' ) as $key ) {
			$settings[ $key ] = $this->wpml->translate( $key, (string) $settings[ $key ] );
		}

		$site_title = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );

		return array(
			'email'           => $email,
			'heading'         => $heading,
			'settings'        => $settings,
			'lang'            => str_replace( '_', '-', determine_locale() ),
			'charset'         => (string) get_bloginfo( 'charset' ),
			'site_title'      => $site_title,
			'site_url'        => home_url( '/' ),
			'colors'          => $this->colors( $settings ),
			'google_font_url' => $this->google_font_url( $settings ),
			'logo'            => $this->logo( $settings, $site_title ),
			'intro_html'      => 'yes' === $settings['show_intro'] ? $this->intro_html( $email ) : '',
			'help'            => $this->help( $settings ),
			'socials'         => $this->socials( $settings ),
			'footer'          => $this->footer( $settings, $email ),
			'featured_html'   => $this->featured_html( $settings, $email ),
		);
	}

	/**
	 * Intro : texte saisi, sinon texte par défaut ; placeholders remplacés.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function intro_html( ?\WC_Email $email ): string {
		$text = $email ? $this->intro_text( $email ) : '';
		if ( '' === $text ) {
			return '';
		}

		$html = '';
		foreach ( (array) preg_split( '/\R\s*\R/', $text ) as $paragraph ) {
			$paragraph = trim( (string) $paragraph );
			if ( '' !== $paragraph ) {
				$html .= '<p>' . nl2br( $this->placeholders->to_html( $paragraph, $email ), false ) . '</p>';
			}
		}
		return $html;
	}

	/**
	 * Texte d'intro brut (placeholders non remplacés) : texte saisi, sinon texte par défaut.
	 *
	 * @param \WC_Email $email E-mail.
	 */
	private function intro_text( \WC_Email $email ): string {
		$intros = Options::get( 'intros' );
		$custom = trim( (string) ( is_array( $intros ) ? ( $intros[ $email->id ] ?? '' ) : '' ) );
		$custom = '' !== $custom ? $this->wpml->translate( 'intro_' . $email->id, $custom ) : '';
		$text   = '' !== $custom ? $custom : $this->texts->for_email( $email );

		/**
		 * Texte d'introduction (texte brut, placeholders non remplacés).
		 *
		 * @param string    $text  Texte.
		 * @param \WC_Email $email E-mail.
		 *
		 * @since 1.0.0
		 */
		return trim( (string) apply_filters( 'bb_email_intro_text', $text, $email ) );
	}

	/**
	 * Pré-en-tête : texte affiché sous le sujet dans la boîte de réception, masqué dans l'e-mail.
	 * Texte saisi pour l'e-mail, sinon début de l'intro ; placeholders remplacés.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function preheader( ?\WC_Email $email ): string {
		if ( ! $email ) {
			return '';
		}

		$preheaders = (array) Options::get( 'preheaders' );
		$text       = trim( (string) ( $preheaders[ $email->id ] ?? '' ) );
		$text       = '' !== $text ? $this->wpml->translate( 'preheader_' . $email->id, $text ) : $this->intro_text( $email );
		$text       = self::plain( $this->placeholders->to_html( $text, $email ) );
		if ( mb_strlen( $text ) > self::PREHEADER_MAX ) {
			$text = rtrim( mb_substr( $text, 0, self::PREHEADER_MAX - 1 ) ) . '…';
		}

		/**
		 * Texte du pré-en-tête (texte brut ; vide = pas de pré-en-tête).
		 *
		 * @param string    $text  Texte.
		 * @param \WC_Email $email E-mail.
		 *
		 * @since 1.4.0
		 */
		return trim( (string) apply_filters( 'bb_email_preheader', $text, $email ) );
	}

	/**
	 * Pré-en-tête masqué, suivi d'espaces invisibles : les clients mail n'affichent pas
	 * le début du corps (« Voir ma commande », adresse…) à la suite du texte.
	 *
	 * Classe « -emogrifier-keep » obligatoire : après l'inlining, WooCommerce supprime les éléments
	 * en display:none (HtmlPruner::removeElementsWithDisplayNone()), sauf ceux qui la portent.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function preheader_html( ?\WC_Email $email ): string {
		$text = $this->preheader( $email );
		if ( '' === $text ) {
			return '';
		}
		return sprintf(
			'<div class="bb-preheader -emogrifier-keep" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">%1$s%2$s</div>',
			esc_html( $text ),
			str_repeat( '&#847;&zwnj;&nbsp;', 40 )
		);
	}

	/**
	 * Bouton d'action réglé pour l'e-mail (lien : placeholder ou URL). Rien si le lien est vide
	 * pour cette commande (ex. {tracking_url} sans numéro de suivi).
	 *
	 * @param \WC_Email|null $email E-mail.
	 * @return array{url:string,label:string}|null
	 */
	public function action_button( ?\WC_Email $email ): ?array {
		if ( ! $email ) {
			return null;
		}

		$urls   = (array) Options::get( 'button_urls' );
		$labels = (array) Options::get( 'button_labels' );
		$link   = (string) ( $urls[ $email->id ] ?? '' );
		$label  = trim( (string) ( $labels[ $email->id ] ?? '' ) );
		$label  = '' !== $label ? self::plain( $this->placeholders->to_html( $this->wpml->translate( 'button_label_' . $email->id, $label ), $email ) ) : '';
		$url    = '';

		if ( str_starts_with( $link, '{' ) ) {
			$value = $this->placeholders->values( $email )[ $link ] ?? '';
			if ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ) {
				$url   = $value['url'];
				$label = '' !== $label ? $label : (string) ( $value['label'] ?? '' );
			}
		} else {
			$url = $link;
		}
		$button = '' !== $url ? array(
			'url'   => $url,
			'label' => '' !== $label ? $label : __( 'En savoir plus', 'bb-woo-mail-layout' ),
		) : null;

		/**
		 * Bouton d'action affiché après l'intro (null = pas de bouton).
		 *
		 * @param array{url:string,label:string}|null $button Bouton réglé.
		 * @param \WC_Email                           $email  E-mail.
		 *
		 * @since 1.4.0
		 */
		return self::normalize_button( apply_filters( 'bb_email_action_button', $button, $email ) );
	}

	/**
	 * Bouton renvoyé par un filtre tiers (forme non garantie).
	 *
	 * @param mixed $button Valeur filtrée.
	 * @return array{url:string,label:string}|null
	 */
	private static function normalize_button( mixed $button ): ?array {
		if ( ! is_array( $button ) || empty( $button['url'] ) || ! is_string( $button['url'] ) ) {
			return null;
		}
		return array(
			'url'   => $button['url'],
			'label' => isset( $button['label'] ) && is_string( $button['label'] ) ? $button['label'] : '',
		);
	}

	/**
	 * HTML du bouton d'action.
	 *
	 * @param \WC_Email|null $email E-mail.
	 */
	public function action_button_html( ?\WC_Email $email ): string {
		$button = $this->action_button( $email );
		return $button ? '<div class="bb-action">' . $this->button( $button['url'], $button['label'] ) . '</div>' : '';
	}

	/**
	 * HTML → texte brut sur une ligne.
	 *
	 * @param string $html HTML.
	 */
	private static function plain( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Logo et dimensions : largeur et hauteur maximales réglées, sans jamais agrandir l'image.
	 *
	 * @param array<string,mixed> $settings   Réglages.
	 * @param string              $site_title Nom du site (alt).
	 * @return array{url:string,width:int|null,height:int|null,max_width:int,max_height:int,alt:string}|null
	 */
	private function logo( array $settings, string $site_title ): ?array {
		if ( 'yes' !== $settings['show_logo'] || '' === $settings['logo_url'] ) {
			return null;
		}

		$max_width  = (int) $settings['logo_max_width'];
		$max_height = (int) $settings['logo_max_height'];
		$width      = null;
		$height     = null;

		[ $natural_w, $natural_h ] = $this->logo_natural_size( $settings );
		if ( $natural_w > 0 && $natural_h > 0 ) {
			// La plus contraignante des deux limites l'emporte (proportions conservées), jamais d'agrandissement.
			$width  = max( 1, (int) min( $max_width, $natural_w, floor( $natural_w * $max_height / $natural_h ) ) );
			$height = max( 1, (int) round( $natural_h * $width / $natural_w ) );
		}

		return array(
			'url'        => (string) $settings['logo_url'],
			'width'      => $width,
			'height'     => $height,
			'max_width'  => $max_width,
			'max_height' => $max_height,
			'alt'        => $site_title,
		);
	}

	/**
	 * Dimensions natives du logo : médiathèque, sinon fichier présent sur le serveur.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @return array{0:int,1:int}
	 */
	private function logo_natural_size( array $settings ): array {
		static $cache = array();

		$url = (string) $settings['logo_url'];
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$size  = array( 0, 0 );
		$image = $settings['logo_id'] ? wp_get_attachment_image_src( (int) $settings['logo_id'], 'full' ) : false;
		if ( $image ) {
			$size = array( (int) $image[1], (int) $image[2] );
		} else {
			$file = LogoChecker::local_path( $url );
			$info = $file ? wp_getimagesize( $file ) : false;
			if ( $info ) {
				$size = array( (int) $info[0], (int) $info[1] );
			}
		}

		$cache[ $url ] = $size;
		return $size;
	}

	/**
	 * Bloc « Besoin d'aide ? ».
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @return array{phone:string,phone_href:string,email:string,hours:string}|null
	 */
	private function help( array $settings ): ?array {
		if ( 'yes' !== $settings['show_help'] ) {
			return null;
		}
		$help = array(
			'phone'      => (string) $settings['contact_phone'],
			'phone_href' => 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $settings['contact_phone'] ),
			'email'      => (string) $settings['contact_email'],
			'hours'      => (string) $settings['contact_hours'],
		);
		return ( '' === $help['phone'] && '' === $help['email'] && '' === $help['hours'] ) ? null : $help;
	}

	/**
	 * Réseaux sociaux renseignés, icônes embarquées dans le plugin.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @return array<int, array{network:string,label:string,url:string,icon:string}>
	 */
	private function socials( array $settings ): array {
		if ( 'yes' !== $settings['show_social'] ) {
			return array();
		}
		$labels = array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'tiktok'    => 'TikTok',
			'youtube'   => 'YouTube',
		);

		$out = array();
		foreach ( Options::SOCIAL_NETWORKS as $network ) {
			$url = (string) $settings[ 'social_' . $network ];
			if ( '' !== $url ) {
				$out[] = array(
					'network' => $network,
					'label'   => $labels[ $network ],
					'url'     => $url,
					'icon'    => BB_WML_URL . 'assets/icons/' . $network . '.png',
				);
			}
		}
		return $out;
	}

	/**
	 * Pied de page.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @param \WC_Email|null      $email    E-mail.
	 * @return array{legal_name:string,address_html:string,site_url:string,site_label:string,text_html:string,unsubscribe_url:string}|null
	 */
	private function footer( array $settings, ?\WC_Email $email ): ?array {
		if ( 'yes' !== $settings['show_footer'] ) {
			return null;
		}

		$text = $this->placeholders->replace( wp_kses( (string) $settings['footer_text'], Options::allowed_footer_html() ), $email );

		return array(
			'legal_name'      => (string) $settings['footer_legal_name'],
			'address_html'    => nl2br( esc_html( (string) $settings['footer_address'] ), false ),
			'site_url'        => 'yes' === $settings['footer_show_site_link'] ? home_url( '/' ) : '',
			'site_label'      => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			/**
			 * HTML du texte libre de pied de page.
			 *
			 * @param string         $text  HTML assaini.
			 * @param \WC_Email|null $email E-mail.
			 *
			 * @since 1.0.0
			 */
			'text_html'       => (string) apply_filters( 'bb_email_footer_text', $text, $email ),
			/**
			 * Lien de désinscription (e-mails marketing uniquement ; vide = non affiché).
			 *
			 * @param string         $url   URL.
			 * @param \WC_Email|null $email E-mail.
			 *
			 * @since 1.0.0
			 */
			'unsubscribe_url' => esc_url_raw( (string) apply_filters( 'bb_email_unsubscribe_url', '', $email ) ),
		);
	}

	/**
	 * Bloc « produits mis en avant » (jusqu'à 3 produits : photo, titre, prix), e-mails client uniquement.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @param \WC_Email|null      $email    E-mail.
	 */
	public function featured_html( array $settings, ?\WC_Email $email ): string {
		if ( 'yes' !== $settings['show_featured'] || ! $email ) {
			return '';
		}

		$ids = $email->is_customer_email() ? $this->featured_ids( $settings, $email ) : array();

		/**
		 * Produits mis en avant pour un e-mail (tableau vide = pas de bloc).
		 *
		 * @param int[]     $ids   Identifiants de produits, par ordre de priorité (les 3 premiers visibles sont affichés).
		 * @param \WC_Email $email E-mail.
		 *
		 * @since 1.1.0
		 */
		$ids = (array) apply_filters( 'bb_email_featured_products', $ids, $email );

		$products = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			if ( count( $products ) >= Options::MAX_FEATURED ) {
				break;
			}
			$product = $id ? wc_get_product( $id ) : null;
			if ( ! $product instanceof \WC_Product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
				continue;
			}
			$image      = $product->get_image_id() ? wp_get_attachment_image_src( (int) $product->get_image_id(), 'woocommerce_thumbnail' ) : false;
			$products[] = array(
				'name'       => $product->get_name(),
				'url'        => (string) $product->get_permalink(),
				'image'      => $image ? (string) $image[0] : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				// Texte pour lecteurs d'écran (« Original price was… ») : masqué sur le site par CSS, visible dans un e-mail.
				'price_html' => (string) preg_replace( '#<span[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?</span>#s', '', (string) $product->get_price_html() ),
			);
		}
		if ( ! $products ) {
			return '';
		}

		$title = trim( (string) $settings['featured_title'] );
		$title = '' !== $title ? $this->wpml->translate( 'featured_title', $title ) : __( 'Vous aimerez aussi', 'bb-woo-mail-layout' );

		ob_start();
		( static function ( string $bb_file, string $title, array $products ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- variables lues par le gabarit inclus.
			include $bb_file;
		} )( BB_WML_DIR . 'layouts/partials/featured-products.php', $title, $products );
		return (string) ob_get_clean();
	}

	/**
	 * Candidats au bloc « produits mis en avant », par ordre de priorité.
	 *
	 * - manual : produits choisis dans l'admin.
	 * - cross_sells : ventes croisées des produits commandés, puis produits apparentés, puis produits choisis.
	 * - related : produits apparentés (catégories / étiquettes) des produits commandés, puis produits choisis.
	 *
	 * Les produits de la commande sont exclus des suggestions automatiques. Sans commande
	 * (e-mails de compte), seuls les produits choisis sont proposés.
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @param \WC_Email           $email    E-mail.
	 * @return int[]
	 */
	private function featured_ids( array $settings, \WC_Email $email ): array {
		$manual = array_map( 'absint', (array) $settings['featured_products'] );
		$source = (string) ( $settings['featured_source'] ?? 'manual' );
		$order  = $email->object instanceof \WC_Order ? $email->object : null;
		if ( 'manual' === $source || ! $order ) {
			return $manual;
		}

		$ordered = array();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product && $item->get_product_id() ) {
				$ordered[] = $item->get_product_id();
			}
		}
		$ordered = array_values( array_unique( $ordered ) );

		// Marge : certains candidats seront écartés (non publiés, masqués, en rupture masquée).
		$wanted = Options::MAX_FEATURED * 2;
		$auto   = array();
		if ( 'cross_sells' === $source ) {
			foreach ( $ordered as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product instanceof \WC_Product ) {
					$auto = array_merge( $auto, array_map( 'absint', $product->get_cross_sell_ids() ) );
				}
			}
		}
		foreach ( $ordered as $product_id ) {
			if ( count( array_unique( array_diff( $auto, $ordered ) ) ) >= $wanted ) {
				break;
			}
			$auto = array_merge( $auto, array_map( 'absint', wc_get_related_products( $product_id, $wanted, $ordered ) ) );
		}

		return array_values( array_unique( array_merge( array_diff( $auto, $ordered ), $manual ) ) );
	}

	/**
	 * URL Google Fonts (option explicite, avec police de repli).
	 *
	 * @param array<string,mixed> $settings Réglages.
	 */
	private function google_font_url( array $settings ): string {
		if ( 'google' !== $settings['font'] || '' === $settings['google_font'] ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', (string) $settings['google_font'] ) . ':wght@400;700&display=swap';
	}

	/*
	 * ------------------------------------------------------------------
	 * Couleurs
	 * ------------------------------------------------------------------
	 */

	/**
	 * Palette : couleurs réglées + dérivées (contraste, bordures, fonds doux).
	 *
	 * @param array<string,mixed> $settings Réglages.
	 * @return array<string,string>
	 */
	public function colors( array $settings ): array {
		$primary = (string) $settings['color_primary'];
		$button  = (string) $settings['color_button'];
		$text    = (string) $settings['color_text'];

		return array(
			'primary'      => $primary,
			'primary_text' => self::contrast( $primary ),
			'button'       => $button,
			'button_text'  => self::contrast( $button ),
			'text'         => $text,
			'background'   => (string) $settings['color_background'],
			'muted'        => self::mix( $text, '#ffffff', 0.65 ),
			// Réglage « Couleur des bordures », sinon teinte calculée depuis la couleur du texte.
			'border'       => '' !== (string) ( $settings['color_border'] ?? '' ) ? (string) $settings['color_border'] : self::mix( $text, '#ffffff', 0.18 ),
			'soft'         => self::mix( $primary, '#ffffff', 0.06 ),
		);
	}

	/**
	 * Mélange deux couleurs hexadécimales ($weight = part de $color).
	 *
	 * @param string $color  Couleur.
	 * @param string $base   Couleur de base.
	 * @param float  $weight Part de $color (0-1).
	 */
	public static function mix( string $color, string $base, float $weight ): string {
		$a = self::rgb( $color );
		$b = self::rgb( $base );
		$c = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$c[] = (int) round( $a[ $i ] * $weight + $b[ $i ] * ( 1 - $weight ) );
		}
		return vsprintf( '#%02x%02x%02x', $c );
	}

	/**
	 * Blanc ou gris foncé selon la luminance du fond.
	 *
	 * @param string $background Couleur de fond.
	 */
	public static function contrast( string $background ): string {
		[ $r, $g, $b ] = self::rgb( $background );

		$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
		return $luminance > 0.6 ? '#1f2328' : '#ffffff';
	}

	/**
	 * Hex → RVB.
	 *
	 * @param string $hex Couleur #rgb ou #rrggbb.
	 * @return array{0:int,1:int,2:int}
	 */
	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
	}
}
