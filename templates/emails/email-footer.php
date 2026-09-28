<?php
/**
 * Pied des e-mails : délègue au layout actif (layouts/{layout}/footer.php).
 *
 * @package BB\WooMailLayout
 * @var mixed $email E-mail (transmis par certaines versions / extensions seulement).
 */

defined( 'ABSPATH' ) || exit;

\BB\WooMailLayout\Plugin::instance()->renderer()->render_footer(
	isset( $email ) && $email instanceof WC_Email ? $email : null
);
