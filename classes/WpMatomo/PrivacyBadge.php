<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace WpMatomo;

use WpMatomo\User\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // if accessed directly
}

class PrivacyBadge {

	public function register_hooks() {
		add_shortcode( 'matomo_privacy_badge', array( $this, 'show_privacy_page' ) );
	}

	public function show_privacy_page( $atts ) {
		$a = shortcode_atts( array(
			'size'  => '120',
			'align' => ''
		), $atts );

		$option = sprintf( ' width="%s" height="%s"', esc_attr( $a['size'] ), esc_attr( $a['size'] ) );

		if ( ! empty( $a['align'] ) ) {
			$option .= sprintf( ' align="%s"', esc_attr( $a['align'] ) );
		}

		$url = plugins_url( 'assets/img/privacybadge.png', MATOMO_ANALYTICS_FILE );

		return sprintf( '<img alt="Your privacy protected! This website uses Matomo." src="%s" %s>', esc_attr( $url ), $option );
	}

}
