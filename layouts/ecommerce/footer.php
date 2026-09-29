<?php
/**
 * Layout « E-commerce » — pied : aide, réseaux sociaux, mentions.
 *
 * Variables : voir LayoutRenderer::view_vars().
 *
 * @package BB\WooMailLayout
 * @var array<string, mixed> $v
 */

defined( 'ABSPATH' ) || exit;

$bb_colors = $v['colors'];
$bb_footer = $v['footer'];
?>
					</td>
				</tr>
				<?php echo $v['featured_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans layouts/partials/featured-products.php. ?>
				<?php if ( $v['help'] ) : ?>
					<tr>
						<td class="bb-help" bgcolor="#ffffff">
							<table role="presentation" class="bb-help-box" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $bb_colors['soft'] ); ?>">
								<tr>
									<td class="bb-help-cell">
										<p class="bb-help-title"><?php esc_html_e( 'Besoin d’aide ?', 'bb-woo-mail-layout' ); ?></p>
										<?php if ( '' !== $v['help']['phone'] ) : ?>
											<?php esc_html_e( 'Téléphone :', 'bb-woo-mail-layout' ); ?> <a href="<?php echo esc_attr( $v['help']['phone_href'] ); ?>"><?php echo esc_html( $v['help']['phone'] ); ?></a><br>
										<?php endif; ?>
										<?php if ( '' !== $v['help']['email'] ) : ?>
											<?php esc_html_e( 'E-mail :', 'bb-woo-mail-layout' ); ?> <a href="mailto:<?php echo esc_attr( antispambot( $v['help']['email'] ) ); ?>"><?php echo esc_html( antispambot( $v['help']['email'] ) ); ?></a><br>
										<?php endif; ?>
										<?php if ( '' !== $v['help']['hours'] ) : ?>
											<?php echo esc_html( $v['help']['hours'] ); ?>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( $v['socials'] ) : ?>
					<tr>
						<td class="bb-social" align="center">
							<?php foreach ( $v['socials'] as $bb_social ) : ?>
								<a href="<?php echo esc_url( $bb_social['url'] ); ?>" target="_blank" title="<?php echo esc_attr( $bb_social['label'] ); ?>" style="text-decoration:none;"><img src="<?php echo esc_url( $bb_social['icon'] ); ?>" alt="<?php echo esc_attr( $bb_social['label'] ); ?>" width="32" height="32" style="display:inline-block;width:32px;height:32px;border:0;margin:0 6px;"></a>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( $bb_footer ) : ?>
					<tr>
						<td class="bb-footer" align="center">
							<?php if ( '' !== $bb_footer['legal_name'] || '' !== $bb_footer['address_html'] ) : ?>
								<p>
									<?php if ( '' !== $bb_footer['legal_name'] ) : ?>
										<span class="bb-legal-name"><?php echo esc_html( $bb_footer['legal_name'] ); ?></span><br>
									<?php endif; ?>
									<?php echo wp_kses( $bb_footer['address_html'], array( 'br' => array() ) ); ?>
								</p>
							<?php endif; ?>
							<?php if ( '' !== $bb_footer['site_url'] ) : ?>
								<p><a href="<?php echo esc_url( $bb_footer['site_url'] ); ?>" target="_blank"><?php echo esc_html( $bb_footer['site_label'] ); ?></a></p>
							<?php endif; ?>
							<?php if ( '' !== $bb_footer['text_html'] ) : ?>
								<div class="bb-footer-text"><?php echo wp_kses( $bb_footer['text_html'], \BB\WooMailLayout\Settings\Options::allowed_footer_html() ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== $bb_footer['unsubscribe_url'] ) : ?>
								<p><a href="<?php echo esc_url( $bb_footer['unsubscribe_url'] ); ?>" target="_blank"><?php esc_html_e( 'Se désinscrire', 'bb-woo-mail-layout' ); ?></a></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<!--[if mso]></td></tr></table><![endif]-->
		</td>
	</tr>
</table>
</body>
</html>
