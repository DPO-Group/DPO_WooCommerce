<?php

/**
 * DPO Pay Services Class
 *
 * Handles additional services for the DPO Pay WooCommerce gateway, including markup fee processing.
 *
 * @package WooCommerce DPO Pay Gateway
 */
class DpoPayServices extends WCGatewayDPO {


	/**
	 * Add a markup fee to the order based on the response.
	 *
	 * @param WC_Order                $order The WooCommerce order object.
	 * @param SimpleXMLElement|string $response The response from DPO Pay containing markup fee data.
	 * @return void
	 */
	public function addMarkupFeeToOrder( WC_Order $order, SimpleXMLElement|string $response ): void {
		$orderHasMarkupFee = ! get_post_meta( $order->get_id(), '_markup_fee_added', true );
		if ( isset( $response->MarkupFee ) && $orderHasMarkupFee ) {
			$markup_fee = floatval( $response->MarkupFee );

			// Add fee as a custom fee item
			$item = new WC_Order_Item_Fee();
			$item->set_name( 'Markup Fee' );
			$item->set_amount( $markup_fee );
			$item->set_total( $markup_fee );
			$item->set_tax_class( '' );
			$item->set_tax_status( 'none' );

			$order->add_item( $item );

			// Optional: add meta for tracking
			$order->add_order_note( 'Markup Fee of ' . wc_price( $markup_fee ) . ' added to order.' );
			update_post_meta( $order->get_id(), '_markup_fee_added', $markup_fee );
			$order->update_meta_data( 'Markup Fee', $markup_fee );

			// Recalculate totals and save
			$order->calculate_totals();
			$order->save();
		}
	}

	/**
	 * @param WC_Order $order
	 * @return string
	 */
	public function getBackUrl( WC_Order $order ): string {
		if ( $this->cronDebugMode === 'yes' ) {
			$backUrl = wc_get_checkout_url();
		} else {
			$backUrl = esc_url( $order->get_cancel_order_url() );
		}
		return $backUrl;
	}


	/**
	 * @param string   $companyAccRef
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function getCompanyref( string $companyAccRef, WC_Order $order ): string {
		if ( $companyAccRef != '' ) {
			$order_fields_array = explode( ',', $companyAccRef ); // Split order_field_meta into array if applicable
			if ( key_exists( '1', $order_fields_array ) ) {
				// Check if multiple meta keys were supplied
				$companyAccRef = '';
				foreach ( $order_fields_array as $order_field ) {
					$companyAccRef .= $order->get_meta( $order_field ) . ',';
				}
				$companyAccRef = substr_replace(
					$companyAccRef,
					'',
					-1
				); // Final $companyAccRef like META_KEY1,META_KEY2
			} else {
				$companyAccRef = $order->get_meta( $companyAccRef );
			}
		}

		return $companyAccRef;
	}

	/**
	 * @param string $response
	 * @return SimpleXMLElement|string
	 */
	public function extractResponse( string $response ): string|SimpleXMLElement {
		$result = $response;
		try {
			$result = new SimpleXMLElement( $response );
		} catch ( Exception $exception ) {
			self::doLogging( 'Exception: ' . $exception->getMessage() );
		}
		return $result;
	}


	/**
	 * @param array    $order_fields
	 * @param WC_Order $order
	 * @param string   $service
	 * @return string
	 */
	protected function get_service( array $order_fields, WC_Order $order, string $service ): string {
		if ( array_key_exists( '0', $order_fields ) && key_exists( '1', $order_fields ) ) {
			// Check orderMetaService was valid
			$serviceType = $order_fields['0'];
			$serviceDesc = $order_fields['1'];
			if ( $serviceType != '' && $serviceDesc != '' ) {
				// Split order_field_meta into array if applicable

				$order_fields_array = explode( ',', $serviceDesc );
				if ( array_key_exists( '1', $order_fields_array ) ) {
					// Concatenate multiple meta keys into a comma-separated string
					$serviceDesc = implode( ',', array_map( fn( $field ) => $order->get_meta( $field ), $order_fields_array ) );
				} else {
					$serviceDesc = $order->get_meta( $order_fields_array[0] ?? '' );
				}

				if ( ! empty( $serviceDesc ) ) {
					$service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time( 'Y/m/d H:i' ) . '</ServiceDate>
                        </Service>';
				}
			}
		}
		return $service;
	}
}
