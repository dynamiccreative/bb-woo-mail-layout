<?php
/**
 * Import / export JSON.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Settings\ImportExport;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Settings\ImportExport
 */
class ImportExportTest extends TestCase {

	public function test_roundtrip_reproduces_settings(): void {
		Options::replace(
			array(
				'layout'        => 'sobre',
				'color_primary' => '#8a5a00',
				'contact_phone' => '04 75 00 00 00',
				'footer_text'   => '<strong>Moulin</strong>',
				'emails'        => array( 'new_order' => 'no' ),
				'intros'        => array( 'customer_note' => 'Bonjour {customer_first_name}' ),
			)
		);
		$before = Options::all();
		$json   = wp_json_encode( ImportExport::export_data() );

		update_option( BB_WML_OPTION, Options::defaults() );
		$this->assertSame( array(), ImportExport::import( $json ) );
		$this->assertSame( $before, Options::all() );
	}

	public function test_unknown_keys_are_refused_and_nothing_changes(): void {
		$data                         = ImportExport::export_data();
		$data['settings']['php_code'] = 'phpinfo();';
		update_option( BB_WML_OPTION, array_merge( Options::defaults(), array( 'layout' => 'sobre' ) ) );

		$errors = ImportExport::import( wp_json_encode( $data ) );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'php_code', implode( ' ', $errors ) );
		$this->assertSame( 'sobre', Options::get( 'layout' ) );
	}

	public function test_invalid_types_and_foreign_files_are_refused(): void {
		$data                                 = ImportExport::export_data();
		$data['settings']['color_primary']    = array( 'x' );
		$data['settings']['emails']['nested'] = array( 'x' );
		$this->assertCount( 2, ImportExport::validate( wp_json_encode( $data ) ) );

		$this->assertNotEmpty( ImportExport::validate( '{"plugin":"autre"}' ) );
		$this->assertNotEmpty( ImportExport::validate( 'pas du json' ) );
	}
}
