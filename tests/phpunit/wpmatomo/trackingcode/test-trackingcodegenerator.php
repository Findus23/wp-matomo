<?php
/**
 * @package Matomo_Analytics
 */

use WpMatomo\Access;
use WpMatomo\Admin\TrackingSettings;
use WpMatomo\Capabilities;
use WpMatomo\Roles;
use WpMatomo\Settings;
use WpMatomo\TrackingCode\TrackingCodeGenerator;

class TrackingCodeGeneratorTest extends MatomoUnit_TestCase {

	/**
	 * @var TrackingCodeGenerator
	 */
	private $trackingCode;

	/**
	 * @var Settings
	 */
	private $settings;

	public function setUp() {
		parent::setUp();

		$this->settings = new Settings();
		WpMatomo\Site::map_matomo_site_id( get_current_blog_id(), 21 );
	}

	private function make_tracking_code() {
		$this->trackingCode = new TrackingCodeGenerator( $this->settings );
	}

	private function get_tracking_code() {
		$this->make_tracking_code();

		return $this->trackingCode->get_tracking_code();
	}

	public function test_get_tracking_code_when_tracking_is_disabled() {
		$this->assertSame( '', $this->get_tracking_code() );
	}

	public function test_get_tracking_code_when_using_default_tracking_code() {
		$this->settings->apply_tracking_related_changes( array(
			'track_mode' => TrackingSettings::TRACK_MODE_DEFAULT
		) );
		$this->assertSame( '<!-- Matomo --><script  type="text/javascript">var _paq = window._paq || [];
_paq.push([\'trackPageView\']);_paq.push([\'enableLinkTracking\']);_paq.push([\'setTrackerUrl\', "\/\/example.org\/wp-content\/plugins\/wp-matomo\/matomo\/matomo.php"]);_paq.push([\'setSiteId\', \'21\']);var d=document, g=d.createElement(\'script\'), s=d.getElementsByTagName(\'script\')[0];
    g.type=\'text/javascript\'; g.async=true; g.defer=true; g.src="\/\/example.org\/wp-content\/uploads\/matomo\/matomo.js"; s.parentNode.insertBefore(g,s);</script><!-- End Matomo Code -->', $this->get_tracking_code() );
	}

	public function test_get_tracking_code_when_using_default_tracking_code_using_rest_api_and_other_features() {
		$this->settings->apply_tracking_related_changes( array(
			'track_mode'                => TrackingSettings::TRACK_MODE_DEFAULT,
			'track_js_endpoint'         => 'restapi',
			'track_api_endpoint'        => 'restapi',
			'track_noscript'            => true,
			'track_content'             => true,
			'add_download_extensions'   => 'zip|waf',
			'set_link_classes'          => 'clickme|foo',
			'disable_cookies'           => true,
			'track_across'              => true,
			'track_crossdomain_linking' => true,
		) );

		$this->assertSame( '<!-- Matomo --><script  type="text/javascript">var _paq = window._paq || [];
_paq.push([\'addDownloadExtensions\', "zip|waf"]);
_paq.push([\'setLinkClasses\', "clickme|foo"]);
_paq.push([\'disableCookies\']);
_paq.push([\'enableCrossDomainLinking\']);
  _paq.push(["setCookieDomain", "*.example.org"]);
_paq.push([\'trackAllContentImpressions\']);_paq.push([\'trackPageView\']);_paq.push([\'enableLinkTracking\']);_paq.push([\'setTrackerUrl\', "\/\/example.org\/index.php?rest_route=\/ma\/v1\/hit\/"]);_paq.push([\'setSiteId\', \'21\']);var d=document, g=d.createElement(\'script\'), s=d.getElementsByTagName(\'script\')[0];
    g.type=\'text/javascript\'; g.async=true; g.defer=true; g.src="\/\/example.org\/index.php?rest_route=\/ma\/v1\/hit\/"; s.parentNode.insertBefore(g,s);</script><!-- End Matomo Code -->', $this->get_tracking_code() );
	}

	public function test_get_tracking_code_test_user_id() {
		$id1 = self::factory()->user->create();

		wp_set_current_user( $id1 );
		$this->settings->apply_tracking_related_changes( array(
			'track_mode'    => TrackingSettings::TRACK_MODE_DEFAULT,
			'track_user_id' => 'uid',
		) );
		$this->assertContains( "_paq.push(['setUserId', '$id1']);", $this->get_tracking_code() );
	}

	public function test_get_tracking_code_when_using_manually_tracking_code() {
		$this->settings->apply_tracking_related_changes( array(
			'track_mode'    => TrackingSettings::TRACK_MODE_MANUALLY,
			'tracking_code' => '<script>foobar</script>'
		) );
		$this->assertSame( '<script>foobar</script>', $this->get_tracking_code() );
	}


}
