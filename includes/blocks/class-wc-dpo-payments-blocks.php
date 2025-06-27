<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Dpo Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_Dpo_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'dpo';
	/**
	 * The gateway instance.
	 *
	 * @var WCGatewayDPO
	 */
	private WCGatewayDPO $gateway;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_dpo_settings', array() );
		$this->gateway  = new WCGatewayDPO();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// Call the parent class's is_active method if it exists
		if ( method_exists( get_parent_class( $this ), 'is_active' ) ) {
			parent::is_active();
		}

		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		// Call the parent method if it exists
		$parent_handles = parent::get_payment_method_script_handles();

		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = WC_Dpo_Payments::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => '1.3.0',
			);
		$script_url        = WC_Dpo_Payments::plugin_url() . $script_path;

		wp_register_script(
			'wc-dpo-payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'wc-dpo-payments-blocks',
				'dpo-group-for-woocommerce',
				WC_Dpo_Payments::plugin_abspath() . 'languages/'
			);
		}

		// Return a merged array of parent handles and the new script handle
		return array_merge( $parent_handles, array( 'wc-dpo-payments-blocks' ) );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data(): array {
		// Call the parent method and get its data
		$parent_data = parent::get_payment_method_data();

		$child_data = array(
			'title'       => $this->gateway->get_option( 'title' ) != null ? $this->gateway->get_option(
				'title'
			) : $this->get_setting( 'title' ),
			'description' => $this->gateway->get_option( 'description' ) != '' ? $this->gateway->get_option(
				'description'
			) : $this->get_setting( 'description' ),
			'button_text' => $this->gateway->get_option( 'button_text' ) != '' ? $this->gateway->get_option(
				'button_text'
			) : 'Pay Now',
			'icons'       => $this->gateway->get_block_icon(),
			'pluginurl'   => trailingslashit( plugins_url( null, __DIR__ ) ) . '../assets/images/',
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'logo_url'    => WC_Dpo_Payments::plugin_url() . '/assets/images/dpo-pay.svg',
		);

		// Merge parent data with child data
		return array_merge( $parent_data, $child_data );
	}
}
