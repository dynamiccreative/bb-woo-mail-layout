<?php
/**
 * Découverte des e-mails.
 *
 * @package BB\WooMailLayout
 */

namespace BB\WooMailLayout\Tests;

use BB\WooMailLayout\Email\EmailRegistry;
use BB\WooMailLayout\Settings\Options;

/**
 * @covers \BB\WooMailLayout\Email\EmailRegistry
 */
class RegistryTest extends TestCase {

	public function test_native_emails_are_detected_with_recipient_type(): void {
		$all = ( new EmailRegistry() )->all();

		$this->assertArrayHasKey( 'new_order', $all );
		$this->assertArrayHasKey( 'customer_processing_order', $all );
		$this->assertFalse( $all['new_order']['customer'] );
		$this->assertTrue( $all['customer_processing_order']['customer'] );
		$this->assertTrue( $all['customer_processing_order']['native'] );
	}

	public function test_emails_are_enabled_by_default_and_can_be_disabled(): void {
		$registry = new EmailRegistry();
		$this->assertTrue( $registry->is_enabled( 'new_order' ) );

		Options::replace( array( 'emails' => array( 'new_order' => 'no' ) ) );
		$this->assertFalse( $registry->is_enabled( 'new_order' ) );
	}
}
