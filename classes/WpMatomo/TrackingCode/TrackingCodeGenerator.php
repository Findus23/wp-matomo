<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace WpMatomo\TrackingCode;

use WpMatomo\Admin\TrackingSettings;
use WpMatomo\Logger;
use WpMatomo\Paths;
use WpMatomo\Settings;
use WpMatomo\Site;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // if accessed directly
}

class TrackingCodeGenerator {

	const TRACKPAGEVIEW = "_paq.push(['trackPageView']);";

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @param Settings $settings
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		$this->logger   = new Logger();
	}

	public function register_hooks() {
		add_action( 'matomo_site_synced', array( $this, 'update_tracking_code' ), $prio = 10, $args = 0 );
	}

	public function update_tracking_code() {
		if ( $this->settings->is_current_tracking_code()
		     && $this->settings->get_option( 'tracking_code' ) ) {

			return false;
		}
		if ( ! $this->settings->is_tracking_enabled()
		     || $this->settings->get_global_option( 'track_mode' ) == TrackingSettings::TRACK_MODE_MANUALLY ) {
			return false;
		}

		$blod_id = get_current_blog_id();
		$idsite  = Site::get_matomo_site_id( $blod_id );

		if ( ! $idsite ) {
			$this->logger->log( 'Not found related idSite for blog ' . get_current_blog_id() );

			return false;
		}

		$result = $this->prepare_tracking_code( $idsite, $this->settings, $this->logger );

		if ( ! empty( $result['script'] ) ) {
			$this->settings->set_option( 'tracking_code', $result['script'] );
			$this->settings->set_option( 'noscript_code', $result['noscript'] );
		}

		$this->settings->set_option( Settings::OPTION_LAST_TRACKING_CODE_UPDATE, time() );
		$this->settings->save();

		return $result;
	}

	public function get_noscript_code() {
		$this->update_tracking_code();

		return $this->settings->get_noscript_tracking_code();
	}

	public function get_tracking_code() {
		$this->update_tracking_code();

		$tracking_code = $this->settings->get_js_tracking_code();

		if ( $this->settings->track_user_id_enabled() ) {
			$tracking_code = $this->apply_user_tracking( $tracking_code );
		}
		if ( $this->settings->track_search_enabled() ) {
			$tracking_code = $this->apply_search_changes( $tracking_code );
		}

		return $tracking_code;
	}

	/**
	 * @param $idsite
	 * @param Settings $settings
	 * @param Logger $logger
	 *
	 * @return array
	 */
	private function prepare_tracking_code( $idsite, $settings, $logger ) {
		$logger->log( 'Apply tracking code changes:' );

		$paths = new Paths();

		if ( $this->settings->get_global_option( 'track_api_endpoint' ) === 'restapi' ) {
			$tracker_endpoint = $paths->get_tracker_api_rest_api_endpoint();
		} else {
			$tracker_endpoint = $paths->get_tracker_api_url_in_matomo_dir();
		}

		if ( $this->settings->get_global_option( 'track_js_endpoint' ) === 'restapi' ) {
			$js_endpoint = $paths->get_js_tracker_rest_api_endpoint();
		} else {
			$js_endpoint = $paths->get_js_tracker_url_in_matomo_dir();
		}

		if ( $settings->get_global_option( 'force_protocol' ) == 'https' ) {
			$js_endpoint      = preg_replace( "(^http://)", "https://", $js_endpoint );
			$tracker_endpoint = preg_replace( "(^http://)", "https://", $tracker_endpoint );
		} else {
			$js_endpoint      = preg_replace( "(^https?://)", "//", $js_endpoint );
			$tracker_endpoint = preg_replace( "(^https?://)", "//", $tracker_endpoint );
		}

		$options = array();

		if ( $settings->get_global_option( 'set_download_extensions' ) ) {
			$options[] = "_paq.push(['setDownloadExtensions', " . json_encode( $settings->get_global_option( 'set_download_extensions' ) ) . "]);";
		}
		if ( $settings->get_global_option( 'add_download_extensions' ) ) {
			$options[] = "_paq.push(['addDownloadExtensions', " . json_encode( $settings->get_global_option( 'add_download_extensions' ) ) . "]);";
		}
		if ( $settings->get_global_option( 'set_download_classes' ) ) {
			$options[] = "_paq.push(['setDownloadClasses', " . json_encode( $settings->get_global_option( 'set_download_classes' ) ) . "]);";
		}
		if ( $settings->get_global_option( 'set_link_classes' ) ) {
			$options[] = "_paq.push(['setLinkClasses', " . json_encode( $settings->get_global_option( 'set_link_classes' ) ) . "]);";
		}
		if ( $settings->get_global_option( 'disable_cookies' ) ) {
			$options[] = "_paq.push(['disableCookies']);";
		}
		if ( $settings->get_global_option( 'track_crossdomain_linking' ) ) {
			$options[] = "_paq.push(['enableCrossDomainLinking']);";
		}

		$cookie_domain = $this->settings->get_tracking_cookie_domain();
		if ( ! empty( $cookie_domain ) ) {
			$options[] = '  _paq.push(["setCookieDomain", ' . json_encode( $cookie_domain ) . ']);';
		}

		$track_across_alias = $settings->get_global_option( 'track_across_alias' );

		if ( $track_across_alias ) {
			// todo detect more hosts such as when using WPML etc
			$hosts = array( @parse_url( home_url(), PHP_URL_HOST ) );
			$hosts = array_filter( $hosts );
			$hosts = array_map( function ( $host ) {
				return '*.' . $host;
			}, $hosts );
			if ( ! empty( $hosts ) ) {
				$options .= '  _paq.push(["setDomains", ' . json_encode( $hosts ) . ']);';
			}
		}

		if ( $settings->track_404_enabled() ) {
			$options[] = "_paq.push(['setDocumentTitle', '404/URL = '+String(document.location.pathname+document.location.search).replace(/\//g,'%2f') + '/From = ' + String(document.referrer).replace(/\//g,'%2f')]);";
		}

		if ( $settings->get_global_option( 'limit_cookies' ) ) {
			$options[] = "_paq.push(['setVisitorCookieTimeout', " . json_encode( $settings->get_global_option( 'limit_cookies_visitor' ) ) . "]);";
			$options[] = "_paq.push(['setSessionCookieTimeout', " . json_encode( $settings->get_global_option( 'limit_cookies_session' ) ) . "]);";
			$options[] = "_paq.push(['setReferralCookieTimeout', " . json_encode( $settings->get_global_option( 'limit_cookies_referral' ) ) . "]);";
		}
		if ( $settings->get_global_option( 'track_content' ) == 'all' ) {
			$options[] = "_paq.push(['trackAllContentImpressions']);";
		} elseif ( $settings->get_global_option( 'track_content' ) == 'visible' ) {
			$options[] = "_paq.push(['trackVisibleContentImpressions']);";
		}
		if ( (int) $settings->get_global_option( 'track_heartbeat' ) > 0 ) {
			$options[] = "_paq.push(['enableHeartBeatTimer', " . intval( $settings->get_global_option( 'track_heartbeat' ) ) . "]);";
		}

		$data_cf_async = '';
		if ( $settings->get_global_option( 'track_datacfasync' ) ) {
			$data_cf_async = 'data-cfasync="false"';
		}

		$script = '<!-- Matomo -->';
		$script .= '<script ' . $data_cf_async . ' type="text/javascript">';
		$script .= "var _paq = window._paq || [];\n";
		$script .= implode( "\n", $options );
		$script .= self::TRACKPAGEVIEW;
		$script .= "_paq.push(['enableLinkTracking']);";
		$script .= "_paq.push(['setTrackerUrl', " . json_encode( $tracker_endpoint ) . "]);";
		$script .= "_paq.push(['setSiteId', '" . intval( $idsite ) . "']);";
		$script .= "var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=" . json_encode( $js_endpoint ) . "; s.parentNode.insertBefore(g,s);";
		$script .= "</script>";
		$script .= "<!-- End Matomo Code -->";

		if ( $settings->get_global_option( 'track_noscript' ) ) {
			$no_script = '<noscript><p><img src="' . esc_url( $tracker_endpoint ) . '?idsite=' . intval( $idsite ) . '&amp;rec=1" style="border:0;" alt="" /></p></noscript>';
		} else {
			$no_script = '';
		}

		$script = apply_filters( 'matomo_tracking_code_script', $script, $idsite );
		$script = apply_filters( 'matomo_tracking_code_noscript', $script, $idsite );

		$logger->log( 'Finished tracking code: ' . $script );
		$logger->log( 'Finished noscript code: ' . $no_script );

		return array(
			'script'   => $script,
			'noscript' => $no_script
		);
	}

	private function apply_search_changes( $tracking_code ) {
		$this->logger->log( 'Apply search tracking changes. Blog ID: ' . get_current_blog_id() );
		$obj_search       = new \WP_Query ( "s=" . get_search_query() . '&showposts=-1' );
		$int_result_count = $obj_search->post_count;
		$tracking_code    = str_replace( self::TRACKPAGEVIEW, "_paq.push(['trackSiteSearch','" . get_search_query() . "', false, " . $int_result_count . "]);\n" . self::TRACKPAGEVIEW, $tracking_code );

		return $tracking_code;
	}

	private function apply_user_tracking( $tracking_code ) {
		$user_id_to_track = null;
		if ( \is_user_logged_in() ) {
			// Get the User ID Admin option, and the current user's data
			$uid_from     = $this->settings->get_global_option( 'track_user_id' );
			$current_user = wp_get_current_user(); // current user
			// Get the user ID based on the admin setting
			if ( $uid_from == 'uid' ) {
				$user_id_to_track = $current_user->ID;
			} elseif ( $uid_from == 'email' ) {
				$user_id_to_track = $current_user->user_email;
			} elseif ( $uid_from == 'username' ) {
				$user_id_to_track = $current_user->user_login;
			} elseif ( $uid_from == 'displayname' ) {
				$user_id_to_track = $current_user->display_name;
			}
		}
		$user_id_to_track = apply_filters( 'matomo_tracking_user_id', $user_id_to_track );
		// Check we got a User ID to track, and track it
		if ( isset( $user_id_to_track ) && ! empty( $user_id_to_track ) ) {
			$tracking_code = str_replace( self::TRACKPAGEVIEW, "_paq.push(['setUserId', '" . esc_js( $user_id_to_track ) . "']);\n" . self::TRACKPAGEVIEW, $tracking_code );
		}

		return $tracking_code;
	}

}
