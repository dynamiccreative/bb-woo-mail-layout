<?php
/**
 * En-tête des e-mails : délègue au layout actif (layouts/{layout}/header.php).
 *
 * @package BB\WooMailLayout
 * @var mixed $email_heading Titre (réglage WooCommerce).
 * @var mixed $email         E-mail (transmis par certaines versions / extensions seulement).
 */

defined( 'ABSPATH' ) || exit;

\BB\WooMailLayout\Plugin::instance()->renderer()->render_header(
	isset( $email_heading ) ? (string) $email_heading : '',
	isset( $email ) && $email instanceof WC_Email ? $email : null
);
