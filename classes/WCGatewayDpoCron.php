<?php
/*
 * Copyright (c) 2026 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Dpo\Common\Dpo;

$f = dirname( __DIR__ );
require_once "$f/classes/DpoPayServices.php";

/**
 * DPO cron class
 */
class WCGatewayDpoCron extends WCGatewayDPO {



	public const LOGGING            = 'logging';
	public const TXN_MSG            = 'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ';
	public const ORDER_APPROVED     = 'The transaction paid successfully and order approved.';
	public const ORDER_APPROVAL_MSG = 'The transaction paid successfully and waiting for approval.';
	public const PAYMENT_FAILED     = 'Payment Failed: DPO payment failed or was cancelled. Notice that the stock is NOT reduced. ';
	public const CUTOFF_MINUTES     = 30;

	/**
	 * Run regularly to update orders
	 */
	public static function dpo_order_query_cron(): void {
		global $wpdb;
		$dpoGateway  = new WCGatewayDPO();
		$dpoServices = new DpoPayServices();

		// Load the settings
		$settings  = get_option( 'woocommerce_woocommerce_dpo_settings', array() );
		$dpoCommon = new Dpo( false );
		$gatewayId = $dpoGateway->id;

		$cutoffTime    = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$cutoffMinutes = self::CUTOFF_MINUTES;
		$cutoff        = $cutoffTime->sub( new DateInterval( "P0DT0H{$cutoffMinutes}M" ) )->getTimestamp();

		$orders = wc_get_orders(
			array(
				'post_status'    => 'wc-pending',
				'payment_method' => $gatewayId,
				'date_created'   => '<' . $cutoff,
			)
		);

		self::logData( $orders );

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$order    = wc_get_order( $order_id );

			$transactionToken = self::get_transaction_token( $wpdb, $order_id );

			if ( $transactionToken === null ) {
				// Cancelled before DPO payment tried
				$order->update_status( 'failed', __( self::PAYMENT_FAILED, 'dpo-group-for-woocommerce' ) );
				$order->add_order_note( self::PAYMENT_FAILED );
				$order->save();
				continue;
			}

			$response = $dpoCommon->verifyToken(
				array(
					'companyToken' => $settings['company_token'] ?? '',
					'transToken'   => $transactionToken,
				)
			);

			if ( $response ) {
				$response = $dpoServices->extractResponse( $response );
				// Check selected order status workflow
				if ( (string) $response->Result === '000' && $order->get_status() !== $dpoGateway->successfulStatus ) {
					$statusMessage = '';
					$orderNote     = '';
					$dpoServices->addMarkupFeeToOrder( $order, $response );

					switch ( $dpoGateway->successfulStatus ) {
						case 'on-hold':
							$statusMessage = __( self::TXN_MSG, 'dpo-group-for-woocommerce' );
							$order->update_status( 'on-hold', $statusMessage );
							$orderNote = self::TXN_MSG;
							break;

						case 'completed':
							$statusMessage = __( self::ORDER_APPROVED, 'dpo-group-for-woocommerce' );
							$order->update_status( 'completed', $statusMessage );
							$order->payment_complete();
							$orderNote = self::ORDER_APPROVED;
							break;

						default:
							$statusMessage = __( self::ORDER_APPROVAL_MSG, 'dpo-group-for-woocommerce' );
							$order->update_status( 'processing', $statusMessage );
							$order->payment_complete();
							$orderNote = self::ORDER_APPROVAL_MSG;
							break;
					}

					$order->add_order_note( $orderNote );
				} else {
					self::updateOrderStatusCron( $response, $order, $dpoGateway );
				}
			}
		}
	}

	/**
	 * Logs to the WC log
	 *
	 * @param $orders
	 *
	 * @return void
	 */
	public static function logData( $orders ): void {
		$logging = false;

		// Load the settings
		$settings = get_option( 'woocommerce_woocommerce_dpo_settings', array() );

		if ( isset( $settings[ self::LOGGING ] ) && $settings[ self::LOGGING ] === 'yes' ) {
			$logging = true;
		}

		$logger = wc_get_logger();

		if ( $logging ) {
			$logger->debug( 'Starting DPO cron job', array( 'source' => 'dpo_cron' ) );
			$order_ids = array_map(
				static fn( $order ) => $order instanceof WC_Order ? $order->get_id() : $order,
				(array) $orders
			);
			$logger->debug(
				sprintf( 'Pending orders to check: %d (IDs: %s)', count( $order_ids ), implode( ', ', $order_ids ) ),
				array( 'source' => 'dpo_cron' )
			);
		}
	}

	/**
	 * @param wpdb  $wpdb
	 * @param mixed $order_id
	 *
	 * @return string|null
	 */
	protected static function get_transaction_token( wpdb $wpdb, mixed $order_id ): ?string {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}
		$token = $order->get_meta( 'dpo_trans_token', true );

		return $token !== '' ? sanitize_text_field( $token ) : null;
	}

	/**
	 * Updated the order status
	 *
	 * @param $response
	 * @param $order
	 * @param WCGatewayDPO $dpoGateway
	 *
	 * @return void
	 */
	public static function updateOrderStatusCron( $response, $order, WCGatewayDPO $dpoGateway ): void {
		$error_code    = $response->Result[0];
		$error_desc    = $response->ResultExplanation[0];
		$currentStatus = $order->get_status();

		if ( $currentStatus !== $dpoGateway->successfulStatus ) {
			$order->update_status( 'failed' );
			$order->add_order_note(
				'Payment Failed: ' . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. '
			);
		} elseif ( $currentStatus === $dpoGateway->successfulStatus ) {
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
	public static function dpo_add_order_meta_box_action( $actions ): mixed {
		global $theorder;

		$payment_method = $theorder->get_payment_method();
		if ( $payment_method === 'woocommerce_dpo' ) {
			$actions['do_dpo_cron'] = __( 'Run DPO Cron job', 'dpo-group-for-woocommerce' );
		}

		return $actions;
	}
}
