<?php
/**
 * Réinitialisation du mot de passe (client) — surcharge BB Woo Mail Layout.
 *
 * @package BB\WooMailLayout
 * @var string   $email_heading
 * @var string   $additional_content
 * @var string   $user_login
 * @var int      $user_id
 * @var string   $reset_key
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$bb_reset_url = add_query_arg(
	array(
		'key'   => $reset_key,
		'id'    => $user_id,
		'login' => rawurlencode( $user_login ),
	),
	wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
);

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php esc_html_e( 'Identifiant du compte :', 'bb-woo-mail-layout' ); ?> <strong><?php echo esc_html( $user_login ); ?></strong></p>
<?php
echo \BB\WooMailLayout\Plugin::instance()->renderer()->button( $bb_reset_url, __( 'Réinitialiser mon mot de passe', 'bb-woo-mail-layout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans button().

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
