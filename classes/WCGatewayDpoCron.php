<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class WCGatewayDpoCron extends WCGatewayDPO
{
    const LOGGING            = 'logging';
    const TXN_MSG            = "The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ";
    const ORDER_APPROVED     = "The transaction paid successfully and order approved.";
    const ORDER_APPROVAL_MSG = "The transaction paid successfully and waiting for approval.";
    const PAYMENT_FAILED     = "Payment Failed: DPO payment failed or was cancelled. Notice that the stock is NOT reduced. ";

    public static function logData($orders)
    {
        $logging = false;

        // Load the settings
        $settings = get_option('woocommerce_woocommerce_dpo_settings', false);

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            $logging = true;
        }

        $logger = wc_get_logger();
        $logging ? $logger->add('dpo_cron', 'Starting DPO cron job') : '';

        $logging ? $logger->add('dpo_cron', 'Orders: ' . json_encode($orders)) : '';
    }

    /**
     * Run regularly to update orders
     */
    public static function dpo_order_query_cron()
    {
        $dpo = new WCGatewayDPO();

        $orders = self::dpo_order_query_cron_query();

        self::logData($orders);

        foreach ($orders as $order) {
            $order_id         = $order->ID;
            $order            = wc_get_order($order_id);
            $transactionToken = $order->get_meta('dpo_trans_token', true);
            // Query DPO for status
            $order = wc_get_order($order_id);

            if ($transactionToken == '') {
                // Cancelled before DPO payment tried
                $order->update_status(
                    'failed',
                    __(
                        self::PAYMENT_FAILED,
                        'woocommerce'
                    )
                );
                $order->add_order_note(
                    self::PAYMENT_FAILED
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
                                        self::TXN_MSG,
                                        'woocommerce'
                                    )
                                );
                                $order->add_order_note(
                                    self::TXN_MSG
                                );
                                break;
                            case 'completed':
                                $order->update_status(
                                    'completed',
                                    __(self::ORDER_APPROVED, 'woocommerce')
                                );
                                $order->add_order_note(self::ORDER_APPROVED);
                                $order->payment_complete();
                                break;
                            default:
                                $order->update_status(
                                    'processing',
                                    __(self::ORDER_APPROVAL_MSG, 'woocommerce')
                                );
                                $order->add_order_note(self::ORDER_APPROVAL_MSG);
                                $order->payment_complete();
                                break;
                        }
                    }
                } else {
                    self::updateOrderStatusCron($response, $order, $dpo);
                }
            }
        }
    }

    public static function updateOrderStatusCron($response, $order, $dpo)
    {
        $error_code = $response->Result[0];
        $error_desc = $response->ResultExplanation[0];
        if ($order->get_status() != $dpo->successful_status) {
            $order->update_status('failed');
            $order->add_order_note(
                'Payment Failed: ' . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. '
            );
        } elseif ($order->get_status() == $dpo->successful_status) {
            $order->payment_complete();
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
