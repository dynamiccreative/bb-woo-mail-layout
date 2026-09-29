<?php
/**
 * Tableau de commande — surcharge BB Woo Mail Layout (libellés français du plugin).
 *
 * Les lignes (email-order-items.php) et les totaux restent produits par WooCommerce.
 *
 * @package BB\WooMailLayout
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var mixed    $email   E-mail (argument de wc_get_template, non garanti).
 */

defined( 'ABSPATH' ) || exit;

$bb_align = is_rtl() ? 'right' : 'left';
// Photos produit : selon le layout (en-tête « Product Images: yes » de son styles.css).
$bb_images = \BB\WooMailLayout\Plugin::instance()->renderer()->shows_product_images( isset( $email ) && $email instanceof WC_Email ? $email : null );

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
?>
<h2 class="bb-order-heading">
	<?php
	$bb_order_label = sprintf(
		/* translators: %s: numéro de commande. */
		__( 'Commande n° %s', 'bb-woo-mail-layout' ),
		$order->get_order_number()
	);
	if ( $sent_to_admin ) {
		printf( '<a class="link" href="%s">%s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $bb_order_label ) );
	} else {
		echo esc_html( $bb_order_label );
	}
	if ( $order->get_date_created() ) {
		printf(
			' <span class="bb-order-date">(<time datetime="%s">%s</time>)</span>',
			esc_attr( $order->get_date_created()->format( 'c' ) ),
			esc_html( wc_format_datetime( $order->get_date_created() ) )
		);
	}
	?>
</h2>

<div class="bb-order-table-wrap">
	<table class="td bb-order-table" cellspacing="0" cellpadding="6" border="1" width="100%">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php esc_html_e( 'Produit', 'bb-woo-mail-layout' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php esc_html_e( 'Quantité', 'bb-woo-mail-layout' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php esc_html_e( 'Prix', 'bb-woo-mail-layout' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => $bb_images,
					'image_size'    => array( 64, 64 ),
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				)
			);
			?>
		</tbody>
		<tfoot>
			<?php
			$bb_totals = $order->get_order_item_totals();
			if ( $bb_totals ) {
				$bb_i = 0;
				foreach ( $bb_totals as $bb_total ) {
					++$bb_i;
					$bb_class = 1 === $bb_i ? 'td bb-total-first' : 'td';
					?>
					<tr>
						<th class="<?php echo esc_attr( $bb_class ); ?>" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php echo wp_kses_post( $bb_total['label'] ); ?></th>
						<td class="<?php echo esc_attr( $bb_class ); ?>" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php echo wp_kses_post( $bb_total['value'] ); ?></td>
					</tr>
					<?php
				}
			}
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php esc_html_e( 'Note du client :', 'bb-woo-mail-layout' ); ?></th>
					<td class="td" style="text-align:<?php echo esc_attr( $bb_align ); ?>;"><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), array( 'br' => array() ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>

<?php
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
