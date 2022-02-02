<?php
/**
 *
 * Plugin Name: DPO Group plugin for WooCommerce
 * Plugin URI: https://github.com/DPO-Group/DPO_WooCommerce
 * Description: Accept payments for WooCommerce using DPO Group's online payments service
 * Version: 1.1.1
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

$current_folder = plugin_basename(__DIR__);
$new_folder     = 'dpo-group-for-woocommerce';
$new_file       = '/gateway-direct-pay-online.php';
$source         = WP_PLUGIN_DIR . '/' . $current_folder . '/' . $new_folder;
$target         = WP_PLUGIN_DIR . '/' . $new_folder;
if (file_exists($source . $new_file)) {
    rename($source, $target);
}

$new_plugin = $new_folder . $new_file;
if (is_plugin_inactive($new_plugin)) {
    activate_plugin($new_plugin);
}
deactivate_plugins('woocommerce-gateway-direct-pay-online/gateway-direct-pay-online.php');
