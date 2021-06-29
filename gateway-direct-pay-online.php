<?php
/**
 *
 * Plugin Name: DPO Group plugin for WooCommerce
 * Plugin URI: https://github.com/DPO-Group/DPO_WooCommerce
 * Description: Accept payments for WooCommerce using DPO Group's online payments service
 * Version: 1.1.0
 * Tested: 5.4.2
 * Author: DPO Group
 * Author URI: https://www.dpogroup.com/africa/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * WC requires at least: 3.0
 * WC tested up to: 4.2
 *
 * Copyright: © 2021 DPO Group
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$woothemes = 'woothemes';
// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit();
}

// Check if WooCommerce is active, if not then deactivate and show error message
if ( ! is_plugin_active('woocommerce/woocommerce.php')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
        "<strong>DPO Group</strong> requires <strong>WooCommerce</strong> plugin to work normally. Please activate it or install it from <a href=\"http://wordpress.org/plugins/woocommerce/\" target=\"_blank\">here</a>.<br /><br />Back to the WordPress <a href='" . get_admin_url(
            null,
            'plugins.php'
        ) . "'>Plugins page</a>."
    );
}

add_action('plugins_loaded', 'woocommerce_dpo_init');
register_deactivation_hook(__FILE__, 'delete_dbo_custom_order_table');

// Main DPO Group plugin function
function woocommerce_dpo_init()
{
    // Check if woocommerce  installed
    if ( ! class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_basename('classes/dpo.class.php');

    // Add the Gateway to WooCommerce
    function woocommerce_add_gateway_dpo($methods)
    {
        $methods[] = 'WCGatewayDPO';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_dpo');

    require_once 'classes/updater.class.php';


    if (is_admin()) {
        // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug'               => plugin_basename(__FILE__),
            'proper_folder_name' => 'woocommerce-gateway-direct-pay-online',
            'api_url'            => 'https://api.github.com/repos/DPO-Group/DPO_WooCommerce',
            'raw_url'            => 'https://raw.github.com/DPO-Group/DPO_WooCommerce/master',
            'github_url'         => 'https://github.com/DPO-Group/DPO_WooCommerce',
            'zip_url'            => 'https://github.com/DPO-Group/DPO_WooCommerce/archive/master.zip',
            'homepage'           => 'https://github.com/DPO-Group/DPO_WooCommerce',
            'sslverify'          => true,
            'requires'           => '4.0',
            'tested'             => '5.7',
            'readme'             => 'README.md',
            'access_token'       => '',
        );

        new WpdpowcGitHubUpdater($config);
    }

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
    if ( ! $nxt) {
        wp_schedule_event(time(), 'hourly', 'dpo_order_query_cron_hook');
    }
}

function delete_dbo_custom_order_table()
{
    // Delete custom table for DPO order data
    global $wpdb;
    $dpo_table_name = $wpdb->prefix . 'dpo_order_data';
    $sql            = "drop table if exists $dpo_table_name";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $wpdb->query($sql);
}

// Adding custom tabs (Service_type) to woocommerce product data settingds
function custom_tab_options_tab()
{
    ?>
    <ul>
        <li class="custom_tab"><a href="#dpo_service_tab_data"> <?php
                _e('DPO Service Type', $woothemes); ?></a></li>
    </ul>
    <?php
}

add_action('woocommerce_product_write_panel_tabs', 'custom_tab_options_tab');

function custom_tab_options()
{
    global $post;

    $custom_tab_options = array(
        'service_type' => get_post_meta($post->ID, 'service_type', true),
    );

    ?>
    <div id="dpo_service_tab_data" class="panel woocommerce_options_panel">

        <div class="options_group custom_tab_options">
            <p class="form-field">
                <label><?php
                    _e('Service Type:', $woothemes); ?></label>
                <input type="text" name="service_type" value="<?php
                echo @$custom_tab_options['service_type']; ?>" placeholder="<?php
                _e('For example: 45', $woothemes); ?>"/>
            </p>

        </div>
    </div>
    <?php
}

add_action('woocommerce_product_write_panels', 'custom_tab_options');

/**
 * Process meta
 *
 * Processes the custom tab options when a post is saved
 */
function process_product_meta_custom_tab($post_id)
{
    update_post_meta($post_id, 'service_type', $_POST['service_type']);
}

add_action('woocommerce_process_product_meta', 'process_product_meta_custom_tab');
