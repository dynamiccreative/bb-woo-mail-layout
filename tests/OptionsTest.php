<?php
/**
 * Assainissement des réglages.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Settings\Options
 */
class OptionsTest extends TestCase {

	public function test_color_falls_back_to_default_when_invalid(): void {
		$this->assertSame( '#abcdef', Options::sanitize_field( 'color_primary', '#ABCDEF' ) );
		$this->assertSame( Options::defaults()['color_primary'], Options::sanitize_field( 'color_primary', 'red;background:url(x)' ) );
	}

	public function test_bool_and_width_bounds(): void {
		$this->assertSame( 'yes', Options::sanitize_field( 'show_logo', '1' ) );
		$this->assertSame( 'no', Options::sanitize_field( 'show_logo', null ) );
		$this->assertSame( 600, Options::sanitize_field( 'logo_max_width', '5000' ) );
		$this->assertSame( 50, Options::sanitize_field( 'logo_max_width', '3' ) );
	}

	public function test_unknown_layout_is_rejected(): void {
		$this->assertSame( 'sobre', Options::sanitize_field( 'layout', 'sobre' ) );
		$this->assertSame( 'classique', Options::sanitize_field( 'layout', '../../etc' ) );
	}

	public function test_footer_html_keeps_only_bold_links_and_breaks(): void {
		$clean = Options::sanitize_field( 'footer_text', '<strong>SARL</strong><br><a href="https://ex.org" onclick="x()">lien</a><script>alert(1)</script><img src=x>' );
		$this->assertSame( '<strong>SARL</strong><br><a href="https://ex.org">lien</a>alert(1)', $clean );
	}

	public function test_email_map_is_merged_with_stored_values(): void {
		Options::replace( array( 'emails' => array( 'new_order' => 'no' ) ) );
		$merged = Options::sanitize_field( 'emails', array( 'customer_note' => 'no' ) );
		$this->assertSame(
			array(
				'new_order'     => 'no',
				'customer_note' => 'no',
			),
			$merged
		);
	}

	public function test_unknown_key_returns_null(): void {
		$this->assertNull( Options::sanitize_field( 'evil', 'x' ) );
	}
}
