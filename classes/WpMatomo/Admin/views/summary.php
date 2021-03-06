<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

use WpMatomo\Admin\Menu;
use WpMatomo\Report\Dates;
use WpMatomo\Admin\AdminSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $report_metadata */
/** @var array $report_dates */
/** @var array $reports_to_show */
/** @var string $report_date */
/** @var bool $is_tracking */
global $wp;
?>
<?php if ( ! $is_tracking ) { ?>
    <div class="notice notice-warning"><p><?php echo __( 'Matomo Tracking is not enabled.', 'matomo' ) ?></p></div>
<?php } ?>
<div class="wrap">
    <div id="icon-plugins" class="icon32"></div>
    <h1>Summary</h1>
	<?php
	if ( $report_date === Dates::TODAY ) {
		echo '<div class="notice notice-info" style="padding:8px;">Reports for today are only refreshed approximately every hour through the WordPress cronjob.</div>';
	}
	?>
    <p>Looking for all reports and advanced features like segmentation, real time reports, and more? <a
                href="<?php echo add_query_arg( array( 'report_date' => $report_date ), menu_page_url( Menu::SLUG_REPORTING, false ) ) ?>">View
            full reporting</a>
        <br/><br/>
        Change date:
		<?php foreach ( $report_dates as $report_date_key => $report_name ) {
			$buttonClass = 'button';
			if ( $report_date === $report_date_key ) {
				$buttonClass = 'button-primary';
			}
			echo '<a href="' . esc_url( add_query_arg( array( 'report_date' => $report_date_key ), menu_page_url( Menu::SLUG_REPORT_SUMMARY, false ) ) ) . '" class="' . $buttonClass . '">' . esc_html( $report_name ) . '</a> ';
		} ?>

    <div id="dashboard-widgets" class="metabox-holder columns-2 has-right-sidebar">
		<?php
		$columns = array( 1, 0 );
		foreach ( $columns as $columnIndex => $columnModulo ) { ?>
            <div id="postbox-container-<?php echo( $columnIndex + 1 ) ?>" class="postbox-container">
                <div id="normal-sortables" class="meta-box-sortables ui-sortable">
					<?php
					foreach ( $reports_to_show as $index => $report_meta ) {
						if ( $index % 2 === $columnModulo ) {
							continue;
						}
						$shortcode = sprintf( '[matomo_report unique_id=%s report_date=%s limit=10]', $report_meta['uniqueId'], $report_date );
						?>
                        <div class="postbox">

                            <h2 class="hndle ui-sortable-handle"
                                style="cursor: help;"
                                title="<?php echo ! empty( $report_meta['documentation'] ) ? ( wp_strip_all_tags( $report_meta['documentation'] ) . ' ' ) : null ?>You can embed this report on any page using the shortcode: <?php echo esc_attr( $shortcode ); ?>"
                            ><?php echo esc_html( $report_meta['name'] ) ?></h2>
                            <div>
								<?php echo do_shortcode( $shortcode ); ?>
                            </div>
                        </div>
					<?php } ?>
                </div>
            </div>
		<?php } ?>
    </div>

    <p style="clear:both;">Did you know? You can embed any report into any page or post using a shortcode. Simply hover
        the title to find the correct shortcode.
        Only users with view access will be able to view the content of the report. Note: Embedding report data can be
        tricky
        if you are using caching plugins that cache the entire HTML of your page or post. In case you are using such a
        plugin, we recommend you disable the caching for these pages.
    </p>
</div>
