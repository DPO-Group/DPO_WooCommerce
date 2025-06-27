<?php
/**
 * Plugin Name: DPO Pay for WooCommerce
 * Plugin URI: https://github.com/DPO-Group/DPO_WooCommerce
 * Description: Receive payments using the African DPO Pay payments provider.
 * Author: DPO Group
 * Author URI: https://www.dpogroup.com/
 * Version: 1.3.0
 * Requires at least: 6.2
 * Tested up to: 6.8.1
 * WC tested up to: 9.8.5
 * WC requires at least: 6.0
 * Requires PHP: 8.0
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2025 DPO Group
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: dpo-group-for-woocommerce
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $woothemes;
$woothemes = 'woothemes';

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Check if WooCommerce is active, if not then deactivate and show error message
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	deactivate_plugins( plugin_basename( __FILE__ ) );
	wp_die(
		"<strong>DPO Pay</strong> requires <strong>WooCommerce</strong> plugin to work normally. Please activate it or install it from <a href=\"http://wordpress.org/plugins/woocommerce/\" target=\"_blank\">here</a>.<br /><br />Back to the WordPress <a href='" . esc_url(
			get_admin_url( null, 'plugins.php' )
		) . "'>Plugins page</a>."
	);
}

add_action( 'plugins_loaded', 'gdpo_woocommerce_dpo_init' );
register_deactivation_hook( __FILE__, 'gdpo_delete_dbo_custom_order_table' );

/**
 * Main DPO Pay plugin function
 *
 * @return void
 */
function gdpo_woocommerce_dpo_init(): void {
	// Check if woocommerce  installed
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_basename( 'classes/dpo.class.php' );

	/**
	 * Add the Gateway to WooCommerce
	 *
	 * @param $methods
	 *
	 * @return mixed
	 */
	/**
	 * @param $methods
	 *
	 * @return mixed
	 */
	function gdpo_woocommerce_add_gateway_dpo( $methods ): mixed {
		$methods[] = 'WCGatewayDPO';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'gdpo_woocommerce_add_gateway_dpo' );

	// Add action for notify
	add_action( 'woocommerce_api_check_dpo_notify', array( WCGatewayDPO::class, 'check_dpo_notify' ) );

	// Add actions for cron jobs
	add_action(
		'woocommerce_order_actions',
		array( WCGatewayDpoCron::class, 'dpo_add_order_meta_box_action' )
	);
	add_action(
		'woocommerce_order_action_do_dpo_cron',
		array( WCGatewayDpoCron::class, 'dpo_order_query_cron' )
	);
	add_filter( 'cron_schedules', 'dpo_every_ten_minutes' );
	add_action( 'dpo_order_query_cron_admin', array( WCGatewayDpoCron::class, 'dpo_order_query_cron' ) );
	add_action( 'dpo_order_query_cron_hook', array( WCGatewayDpoCron::class, 'dpo_order_query_cron' ) );

	$nxt = wp_next_scheduled( 'dpo_order_query_cron_hook' );
	// Remove old schedule (hourly)
	if ( $nxt ) {
		wp_unschedule_event( $nxt, 'dpo_order_query_cron_hook' );
	}

	// Schedule event (10 min) if not already scheduled
	if ( ! wp_next_scheduled( 'dpo_order_query_cron_hook' ) ) {
		wp_schedule_event( time(), 'dpo_every_ten_minutes', 'dpo_order_query_cron_hook' );
	}
}

function dpo_every_ten_minutes( $schedules ) {
	$schedules['dpo_every_ten_minutes'] = array(
		'interval' => 600,
		'display'  => __( 'DPO Pay Every 10 Minutes', 'dpo-group-for-woocommerce' ),
	);

	return $schedules;
}

/**
 * Delete custom table for DPO order data
 *
 * @return void
 */
function gdpo_delete_dbo_custom_order_table(): void {
	global $wpdb;
	$dpo_table_name = $wpdb->prefix . 'dpo_order_data';
	$sql            = "drop table if exists $dpo_table_name";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$wpdb->query( $sql );
}

/**
 * Adding custom tabs (Service_type) to woocommerce product data settingds
 *
 * @return void
 */
function gdpo_custom_tab_options_tab(): void {
	global $woothemes;
	?>
	<ul>
		<li class="custom_tab"><a href="#dpo_service_tab_data"> 
		<?php
				esc_html_e( 'DPO Service Type', $woothemes );
		?>
		</a></li>
	</ul>
	<?php
}

add_action( 'woocommerce_product_write_panel_tabs', 'gdpo_custom_tab_options_tab' );

/**
 * Renders the tab options
 *
 * @return void
 */
function gdpo_custom_tab_options(): void {
	global $post;
	global $woothemes;

	$gdpo_custom_tab_options = array(
		'service_type' => get_post_meta( $post->ID, 'service_type', true ),
	);

	$service_type = $gdpo_custom_tab_options['service_type'];

	?>
	<div id="dpo_service_tab_data" class="panel woocommerce_options_panel">

		<div class="options_group custom_tab_options">
			<p class="form-field">
				<label>
				<?php
					esc_html_e( 'Service Type:', $woothemes );
				?>
				</label>
				<input type="text" name="service_type" value="
				<?php
				echo esc_attr( $service_type );
				?>
				" placeholder="
				<?php
				esc_html_e( 'For example: 45', $woothemes );
				?>
				"/>
			</p>
			<?php
			wp_nonce_field( 'dpo_save_service_type', 'dpo_service_type_nonce' );
			?>

		</div>
	</div>
	<?php
}

add_action( 'woocommerce_product_data_panels', 'gdpo_custom_tab_options' );

/**
 * Process meta
 *
 * Processes the custom tab options when a post is saved
 */
function gdpo_process_product_meta_custom_tab( $post_id ): void {
	if (
		! isset( $_POST['dpo_service_type_nonce'] ) ||
		! wp_verify_nonce( $_POST['dpo_service_type_nonce'], 'dpo_save_service_type' ) ||
		! isset( $_POST['service_type'] )
	) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . esc_html__( 'Invalid form submission. Please try again.', 'dpo-group-for-woocommerce' ) . '</p>';
				echo '</div>';
			}
		);
		return;
	}

	$service_type = sanitize_text_field( wp_unslash( $_POST['service_type'] ) );

	update_post_meta( $post_id, 'service_type', $service_type );
}

add_action( 'woocommerce_process_product_meta', 'gdpo_process_product_meta_custom_tab' );


/**
 * Registers WooCommerce Blocks integration.
 */
class WC_Dpo_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init(): void {
		// Registers WooCommerce Blocks integration.
		add_action(
			'woocommerce_blocks_loaded',
			array( __CLASS__, 'woocommerce_gateway_dpo_woocommerce_block_support' )
		);
	}

	/**
	 * Add the Dpo Payment gateway to the list of available gateways.
	 *
	 * @param array $gateways
	 */
	public static function add_gateway( array $gateways ): array {
		$gateways[] = 'WC_Gateway_Dpo';

		return $gateways;
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url(): string {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath(): string {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 */
	public static function woocommerce_gateway_dpo_woocommerce_block_support(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'includes/blocks/class-wc-dpo-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Dpo_Blocks_Support() );
				}
			);
		}
	}
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_direct_pay_online_declare_hpos_compatibility(): void {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
}

add_action( 'before_woocommerce_init', 'woocommerce_direct_pay_online_declare_hpos_compatibility' );


WC_Dpo_Payments::init();
