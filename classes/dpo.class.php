<?php
/*
 * Copyright (c) 2025 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Dpo\Common\Dpo;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$f = dirname( __DIR__ );
require_once 'WCGatewayDpoCron.php';
require_once "$f/vendor/autoload.php";
require_once "$f/classes/DpoPayServices.php";
require_once "$f/classes/DpoPaySettings.php";

/**
 * DPO Pay Gateway
 *
 * Provides a DPO Pay Payment Gateway.
 *
 * @class       woocommerce_paygate
 * @package     WooCommerce
 * @category    Payment Gateways
 * @author      DPO Group
 */
class WCGatewayDPO extends WC_Payment_Gateway {


	public const VERSION_DPO = '1.3.0';

	protected const LOGGING = 'logging';

	public const DPO_GROUP = 'DPO Pay';

	public const TXN_MSG = 'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduce automatically.';

	public const ORDER_APPROVAL_MSG = 'The transaction was paid successfully and is waiting for approval.';

	public const ORDER_APPROVED = 'The transaction was paid successfully and the order approved.';

	public const TXN_FAILED_MSG                                      = 'Payment Failed:';
	protected static bool $logging                                   = false;
	protected static WC_Logger_Interface|bool|null|WC_Logger $logger = false;
	public array $dpoIconsNameList                                   = array(
		'mastercard'     => 'Mastercard',
		'visa'           => 'Visa',
		'amex'           => 'American Express',
		'unionpay'       => 'UnionPay',
		'mpesa'          => 'M-Pesa',
		'airtelmoney'    => 'Airtel Money',
		'orangemoney'    => 'Orange Money',
		'mtnmobilemoney' => 'MTN Money',
		'vodaphonempesa' => 'Vodaphone M-Pesa',
		'tigopesa'       => 'Tigo Pesa',
		'xpay'           => 'XPay Life',
		'paypal'         => 'PayPal',
	);
	protected string $pluginUrl;
	protected string $liveCompanyToken;
	protected string $liveDefaultServiceType;
	protected string $successfulStatus;
	protected string $ptlType;
	protected string $ptl;
	protected string $imageUrl;
	protected string $orderMetaService;
	protected string $orderMetaCompanyAccRef;
	protected string $cronDebugMode;

	public function __construct() {
		$this->id                 = 'woocommerce_dpo';
		$this->pluginUrl          = trailingslashit( plugins_url( null, __DIR__ ) );
		$this->has_fields         = true;
		$this->method_title       = self::DPO_GROUP;
		$this->method_description = __(
			'This payment gateway works by sending the customer to DPO Pay to complete their payment.',
			'dpo-group-for-woocommerce'
		);
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables in settings
		$this->enabled                = $this->get_option( 'enabled' );
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->liveCompanyToken       = $this->get_option( 'company_token' );
		$this->liveDefaultServiceType = $this->get_option( 'default_service_type' );
		$this->successfulStatus       = $this->get_option( 'successfulStatus' );
		$this->ptlType                = $this->get_option( 'ptlType' );
		$this->ptl                    = $this->get_option( 'ptl' );
		$this->imageUrl               = $this->get_option( 'imageUrl' );
		$this->orderMetaService       = $this->get_option( 'orderMetaService' );
		$this->orderMetaCompanyAccRef = $this->get_option( 'orderMetaCompanyAccRef' );
		$this->cronDebugMode          = $this->get_option( 'cronDebugMode' );

		// Load the settings
		$settings = get_option( 'woocommerce_woocommerce_dpo_settings', false );

		$this->icon = ( $settings['dpo_logo'] ?? '' ) !== 'yes' ? null : $this->pluginUrl . '/assets/images/dpo-pay.svg';

		if ( isset( $settings[ self::LOGGING ] ) && $settings[ self::LOGGING ] === 'yes' ) {
			self::$logging = true;
			if ( ! self::$logger ) {
				self::$logger = wc_get_logger();
			}
		}

		// Save options
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					&$this,
					'process_admin_options',
				)
			);
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}

		/**
		 * Action for the redirect response from DPO
		 */
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'check_dpo_response' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_dpo_scripts' ) );

		add_action( 'dpo_order_query_cron_admin', array( WCGatewayDpoCron::class, 'dpo_order_query_cron' ) );
	}

	/**
	 * Initialise settings form fields.
	 *
	 * Add an array of fields to be displayed on the gateway's settings screen.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function init_form_fields(): void {
		// Call the parent class's init_form_fields method
		parent::init_form_fields();

		$icons = $this->getPaymentIcons();

		$dpoSettings = new DpoPaySettings( $icons );

		$this->form_fields = $dpoSettings->get_form_fields();
	}

	/**
	 * @return object
	 */
	public function getPaymentIcons(): object {
		$iconsArray = array();
		foreach ( $this->dpoIconsNameList as $key => $icon ) {
			$iconsArray[ $key ] = $icon;
		}

		return (object) $iconsArray;
	}

	/**
	 * Checks the DPO notify response
	 *
	 * @return void
	 */
	#[NoReturn] public static function check_dpo_notify(): void {
		global $wpdb;

		$pushData = file_get_contents( 'php://input' );
		if ( str_contains( $pushData, '<API3G>' ) ) {
			try {
				$data = new SimpleXMLElement( $pushData );
				if ( ! empty( $data->TransactionToken ) && ! empty( $data->CompanyRef ) ) {
					// Return OK to DPO
					echo 'OK';

					// Get the transaction information
					$order_id          = $data->CompanyRef;
					$transactionToken  = $data->TransactionToken;
					$result            = $data->Result;
					$resultExplanation = $data->ResultExplanation;

					$order      = wc_get_order( $order_id );
					$order_paid = $order && $order->is_paid();

					// If order is already paid exit
					if ( $order_paid ) {
						exit;
					}

					$dpoGateway       = new WCGatewayDPO();
					$dpo_custom_table = $wpdb->prefix . 'dpo_order_data';

					switch ( $result ) {
						case '000': // Transaction Paid
							self::updateOrderStatus( $order, $dpoGateway );
							// Update the DPO table
							$wpdb->query(
								$wpdb->prepare(
									"UPDATE $dpo_custom_table
                                    SET is_paid = %d,
                                        paid_by = %s,
                                        paid_at = %s
                                    WHERE reference = %s",
									1,
									'pushdata',
									gmdate( 'Y-m-d H:i:s' ),
									$transactionToken
								)
							);
							break;
						case '904': // Cancelled
						case '901': // Declined
						default:
							$requestData                    = new stdClass();
							$requestData->result            = $result;
							$requestData->resultExplanation = $resultExplanation;
							$requestData->transactionToken  = $transactionToken;
							self::updateQuery(
								$requestData,
								$order,
								$wpdb,
								$dpo_custom_table,
								$dpoGateway
							);
							break;
					}
				}
			} catch ( Exception $exception ) {
				self::doLogging( 'Exception: ' . $exception->getMessage() );
			}
		}
		exit;
	}

	/**
	 * Updated the order aorder status
	 *
	 * @param $order
	 * @param WCGatewayDPO $dpoGateway
	 *
	 * @return void
	 */
	public static function updateOrderStatus( $order, WCGatewayDPO $dpoGateway ): void {
		if ( $order->get_status() !== $dpoGateway->successfulStatus ) {
			switch ( $dpoGateway->successfulStatus ) {
				case 'on-hold':
					$order->update_status(
						'on-hold',
						__(
							self::TXN_MSG,
							'dpo-group-for-woocommerce'
						)
					);
					$order->add_order_note(
						self::ORDER_APPROVAL_MSG . ' Notice that the stock will NOT be reduced automatically. '
					);
					break;
				case 'completed':
					$order->update_status(
						'completed',
						__( self::ORDER_APPROVED, 'dpo-group-for-woocommerce' )
					);
					$order->add_order_note( self::ORDER_APPROVED );
					$order->payment_complete();
					break;
				default:
					$order->update_status(
						'processing',
						__( self::ORDER_APPROVAL_MSG, 'dpo-group-for-woocommerce' )
					);
					$order->add_order_note(
						self::ORDER_APPROVAL_MSG
					);
					$order->payment_complete();
					break;
			}
		}
	}

	/**
	 * Updated the query
	 *
	 * @param $requestData
	 * @param $order
	 * @param $wpdb
	 * @param $dpo_custom_table
	 * @param WCGatewayDPO     $dpoGateway
	 *
	 * @return void
	 */
	public static function updateQuery(
		$requestData,
		$order,
		$wpdb,
		$dpo_custom_table,
		WCGatewayDPO $dpoGateway,
	): void {
		$error_code = $requestData->result;
		$error_desc = $requestData->resultExplanation;

		if ( $order->get_status() !== $dpoGateway->successfulStatus ) {
			$order->update_status(
				'failed ',
				__(
					self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ',
					'dpo-group-for-woocommerce'
				)
			);
			$order->add_order_note( '' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $dpo_custom_table
                                    SET is_paid = %d,
                                        paid_by = %s
                                    WHERE reference = %s",
					0,
					'pushdata',
					$requestData->transactionToken
				)
			);
		} elseif ( $order->get_status() === $dpoGateway->successfulStatus ) {
			$order->payment_complete();
		}
	}

	// Plugin input settings

	/**
	 * Logs to the log file
	 *
	 * @param $message
	 *
	 * @return void
	 */
	protected static function doLogging( $message ): void {
		if ( self::$logging ) {
			self::$logger->add( 'dpo_order', $message );
		}
	}

	// WooCommerce DPO Pay settings html

	/**
	 * Add script for payment icons
	 */
	public function add_dpo_scripts(): void {
		wp_enqueue_script(
			'dpo-icon-script',
			$this->get_plugin_url() . '/assets/js/dpoIcons.js',
			array(),
			'1.3.0',
			true
		);
	}

	/**
	 * @return string
	 */
	public function get_plugin_url(): string {
		if ( isset( $this->pluginUrl ) ) {
			return $this->pluginUrl;
		}

		if ( is_ssl() ) {
			return $this->pluginUrl = str_replace(
				'http://',
				'https://',
				WP_PLUGIN_URL
			) . '/' . plugin_basename( dirname( __DIR__, 1 ) );
		} else {
			return $this->pluginUrl = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __DIR__, 1 ) );
		}
	}

	/**
	 * If There are no payment fields show the description if set.
	 * Override this in your gateway if you have some.
	 *
	 * @return void
	 */
	public function payment_fields(): void {
		$html = new stdClass();
		parent::payment_fields();
		do_action( 'dpocard_solution_addfields', $html );
		if ( isset( $html->html ) ) {
			echo esc_html( $html->html );
		}
	}

	/**
	 * Output the gateway settings screen.
	 *
	 * @return void
	 */
	public function admin_options(): void {
		?>
		<h2>
		<?php
			esc_html_e( self::DPO_GROUP, 'dpo-group-for-woocommerce' );
		?>
		</h2>
		<table class="form-table">
			<caption>DPO Pay</caption>
			<tr>
				<th id="tableHeader">Settings</th>
			</tr>
			<?php
			$this->generate_settings_html();
			?>
		</table>
		<?php
	}

	/**
	 * Start the payment process after pay button is clicked
	 *
	 * @param int $order_id
	 *
	 * @return array|string[]
	 * @throws Exception
	 */
	public function process_payment( $order_id ): array {
		// Call the parent class's process_payment method
		global $wpdb;
		$parent_response = parent::process_payment( $order_id );

		$response = $this->before_payment( $order_id );

		if ( $response['success'] === false ) {
			// show error message
			wc_add_notice(
				__(
					'Payment error: Unable to connect to the payment gateway, please try again',
					'dpo-group-for-woocommerce'
				),
				'error'
			);

			$responseData = array(
				'result'   => 'fail',
				'redirect' => '',
			);
		} else {
			$responseData = $this->updatePostMeta( $response, $order_id );
		}

		// Merge parent response with the current response if needed
		if ( $parent_response['result'] === 'fail' ) {
			return $parent_response;
		}

		return $responseData;
	}

	// Check the WooCommerce currency

	/**
	 * Partially creates the xml needed for token creation
	 * and calls create_send_xml_request
	 *
	 * @param $order_id
	 *
	 * @return array|string
	 * @throws Exception
	 */
	public function before_payment( $order_id ): array|string {
		global $woocommerce;

		if ( self::$logging !== false ) {
			self::$logger->add( 'dpo_order', 'Before Payment: ' );
		}

		$order = new WC_Order( $order_id );

		$dpoServices = new DpoPayServices();

		// Get all products in the cart retrieve service type and description of the product
		$service = '';

		// The loop to get the order items which are WC_Order_Item_Product objects since WC 3+
		foreach ( $order->get_items() as $item ) {
			// Get the product ID
			$product_id = $item->get_product_id();

			// Get product settings
			$product_data = get_post_meta( $product_id );

			// Get product details
			$single_product = wc_get_product( $product_id );

			$serviceType = ! empty( $product_data['service_type'][0] )
				? $product_data['service_type'][0]
				: $this->get_option( 'default_service_type' );

			$serviceDesc = str_replace( '&', 'and', $single_product->post->post_title );

			// Replace html with underscores as it is not allowed in XML
			$serviceDesc = str_replace( array( '<', '>', '/' ), '_', $serviceDesc );

			// Create each product service xml
			$service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time( 'Y/m/d H:i' ) . '</ServiceDate>
                        </Service>';
		}

		$order_fields = explode( '|', $this->orderMetaService ); // Split orderMetaService into array

		self::doLogging( 'Order fields: ' . json_encode( $order_fields ) );

		$service = $dpoServices->get_service( $order_fields, $order, $service );

		// Non-numeric values not allowed by DPO
		$phone = preg_replace( array( '/\+/', '/[^0-9]+/' ), array( '00', '' ), $order->get_billing_phone() );

		$companyAccRef = $this->orderMetaCompanyAccRef;

		$companyAccRef = $dpoServices->getCompanyref( $companyAccRef, $order );

		$backUrl = $dpoServices->getBackUrl( $order );

		$param = array(
			'serviceType'       => $service ?? $this->get_option( 'default_service_type' ),
			'companyToken'      => $this->get_option( 'company_token' ),
			'companyRef'        => $order_id,
			'redirectURL'       => $this->get_return_url( $order ),
			'backURL'           => $backUrl,
			'paymentAmount'     => $order->get_total(),
			'customerFirstName' => $order->get_billing_first_name(),
			'customerLastName'  => $order->get_billing_last_name(),
			'customerPhone'     => $phone,
			'customerEmail'     => $order->get_billing_email(),
			'customerAddress'   => $order->get_billing_address_1(),
			'customerCity'      => $order->get_billing_city(),
			'customerZip'       => $order->get_billing_postcode(),
			'customerCountry'   => $order->get_billing_country(),
			'customerDialCode'  => $order->get_billing_country(),
			'ptlType'           => $this->ptlType === 'minutes' ? 'minutes' : '',
			'ptl'               => $this->ptl ?? '',
			'paymentCurrency'   => $this->check_woocommerce_currency( $order->get_currency() ),
			'companyAccRef'     => $companyAccRef,
		);

		if ( self::$logging ) {
			self::$logger->add( 'dpo_order', 'Params: ' . json_encode( $param ) );
		}

		// Load the settings
		$settings = get_option( 'woocommerce_woocommerce_dpo_settings', false );

		if ( $settings['order_filter'] === 'yes' ) {
			$order = apply_filters( 'dpo_pay_order_create', $order );
		}

		// Save payment parameters to session
		$woocommerce->session->paymentToken = $param;

		// Create xml and send request return response
		$dpoCommon = new Dpo( false );

		return $dpoCommon->createToken( $param );
	}

	/**
	 * Checks the WooCommerce currency
	 *
	 * @param $currency
	 *
	 * @return mixed|string
	 */
	public function check_woocommerce_currency( $currency ): mixed {
		// Check if CFA
		if ( $currency === 'CFA' ) {
			$currency = 'XOF';
		}
		if ( $currency === 'MK' ) {
			$currency = 'MWK';
		}

		return $currency;
	}

	/**
	 * Updated the post meta
	 *
	 * @param $response
	 * @param $order_id
	 *
	 * @return string[]
	 */
	public function updatePostMeta( $response, $order_id ): array {
		if ( ! empty( $response ) ) {
			if ( $response['result'] != '000' ) {
				// Show error message
				wc_add_notice(
					__(
						'Payment error code: ' . $response['success'] . ', ' . $response['error'],
						'dpo-group-for-woocommerce'
					),
					'error'
				);

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}

			try {
				// Add Transaction ID
				$transRef = $response['transRef'];
				$order    = new WC_Order( $order_id );
				$order->set_transaction_id( $transRef );
				$order->save();

				// Add record to order - will appear as custom field in admin
				update_post_meta( $order_id, 'dpo_reference', $transRef );
				update_post_meta( $order_id, 'dpo_trans_token', $response['transToken'] );

				// Create DPO Pay gateway payment URL
				$dpoCommon  = new Dpo( false );
				$paymentURL = $dpoCommon->getPayUrl() . '?ID=' . $response['transToken'];

				$responseData = array(
					'redirect' => $paymentURL,
					'result'   => 'success',
				);
			} catch ( WC_Data_Exception $exception ) {
				self::doLogging( 'Exception: ' . $exception->getMessage() );
			}
		} else {
			$response_message = wp_strip_all_tags( $response );
			// Show error message
			wc_add_notice( __( 'Payment error: ' . $response_message, 'dpo-group-for-woocommerce' ), 'error' );

			$responseData = array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		return $responseData;
	}

	/**
	 * Handles the response from DPO portal after order payment
	 * Gets transaction token from GET and verifies
	 * Updates orders accordingly
	 *
	 * @param $order_id
	 */
	public function check_dpo_response( $order_id ): void {
		global $wpdb;
		$dpo_custom_table = $wpdb->prefix . 'dpo_order_data';

		$transactionToken = sanitize_text_field( wp_unslash( $_GET['TransactionToken'] ) );
		$order            = wc_get_order( $order_id );

		if ( $this->cronDebugMode === 'yes' ) {
			wc_add_notice(
				__( 'This is a cron debugging test, the order is still pending.', 'dpo-group-for-woocommerce' ),
				'notice'
			);
			wp_redirect( wc_get_checkout_url() );
			exit();
		}

		$this->check_transaction_token( $transactionToken, $order );

		// Get verify token response from DPO Pay
		$dpoCommon   = new Dpo( false );
		$dpoServices = new DpoPayServices();

		$response = $dpoCommon->verifyToken(
			array(
				'companyToken' => $this->get_option( 'company_token' ),
				'transToken'   => $transactionToken,
			)
		);

		if ( ! empty( $response ) ) {
			$response = $dpoServices->extractResponse( $response );

			if ( $response->Result == '000' && $order->get_id() == (int) $response->CompanyRef ) {
				$dpoServices->addMarkupFeeToOrder( $order, $response );

				switch ( $this->successfulStatus ) {
					case 'on-hold':
						$order->update_status(
							'on-hold',
							__(
								self::TXN_MSG,
								'dpo-group-for-woocommerce'
							)
						);
						$order->add_order_note(
							self::TXN_MSG
						);
						break;
					case 'completed':
						$order->update_status(
							'completed',
							__( self::ORDER_APPROVED, 'dpo-group-for-woocommerce' )
						);
						$order->add_order_note( self::ORDER_APPROVED );
						$order->add_order_note(
							'Customer Credit Type: ' . $response->CustomerCreditType->__toString()
						);
						update_post_meta(
							$order_id,
							'customer_credit_type',
							$response->CustomerCreditType->__toString()
						);
						$order->payment_complete();
						break;
					default:
						$order->update_status(
							'processing',
							__( self::ORDER_APPROVAL_MSG, 'dpo-group-for-woocommerce' )
						);
						$order->add_order_note( self::ORDER_APPROVAL_MSG );
						$order->add_order_note(
							'Customer Credit Type: ' . $response->CustomerCreditType->__toString()
						);
						update_post_meta(
							$order_id,
							'customer_credit_type',
							$response->CustomerCreditType->__toString()
						);
						$order->payment_complete();

						break;
				}
				do_action( 'dpo_template_redirect' );
			} else {
				$this->updateResponseOrderStatus( $response, $order, $wpdb, $dpo_custom_table, $transactionToken );
			}
		} else {
			wc_add_notice(
				__(
					' Verification error: Unable to connect to the payment gateway, please try again',
					'dpo-group-for-woocommerce'
				),
				'error'
			);
			wp_redirect( wc_get_cart_url() );
			exit;
		}
	}

	/**
	 * @param $transactionToken
	 * @param $order
	 * @return void
	 */
	protected function check_transaction_token( $transactionToken, $order ): void {
		if ( empty( $transactionToken ) ) {
			if ( $order->get_status() === $this->successfulStatus ) {
				$order->payment_complete();
			} else {
				wp_redirect( wc_get_cart_url() );
			}
			exit;
		}
	}

	/**
	 * Updates the response order status
	 *
	 * @param $response
	 * @param $order
	 * @param $wpdb
	 * @param $dpo_custom_table
	 * @param $transactionToken
	 *
	 * @return void
	 */
	public function updateResponseOrderStatus( $response, $order, $wpdb, $dpo_custom_table, $transactionToken ): void {
		$error_code = $response->Result[0];
		$error_desc = $response->ResultExplanation[0];

		if ( $order->get_status() !== $this->successfulStatus ) {
			$order->update_status(
				'failed ',
				__(
					self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ',
					'dpo-group-for-woocommerce'
				)
			);
			$order->add_order_note( '' );
			wc_add_notice(
				__( self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc, 'dpo-group-for-woocommerce' ),
				'error'
			);
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $dpo_custom_table
                                    (reference, record_type, record_value)
                                    VALUES ( %s, %s, %s )",
					$transactionToken,
					'order_failed',
					'failed_by_redirect'
				)
			);
			wp_redirect( wc_get_checkout_url() );
			exit;
		} elseif ( $order->get_status() === $this->successfulStatus ) {
			$order->payment_complete();
		}
	}

	/**
	 * get_icon
	 *
	 * Add SVG icon to checkout
	 */
	public function get_icon() {
		// Call the parent class's get_icon method
		$parent_icon   = parent::get_icon();
		$settings      = get_option( 'woocommerce_woocommerce_dpo_settings', false );
		$payment_icons = $settings['payment_icons'];
		$displayIcon   = '';

		if ( $payment_icons ) {
			$icon        = $parent_icon;
			$icon       .= '<br><div style="padding: 25px 0;" id="dpo-icon-container">';
			$dpoImageUrl = plugin_dir_url( __FILE__ ) . '../assets/images/';
			foreach ( $this->dpoIconsNameList as $key => $dpoIconName ) {
				if ( in_array( $key, $payment_icons ) ) {
					$icon .= <<<ICON
<img src="{$dpoImageUrl}dpo-$key.png" alt="$dpoIconName"
style="width:auto !important; height: 25px !important; border: none !important; float: left !important;margin-right:5px;margin-bottom: 5px;">
ICON;
				}
			}
			$icon .= '</div><br>';

			$displayIcon = apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		return $displayIcon;
	}

	/**
	 * get_icon
	 *
	 * Add SVG icon to checkout
	 */
	public function get_block_icon() {
		$settings      = get_option( 'woocommerce_woocommerce_dpo_settings', false );
		$payment_icons = $settings['payment_icons'];
		$dpoLogo       = $settings['dpo_logo'] ?? '';
		$displayIcon   = '';

		if ( $payment_icons ) {
			$icon = '';

			if ( $dpoLogo === 'yes' || $dpoLogo === 'on' ) {
				$icon .= '<div style="margin-top: 0; width:100%"><div>' . $this->get_description() . '</div>' .
					'<div>' .
					'<img src="' . esc_url( WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr(
						$this->get_title()
					) .
					'" style="width: 95px !important; height: 50px !important; border: none !important;"></div></div>';
			}

			$icon       .= '<div style="display: inline-block;" id="dpo-icon-container">';
			$dpoImageUrl = plugin_dir_url( __FILE__ ) . '../assets/images/';
			foreach ( $this->dpoIconsNameList as $key => $dpoIconName ) {
				if ( in_array( $key, $payment_icons ) ) {
					$icon .= <<<ICON
<img src="{$dpoImageUrl}dpo-$key.png" alt="$dpoIconName"
style="width:auto !important; height: 25px !important; border: none !important; float: left !important;margin-right:5px;margin-bottom: 5px;">
ICON;
				}
			}
			$icon .= '</div><br>';

			$displayIcon = apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		return $displayIcon;
	}
}
