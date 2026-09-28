<?php
/**
 * Adresses de facturation / livraison — surcharge BB Woo Mail Layout (libellés français du plugin).
 *
 * @package BB\WooMailLayout
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 */

defined( 'ABSPATH' ) || exit;

$bb_align    = is_rtl() ? 'right' : 'left';
$bb_billing  = $order->get_formatted_billing_address();
$bb_shipping = $order->get_formatted_shipping_address();
$bb_ship     = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && $bb_shipping;
?>
<table id="addresses" class="bb-addresses" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td class="bb-address-col" valign="top" width="<?php echo esc_attr( $bb_ship ? '48%' : '100%' ); ?>" style="text-align:<?php echo esc_attr( $bb_align ); ?>;">
			<h2><?php esc_html_e( 'Adresse de facturation', 'bb-woo-mail-layout' ); ?></h2>
			<address class="address">
				<?php echo wp_kses_post( $bb_billing ? $bb_billing : esc_html__( 'Non renseignée', 'bb-woo-mail-layout' ) ); ?>
				<?php if ( $order->get_billing_phone() ) : ?>
					<br><?php echo wp_kses_post( wc_make_phone_clickable( $order->get_billing_phone() ) ); ?>
				<?php endif; ?>
				<?php if ( $order->get_billing_email() ) : ?>
					<br><?php echo esc_html( $order->get_billing_email() ); ?>
				<?php endif; ?>
				<?php do_action( 'woocommerce_email_customer_address_section', 'billing', $order, $sent_to_admin, false ); ?>
			</address>
		</td>
		<?php if ( $bb_ship ) : ?>
			<td class="bb-address-gap" width="4%">&nbsp;</td>
			<td class="bb-address-col" valign="top" width="48%" style="text-align:<?php echo esc_attr( $bb_align ); ?>;">
				<h2><?php esc_html_e( 'Adresse de livraison', 'bb-woo-mail-layout' ); ?></h2>
				<address class="address">
					<?php echo wp_kses_post( $bb_shipping ); ?>
					<?php if ( $order->get_shipping_phone() ) : ?>
						<br><?php echo wp_kses_post( wc_make_phone_clickable( $order->get_shipping_phone() ) ); ?>
					<?php endif; ?>
					<?php do_action( 'woocommerce_email_customer_address_section', 'shipping', $order, $sent_to_admin, false ); ?>
				</address>
			</td>
		<?php endif; ?>
	</tr>
</table>
