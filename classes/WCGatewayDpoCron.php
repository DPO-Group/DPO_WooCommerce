<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

/**
 * DPO cron class
 *
 */
class WCGatewayDpoCron extends WCGatewayDPO
{
    public const LOGGING            = 'logging';
    public const TXN_MSG            = 'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ';
    public const ORDER_APPROVED     = 'The transaction paid successfully and order approved.';
    public const ORDER_APPROVAL_MSG = 'The transaction paid successfully and waiting for approval.';
    public const PAYMENT_FAILED     = 'Payment Failed: DPO payment failed or was cancelled. Notice that the stock is NOT reduced. ';

    /**
     * Logs to the WC log
     *
     * @param $orders
     *
     * @return void
     */
    public static function logData($orders): void
    {
        $logging = false;

        // Load the settings
        $settings = get_option('woocommerce_woocommerce_dpo_settings', false);

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            $logging = true;
        }

        $logger = wc_get_logger();

        if ($logging) {
            $logger->add('dpo_cron', 'Starting DPO cron job');
            $logger->add('dpo_cron', 'Orders: ' . json_encode($orders));
        }
    }

    /**
     * Run regularly to update orders
     */
    public static function dpo_order_query_cron(): void
    {
        $dpo = new WCGatewayDPO();

        $orders = self::dpo_order_query_cron_query();

        self::logData($orders);

        foreach ($orders as $order) {
            $order_id         = $order->ID;
            $order            = wc_get_order($order_id);
            $transactionToken = $order->get_meta('dpo_trans_token');
            // Query DPO for status
            $order = wc_get_order($order_id);

            if ($transactionToken == '') {
                // Cancelled before DPO payment tried
                $order->update_status('failed', __(self::PAYMENT_FAILED, 'woocommerce'));
                $order->add_order_note(self::PAYMENT_FAILED);
                $order->save();
                continue;
            }

            $response = $dpo->verifytoken($transactionToken);

            if ($response) {
                // Check selected order status workflow
                if ($response->Result[0] === '000' && $order->get_status() !== $dpo->successfulStatus) {
                    $statusMessage = '';
                    $orderNote     = '';

                    switch ($dpo->successfulStatus) {
                        case 'on-hold':
                            $statusMessage = __(self::TXN_MSG, 'woocommerce');
                            $order->update_status('on-hold', $statusMessage);
                            $orderNote = self::TXN_MSG;
                            break;

                        case 'completed':
                            $statusMessage = __(self::ORDER_APPROVED, 'woocommerce');
                            $order->update_status('completed', $statusMessage);
                            $order->payment_complete();
                            $orderNote = self::ORDER_APPROVED;
                            break;

                        default:
                            $statusMessage = __(self::ORDER_APPROVAL_MSG, 'woocommerce');
                            $order->update_status('processing', $statusMessage);
                            $order->payment_complete();
                            $orderNote = self::ORDER_APPROVAL_MSG;
                            break;
                    }

                    $order->add_order_note($orderNote);
                } else {
                    self::updateOrderStatusCron($response, $order, $dpo);
                }
            }
        }
    }

    /**
     * Updated the order status
     *
     * @param $response
     * @param $order
     * @param $dpo
     *
     * @return void
     */
    public static function updateOrderStatusCron($response, $order, $dpo): void
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

    /**
     * Adds order meta box action
     *
     * @param $actions
     *
     * @return mixed
     */
    public static function dpo_add_order_meta_box_action($actions): mixed
    {
        global $theorder;

        $payment_method = $theorder->get_payment_method();
        if ($payment_method == 'woocommerce_dpo') {
            $actions['do_dpo_cron'] = __('Run DPO Cron job', 'woocommerce_dpo');
        }

        return $actions;
    }

    /**
     * Queries the post table for the order
     *
     * @return array|object|stdClass[]|null
     */
    protected static function dpo_order_query_cron_query(): array|object|null
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
