<?php
/**
 * @package Matomo_Analytics
 */

use WpMatomo\Admin\GetStarted;
use WpMatomo\Admin\TrackingSettings;
use WpMatomo\Admin\Info;
use WpMatomo\Roles;
use WpMatomo\Settings;

class AdminGetStartedTest extends MatomoUnit_TestCase {

	/**
	 * @var GetStarted
	 */
	private $get_started;

	/**
	 * @var Settings
	 */
	private $settings;

	public function setUp() {
		parent::setUp();

		$this->settings    = new Settings();
		$this->get_started = new GetStarted( $this->settings );

		wp_get_current_user()->add_role( Roles::ROLE_SUPERUSER );

		$this->assume_admin_page();
	}

	public function tearDown() {
		$_REQUEST = array();
		$_POST    = array();
		parent::tearDown();
	}

	public function test_show_renders_ui() {
		ob_start();
		$this->get_started->show();
		$output = ob_get_clean();
		$this->assertNotEmpty( $output );
		$this->assertContains( '', $output );
	}

	/**
	 * @group ms-required
	 */
	public function test_show_multisite_renders_ui() {
		ob_start();
		$this->get_started->show_multisite();
		$output = ob_get_clean();
		$this->assertNotEmpty( $output );
		$this->assertContains( 'Multi Site mode', $output );
	}

	public function test_show_does_change_value_if_nonce() {
		$this->assertSame( TrackingSettings::TRACK_MODE_DISABLED, $this->settings->get_global_option( 'track_mode' ) );

		$this->fake_request( TrackingSettings::TRACK_MODE_DEFAULT );

		$this->get_started->show();

		$this->assertSame( TrackingSettings::TRACK_MODE_DEFAULT, $this->settings->get_global_option( 'track_mode' ) );
		// still show the get started page
		$this->assertNotEmpty( $this->settings->get_global_option( Settings::SHOW_GET_STARTED_PAGE ) );
	}

	public function test_show_settings_does_not_change_any_values_when_not_superuser() {
		wp_get_current_user()->remove_role( Roles::ROLE_SUPERUSER );

		$this->fake_request( TrackingSettings::TRACK_MODE_DEFAULT );

		$this->get_started->show();

		$this->assertSame( TrackingSettings::TRACK_MODE_DISABLED, $this->settings->get_global_option( 'track_mode' ) );
	}

	public function test_show_settings_does_not_change_any_values_when_not_correct_value() {

		$this->fake_request( 'manually' );

		$this->get_started->show();

		$this->assertSame( TrackingSettings::TRACK_MODE_DISABLED, $this->settings->get_global_option( 'track_mode' ) );
	}

	public function test_show_settings_get_started_page() {

		$this->assertNotEmpty( $this->settings->get_global_option( Settings::SHOW_GET_STARTED_PAGE ) );
		$this->fake_request( Settings::SHOW_GET_STARTED_PAGE, 'no' );

		$this->get_started->show();

		$this->assertEmpty( $this->settings->get_global_option( Settings::SHOW_GET_STARTED_PAGE ) );
	}

	public function test_show_settings_get_started_page_when_not_correct_value() {

		$this->assertNotEmpty( $this->settings->get_global_option( Settings::SHOW_GET_STARTED_PAGE ) );
		$this->fake_request( Settings::SHOW_GET_STARTED_PAGE, '' );

		$this->get_started->show();

		$this->assertNotEmpty( $this->settings->get_global_option( Settings::SHOW_GET_STARTED_PAGE ) );
	}

	private function fake_request( $track_mode_value, $post_key = 'track_mode' ) {
		$_POST[ Info::FORM_NAME ] = array( $post_key => $track_mode_value );
		$_REQUEST['_wpnonce']     = wp_create_nonce( Info::NONCE_NAME );
		$_SERVER['REQUEST_URI']   = home_url();

	}


}
