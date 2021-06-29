<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class WCGatewayDpoCron extends WCGatewayDPO
{
    const LOGGING = 'logging';

    /**
     * Run regularly to update orders
     */
    public static function dpo_order_query_cron()
    {
        $dpo = new WCGatewayDPO();

        // Load the settings
        $settings = get_option('woocommerce_woocommerce_dpo_settings', false);

        $logging = false;

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            $logging = true;
        }

        $logger = wc_get_logger();
        $logging ? $logger->add('dpo_cron', 'Starting DPO cron job') : '';

        $orders = self::dpo_order_query_cron_query();
        $logging ? $logger->add('dpo_cron', 'Orders: ' . json_encode($orders)) : '';

        foreach ($orders as $order) {
            $order_id         = $order->ID;
            $transactionToken = get_post_meta($order_id, 'dpo_trans_token', true);
            // Query DPO for status
            $order = wc_get_order($order_id);

            if ($transactionToken == '') {
                // Cancelled before DPO payment tried
                $order->update_status(
                    'failed',
                    __(
                        'Payment Failed: DPO payment failed or was cancelled. Notice that the stock is NOT reduced. ',
                        'woocommerce'
                    )
                );
                $order->add_order_note(
                    'Payment Failed: DPO payment failed or was cancelled. Notice that the stock is NOT reduced.'
                );
                $order->save();
                continue;
            }

            $response = $dpo->verifytoken($transactionToken);

            if ($response) {
                // Check selected order status workflow
                if ($response->Result[0] == '000') {
                    if ($order->get_status() !== $dpo->successful_status) {
                        switch ($dpo->successful_status) {
                            case 'on-hold':
                                $order->update_status(
                                    'on-hold',
                                    __(
                                        'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ',
                                        'woocommerce'
                                    )
                                );
                                $order->add_order_note(
                                    'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. '
                                );
                                break;
                            case 'completed':
                                $order->update_status(
                                    'completed',
                                    __('The transaction paid successfully and order approved.', 'woocommerce')
                                );
                                $order->add_order_note('The transaction paid successfully and order approved.');
                                $order->payment_complete();
                                break;
                            default:
                                $order->update_status(
                                    'processing',
                                    __('The transaction paid successfully and waiting for approval.', 'woocommerce')
                                );
                                $order->add_order_note('The transaction paid successfully and waiting for approval.');
                                $order->payment_complete();
                                break;
                        }
                    }
                } else {
                    $error_code = $response->Result[0];
                    $error_desc = $response->ResultExplanation[0];
                    if ($order->get_status() != $dpo->successful_status) {
                        $order->update_status( 'failed');
                        $order->add_order_note('Payment Failed: ' . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ');
                    } elseif ($order->get_status() == $dpo->successful_status) {
                        $order->payment_complete();
                    }
                }
            }
        }
    }

    public static function dpo_add_order_meta_box_action($actions)
    {
        global $theorder;

        $payment_method = $theorder->get_payment_method();
        if ($payment_method == 'woocommerce_dpo') {
            $actions['do_dpo_cron'] = __('Run DPO Cron job', 'woocommerce_dpo');
        }

        return $actions;
    }

    protected static function dpo_order_query_cron_query()
    {
        global $wpdb;

        $query = <<<QUERY
SELECT ID FROM `{$wpdb->prefix}posts`
INNER JOIN `{$wpdb->prefix}postmeta` ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
WHERE meta_key = '_payment_method'
AND meta_value = 'woocommerce_dpo'
AND post_status = 'wc-pending'
QUERY;

        return $wpdb->get_results($query);
    }
}
