<?php
/**
 * Bloc « produits mis en avant » : ligne de tableau insérée par les layouts (footer.php).
 * Colonnes côte à côte, empilées sur mobile (voir base.css).
 *
 * @package BB\WooMailLayout
 * @var string                                                               $title    Titre du bloc.
 * @var array<int, array{name:string,url:string,image:string,price_html:string}> $products Produits (1 à 3).
 */

defined( 'ABSPATH' ) || exit;

$bb_width = (int) floor( 100 / count( $products ) );
?>
<tr>
	<td class="bb-featured" bgcolor="#ffffff">
		<p class="bb-featured-title"><?php echo esc_html( $title ); ?></p>
		<table role="presentation" class="bb-featured-grid" width="100%" cellpadding="0" cellspacing="0" border="0">
			<tr>
				<?php foreach ( $products as $bb_product ) : ?>
					<td class="bb-featured-item" width="<?php echo (int) $bb_width; ?>%" valign="top" align="center">
						<a href="<?php echo esc_url( $bb_product['url'] ); ?>" target="_blank" style="text-decoration:none;">
							<img class="bb-featured-img" src="<?php echo esc_url( $bb_product['image'] ); ?>" alt="<?php echo esc_attr( $bb_product['name'] ); ?>" width="150" height="150" style="display:block;margin:0 auto;width:150px;max-width:100%;height:auto;border:0;">
						</a>
						<p class="bb-featured-name"><a href="<?php echo esc_url( $bb_product['url'] ); ?>" target="_blank"><?php echo esc_html( $bb_product['name'] ); ?></a></p>
						<p class="bb-featured-price"><?php echo wp_kses_post( $bb_product['price_html'] ); ?></p>
					</td>
				<?php endforeach; ?>
			</tr>
		</table>
	</td>
</tr>
