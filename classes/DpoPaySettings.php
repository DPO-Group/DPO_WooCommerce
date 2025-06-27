<?php

/**
 * DPO Pay Gateway Settings Class
 *
 * Defines the settings form fields for the DPO Pay WooCommerce gateway.
 *
 * @package WooCommerce DPO Pay Gateway
 * @since   1.0.0
 */
class DpoPaySettings {

	/**
	 * Payment icons array.
	 *
	 * @var array
	 */
	private $payment_icons;

	/**
	 * Constructor.
	 *
	 * @param array $payment_icons array to get payment icons.
	 */
	public function __construct( $paymentIcons ) {
		$this->payment_icons = $paymentIcons;
	}

	/**
	 * Get the settings form fields.
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		return array(
			'enabled'                => array(
				'title'       => __( 'Enable/Disable', 'dpo-group-for-woocommerce' ),
				'label'       => __( 'Enable DPO Pay Gateway', 'dpo-group-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __(
					'This controls whether or not this gateway is enabled within WooCommerce.',
					'dpo-group-for-woocommerce'
				),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Title', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'This controls the title which the user sees during checkout.',
					'dpo-group-for-woocommerce'
				),
				'desc_tip'    => false,
				'default'     => __( 'DPO Pay', 'dpo-group-for-woocommerce' ),
			),
			'description'            => array(
				'title'       => __( 'Description', 'dpo-group-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __(
					'This controls the description which the user sees during checkout.',
					'dpo-group-for-woocommerce'
				),
				'default'     => 'Pay via DPO Pay',
			),
			'company_token'          => array(
				'title'       => __( 'Company Token', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'You need to receive token number from DPO Pay gateway',
					'dpo-group-for-woocommerce'
				),
				'placeholder' => __( 'For Example: 57466282-EBD7-4ED5-B699-8659330A6996', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'default_service_type'   => array(
				'title'       => __( 'Default DPO Service Type', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'Insert a default service type number according to the options accepted by the DPO Pay.',
					'dpo-group-for-woocommerce'
				),
				'placeholder' => __( 'For Example: 29161', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'logging'                => array(
				'title'       => __( 'Enable Logging', 'dpo-group-for-woocommerce' ),
				'label'       => __( 'Enable Logging', 'dpo-group-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable WooCommerce Logging', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			'ptlType'                => array(
				'title'       => __( 'PTL Type ( Optional )', 'dpo-group-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Define payment time limit tag is hours or minutes.', 'dpo-group-for-woocommerce' ),
				'options'     => array(
					'hours'   => __( 'Hours', 'dpo-group-for-woocommerce' ),
					'minutes' => __( 'Minutes', 'dpo-group-for-woocommerce' ),
				),
				'default'     => 'hours',
			),
			'ptl'                    => array(
				'title'       => __( 'PTL ( Optional )', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Define number of hours to payment time limit', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'successfulStatus'       => array(
				'title'       => __( 'Successful Order Status', 'dpo-group-for-woocommerce' ),
				'type'        => 'select',
				'description' => __(
					'Define order status if transaction successful. If "On Hold", stock will NOT be reduced automatically.',
					'dpo-group-for-woocommerce'
				),
				'options'     => array(
					'processing' => __( 'Processing', 'dpo-group-for-woocommerce' ),
					'completed'  => __( 'Completed', 'dpo-group-for-woocommerce' ),
					'on-hold'    => __( 'On Hold', 'dpo-group-for-woocommerce' ),
				),
				'default'     => 'processing',
			),
			'orderMetaService'       => array(
				'title'       => __( 'Add Order Meta to Service', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'Add order meta to DPO services using \',\' to separate meta keys (e.g. SERVICE_TYPE|META_KEY1,META_KEY2).',
					'dpo-group-for-woocommerce'
				),
				'placeholder' => __(
					'For Example: 29161|_billing_company,_custom_meta_key,',
					'dpo-group-for-woocommerce'
				),
			),
			'orderMetaCompanyAccRef' => array(
				'title'       => __( 'Add Order Meta to CompanyAccRef', 'dpo-group-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'Add order meta to DPO CompanyAccRef using \',\' to separate meta keys (e.g. META_KEY1,META_KEY2).',
					'dpo-group-for-woocommerce'
				),
				'placeholder' => __( 'For Example: _billing_company,_custom_meta_key,', 'dpo-group-for-woocommerce' ),
			),
			'dpo_logo'               => array(
				'title'       => __( 'Enable DPO Pay Logo', 'dpo-group-for-woocommerce' ),
				'label'       => __( 'Enable DPO Pay Logo', 'dpo-group-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Displays the DPO Pay Logo if enabled.', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),
			'payment_icons'          => array(
				'title'       => __( 'Include Payment Icons', 'dpo-group-for-woocommerce' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'width: 450px;',
				'description' => __(
					'Select the payment icons you want to display on checkout.',
					'dpo-group-for-woocommerce'
				),
				'default'     => '',
				'options'     => $this->payment_icons,
			),
			'order_filter'           => array(
				'title'       => __( 'DPO Pay order filter', 'dpo-group-for-woocommerce' ),
				'label'       => __(
					'Enable \'dpo_pay_order_create\' filter (use with caution)',
					'dpo-group-for-woocommerce'
				),
				'type'        => 'checkbox',
				'description' => __( 'Enable \'dpo_pay_order_create\'', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			'cronDebugMode'          => array(
				'title'       => __( 'Cron Debug Mode', 'dpo-group-for-woocommerce' ),
				'label'       => __( 'Enable cron debug mode', 'dpo-group-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Activate/deactivate cron debug mode', 'dpo-group-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			),
		);
	}
}
