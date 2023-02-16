<?php
/**
 * Plugin Name: DPO Pay for WooCommerce
 * Plugin URI: https://github.com/DPO-Group/DPO_WooCommerce
 * Description: Receive payments using the African DPO Pay payments provider.
 * Author: DPO Group
 * Author URI: https://www.dpogroup.com/
 * Version: 1.1.2
 * Requires at least: 4.4
 * Tested up to: 5.9
 * WC tested up to: 5.6.0
 * WC requires at least: 4.9
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2023 DPO Group
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: dpo-group-for-woocommerce
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $woothemes;
$woothemes = 'woothemes';

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit();
}

// Check if WooCommerce is active, if not then deactivate and show error message
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
        "<strong>DPO Pay</strong> requires <strong>WooCommerce</strong> plugin to work normally. Please activate it or install it from <a href=\"http://wordpress.org/plugins/woocommerce/\" target=\"_blank\">here</a>.<br /><br />Back to the WordPress <a href='" . get_admin_url(
            null,
            'plugins.php'
        ) . "'>Plugins page</a>."
    );
}

$new_folder = 'woocommerce-gateway-direct-pay-online';
$target = WP_PLUGIN_DIR . '/' . $new_folder;
$new_file = '/gateway-direct-pay-online.php';
if(file_exists($target . $new_file)) {
    recurseRmdir($target);
}

add_action('plugins_loaded', 'gdpo_woocommerce_dpo_init');
register_deactivation_hook(__FILE__, 'gdpo_delete_dbo_custom_order_table');

// Main DPO Pay plugin function
function gdpo_woocommerce_dpo_init()
{
    // Check if woocommerce  installed
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_basename('classes/dpo.class.php');

    // Add the Gateway to WooCommerce
    function gdpo_woocommerce_add_gateway_dpo($methods)
    {
        $methods[] = 'WCGatewayDPO';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'gdpo_woocommerce_add_gateway_dpo');

    // Add action for notify
    add_action('woocommerce_api_check_dpo_notify', [WCGatewayDPO::class, 'check_dpo_notify']);

    // Add actions for cron jobs
    add_action(
        'woocommerce_order_actions',
        [WCGatewayDpoCron::class, 'dpo_add_order_meta_box_action']
    );
    add_action(
        'woocommerce_order_action_do_dpo_cron',
        [WCGatewayDpoCron::class, 'dpo_order_query_cron']
    );
    add_action('dpo_order_query_cron_admin', [WCGatewayDpoCron::class, 'dpo_order_query_cron']);
    add_action('dpo_order_query_cron_hook', [WCGatewayDpoCron::class, 'dpo_order_query_cron']);

    $nxt = wp_next_scheduled('dpo_order_query_cron_hook');
    if (!$nxt) {
        wp_schedule_event(time(), 'hourly', 'dpo_order_query_cron_hook');
    }
}

function gdpo_delete_dbo_custom_order_table()
{
    // Delete custom table for DPO order data
    global $wpdb;
    $dpo_table_name = $wpdb->prefix . 'dpo_order_data';
    $sql            = "drop table if exists $dpo_table_name";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $wpdb->query($sql);
}

// Adding custom tabs (Service_type) to woocommerce product data settingds
function gdpo_custom_tab_options_tab()
{
    global $woothemes;
    ?>
    <ul>
        <li class="custom_tab"><a href="#dpo_service_tab_data"> <?php
                _e('DPO Service Type', $woothemes); ?></a></li>
    </ul>
    <?php
}

add_action('woocommerce_product_write_panel_tabs', 'gdpo_custom_tab_options_tab');

function gdpo_custom_tab_options()
{
    global $post;
    global $woothemes;

    $gdpo_custom_tab_options = array(
        'service_type' => get_post_meta($post->ID, 'service_type', true),
    );

    $service_type = @$gdpo_custom_tab_options['service_type'];

    ?>
    <div id="dpo_service_tab_data" class="panel woocommerce_options_panel">

        <div class="options_group custom_tab_options">
            <p class="form-field">
                <label><?php
                    _e('Service Type:', $woothemes); ?></label>
                <input type="text" name="service_type" value="<?php
                echo esc_attr($service_type); ?>" placeholder="<?php
                _e('For example: 45', $woothemes); ?>"/>
            </p>

        </div>
    </div>
    <?php
}

add_action('woocommerce_product_data_panels', 'gdpo_custom_tab_options');

/**
 * Process meta
 *
 * Processes the custom tab options when a post is saved
 */
function gdpo_process_product_meta_custom_tab($post_id)
{
    $service_type = sanitize_text_field($_POST['service_type']);
    update_post_meta($post_id, 'service_type', $service_type);
}

add_action('woocommerce_process_product_meta', 'gdpo_process_product_meta_custom_tab');

function recurseRmdir($dir) {
  $files = array_diff(scandir($dir), array('.','..'));
  foreach ($files as $file) {
    (is_dir("$dir/$file")) ? recurseRmdir("$dir/$file") : unlink("$dir/$file");
  }
  return rmdir($dir);
}
