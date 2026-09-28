<?php
/**
 * Note client — surcharge BB Woo Mail Layout.
 *
 * Le message d'accueil anglais de WooCommerce est remplacé par l'intro française du layout.
 * Le contenu métier reste produit par les hooks WooCommerce standard.
 *
 * @package BB\WooMailLayout
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 * @var string   $customer_note
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<?php $bb_note = function_exists( 'wc_wptexturize_order_note' ) ? wc_wptexturize_order_note( $customer_note ) : wptexturize( $customer_note ); ?>
<blockquote class="bb-note"><?php echo wp_kses_post( wpautop( make_clickable( $bb_note ) ) ); ?></blockquote>
<p><?php esc_html_e( 'Pour rappel, voici le détail de votre commande :', 'bb-woo-mail-layout' ); ?></p>
<?php

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
