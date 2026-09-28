<?php
/**
 * Nouveau compte (client) — surcharge BB Woo Mail Layout.
 *
 * @package BB\WooMailLayout
 * @var string   $email_heading
 * @var string   $additional_content
 * @var string   $user_login
 * @var bool     $password_generated
 * @var string   $set_password_url   WooCommerce ≥ 6.0.
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$bb_renderer = \BB\WooMailLayout\Plugin::instance()->renderer();

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php esc_html_e( 'Votre identifiant :', 'bb-woo-mail-layout' ); ?> <strong><?php echo esc_html( $user_login ); ?></strong></p>
<?php
if ( ! empty( $password_generated ) && ! empty( $set_password_url ) ) {
	echo $bb_renderer->button( $set_password_url, __( 'Définir mon mot de passe', 'bb-woo-mail-layout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans button().
}

echo $bb_renderer->button( wc_get_page_permalink( 'myaccount' ), __( 'Accéder à mon compte', 'bb-woo-mail-layout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans button().

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
