<?php
/**
 * Layout « Sobre » — en-tête : tout blanc, logo, filet de couleur.
 *
 * Variables : voir LayoutRenderer::view_vars().
 *
 * @package BB\WooMailLayout
 * @var array<string, mixed> $v
 */

defined( 'ABSPATH' ) || exit;

$bb_colors = $v['colors'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $v['lang'] ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo esc_attr( $v['charset'] ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="x-apple-disable-message-reformatting">
	<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
	<meta name="color-scheme" content="light">
	<meta name="supported-color-schemes" content="light">
	<title><?php echo esc_html( $v['site_title'] ); ?></title>
	<!--[if mso]>
	<xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
	<![endif]-->
	<?php if ( $v['google_font_url'] ) : ?>
		<link href="<?php echo esc_url( $v['google_font_url'] ); ?>" rel="stylesheet" type="text/css"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- document e-mail, hors file d'attente WordPress. ?>
	<?php endif; ?>
	<style type="text/css">
<?php echo $v['css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS du plugin, jetons assainis, « </ » neutralisé. ?>
	</style>
</head>
<body class="bb-body bb-layout-sobre" bgcolor="#ffffff" style="background-color:#ffffff;">
<table role="presentation" class="bb-wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;">
	<tr>
		<td class="bb-wrapper-cell" align="center" valign="top">
			<!--[if mso]><table role="presentation" align="center" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
			<table role="presentation" class="bb-container" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td class="bb-header" align="center" bgcolor="#ffffff" style="background-color:#ffffff;padding:28px 32px 20px;border-bottom:3px solid <?php echo esc_attr( $bb_colors['primary'] ); ?>;">
						<?php if ( $v['logo'] ) : ?>
							<a href="<?php echo esc_url( $v['site_url'] ); ?>" target="_blank" style="text-decoration:none;">
								<?php if ( $v['logo']['width'] ) : ?>
								<img src="<?php echo esc_url( $v['logo']['url'] ); ?>" alt="<?php echo esc_attr( $v['logo']['alt'] ); ?>" width="<?php echo (int) $v['logo']['width']; ?>" height="<?php echo (int) $v['logo']['height']; ?>" style="display:block;margin:0 auto;width:<?php echo (int) $v['logo']['width']; ?>px;max-width:100%;height:auto;border:0;">
								<?php else : ?>
								<img src="<?php echo esc_url( $v['logo']['url'] ); ?>" alt="<?php echo esc_attr( $v['logo']['alt'] ); ?>" style="display:block;margin:0 auto;width:auto;max-width:<?php echo (int) $v['logo']['max_width']; ?>px;height:auto;max-height:<?php echo (int) $v['logo']['max_height']; ?>px;border:0;">
								<?php endif; ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( $v['site_url'] ); ?>" target="_blank" style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:bold;color:<?php echo esc_attr( $bb_colors['primary'] ); ?>;text-decoration:none;"><?php echo esc_html( $v['site_title'] ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td class="bb-main" bgcolor="#ffffff" valign="top">
						<?php if ( '' !== $v['heading'] ) : ?>
							<h1 class="bb-heading"><?php echo esc_html( $v['heading'] ); ?></h1>
						<?php endif; ?>
						<?php if ( '' !== $v['intro_html'] ) : ?>
							<div class="bb-intro"><?php echo wp_kses_post( $v['intro_html'] ); ?></div>
						<?php endif; ?>
