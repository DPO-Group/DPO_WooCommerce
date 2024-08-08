<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Dpo\Common\Dpo;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$f = dirname(__DIR__);
require_once 'WCGatewayDpoCron.php';
require_once "$f/vendor/autoload.php";

/**
 * DPO Pay Gateway
 *
 * Provides a DPO Pay Payment Gateway.
 *
 * @class       woocommerce_paygate
 * @package     WooCommerce
 * @category    Payment Gateways
 * @author      DPO Group
 *
 */
class WCGatewayDPO extends WC_Payment_Gateway
{

    public const VERSION_DPO = '1.1.6';

    protected const LOGGING = 'logging';

    public const DPO_GROUP = 'DPO Pay';

    public const TXN_MSG = 'The transaction paid successfully and waiting for approval. 
    Notice that the stock will NOT reduced automatically. ';

    public const ORDER_APPROVAL_MSG = 'The transaction was paid successfully and is waiting for approval.';

    public const ORDER_APPROVED = 'The transaction was paid successfully and the order approved.';

    public const TXN_FAILED_MSG = 'Payment Failed:';
    protected static bool $logging = false;
    protected static WC_Logger_Interface|bool|null|WC_Logger $logger = false;
    public array $dpoIconsNameList = [
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
    ];
    protected string $plugin_url;
    protected string $live_company_token;
    protected string $live_default_service_type;
    protected string $successful_status;
    protected string $ptl_type;
    protected string $ptl;
    protected string $image_url;
    protected string $order_meta_service;
    protected string $order_meta_company_acc_ref;

    public function __construct()
    {
        $this->id                 = 'woocommerce_dpo';
        $this->plugin_url         = trailingslashit(plugins_url(null, dirname(__FILE__)));
        $this->has_fields         = true;
        $this->method_title       = self::DPO_GROUP;
        $this->method_description = __(
            'This payment gateway works by sending the customer to DPO Pay to complete their payment.',
            'paygate'
        );
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables in settings
        $this->enabled                    = $this->get_option('enabled');
        $this->title                      = $this->get_option('title');
        $this->description                = $this->get_option('description');
        $this->live_company_token         = $this->get_option('company_token');
        $this->live_default_service_type  = $this->get_option('default_service_type');
        $this->successful_status          = $this->get_option('successful_status');
        $this->ptl_type                   = $this->get_option('ptl_type');
        $this->ptl                        = $this->get_option('ptl');
        $this->image_url                  = $this->get_option('image_url');
        $this->order_meta_service         = $this->get_option('order_meta_service');
        $this->order_meta_company_acc_ref = $this->get_option('order_meta_company_acc_ref');

        // Load the settings
        $settings = get_option('woocommerce_woocommerce_dpo_settings', false);

        $this->icon               = ($settings['dpo_logo'] ?? '') !== "yes" ? null : $this->plugin_url . '/assets/images/dpo-pay.svg';

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            self::$logging = true;
            if (!self::$logger) {
                self::$logger = wc_get_logger();
            }
        }

        // Save options
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                [
                    &$this,
                    'process_admin_options',
                ]
            );
        } else {
            add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
        }

        /**
         * Action for the redirect response from DPO
         */
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'check_dpo_response']);
        add_action('wp_enqueue_scripts', [$this, 'add_dpo_scripts']);

        add_action('dpo_order_query_cron_admin', [WCGatewayDpoCron::class, 'dpo_order_query_cron']);
    }

    /**
     * Checks the DPO notify response
     *
     * @return void
     */
    #[NoReturn] public static function check_dpo_notify(): void
    {
        global $wpdb;

        $pushData = file_get_contents('php://input');
        if (str_contains($pushData, '<API3G>')) {
            try {
                $data = new SimpleXMLElement($pushData);
                if (!empty($data->TransactionToken) && !empty($data->CompanyRef)) {
                    // Return OK to DPO
                    echo 'OK';

                    // Get the transaction information
                    $order_id          = $data->CompanyRef;
                    $transactionToken  = $data->TransactionToken;
                    $result            = $data->Result;
                    $resultExplanation = $data->ResultExplanation;

                    $order      = wc_get_order($order_id);
                    $order_paid = $order && $order->is_paid();

                    // If order is already paid exit
                    if ($order_paid) {
                        exit;
                    }

                    $dpo              = new WCGatewayDPO();
                    $dpo_custom_table = $wpdb->prefix . 'dpo_order_data';

                    switch ($result) {
                        case '000': // Transaction Paid
                            self::updateOrderStatus($order, $dpo);
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
                                    date('Y-m-d H:i:s'),
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
                                $dpo,
                                $wpdb,
                                $dpo_custom_table
                            );
                            break;
                    }
                }
            } catch (Exception $exception) {
                self::doLogging('Exception: ' . $exception->getMessage());
            }
        }
        exit;
    }

    /**
     * Updated the order aorder status
     *
     * @param $order
     * @param $dpo
     *
     * @return void
     */
    public static function updateOrderStatus($order, $dpo): void
    {
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
                        self::ORDER_APPROVAL_MSG . ' Notice that the stock will NOT be reduced automatically. '
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
     * @param $dpo
     * @param $wpdb
     * @param $dpo_custom_table
     *
     * @return void
     */
    public static function updateQuery(
        $requestData,
        $order,
        $dpo,
        $wpdb,
        $dpo_custom_table
    ): void {
        $error_code = $requestData->result;
        $error_desc = $requestData->resultExplanation;

        if ($order->get_status() != $dpo->successful_status) {
            $order->update_status(
                'failed ',
                __(
                    self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ',
                    'woocommerce'
                )
            );
            $order->add_order_note('');
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
        } elseif ($order->get_status() == $dpo->successful_status) {
            $order->payment_complete();
        }
    }

    /**
     * Logs to the log file
     *
     * @param $message
     *
     * @return void
     */
    protected static function doLogging($message): void
    {
        if (self::$logging) {
            self::$logger->add('dpo_order', $message);
        }
    }

    /**
     * Add script for payment icons
     */
    public function add_dpo_scripts(): void
    {
        wp_enqueue_script('dpo-icon-script', $this->get_plugin_url() . '/assets/js/dpoIcons.js');
    }

    // Plugin input settings

    /**
     * @return string
     */
    public function get_plugin_url(): string
    {
        if (isset($this->plugin_url)) {
            return $this->plugin_url;
        }

        if (is_ssl()) {
            return $this->plugin_url = str_replace(
                                           'http://',
                                           'https://',
                                           WP_PLUGIN_URL
                                       ) . '/' . plugin_basename(dirname(__FILE__, 2));
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__, 2));
        }
    }

    // WooCommerce DPO Pay settings html

    /**
     * If There are no payment fields show the description if set.
     * Override this in your gateway if you have some.
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $html = new stdClass();
        parent::payment_fields();
        do_action('dpocard_solution_addfields', $html);
        if (isset($html->html)) {
            echo esc_html($html->html);
        }
    }

    /**
     * Initialise settings form fields.
     *
     * Add an array of fields to be displayed on the gateway's settings screen.
     *
     * @return void
     * @since  1.0.0
     *
     */
    public function init_form_fields(): void
    {
        // Call the parent class's init_form_fields method
        parent::init_form_fields();

        $this->form_fields = [
            'enabled'                    => [
                'title'       => __('Enable/Disable', 'woocommerce'),
                'label'       => __('Enable DPO Pay Gateway', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'This controls whether or not this gateway is enabled within WooCommerce.',
                    'paygatedpo'
                ),
                'desc_tip'    => true,
                'default'     => 'no',
            ],
            'title'                      => [
                'title'       => __('Title', 'paygate'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'paygatedpo'),
                'desc_tip'    => false,
                'default'     => __(self::DPO_GROUP, 'woocommerce'),
            ],
            'description'                => [
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'paygatedpo'
                ),
                'default'     => 'Pay via DPO Pay',
            ],
            'company_token'              => [
                'title'       => __('Company Token', 'woocommerce'),
                'type'        => 'text',
                'description' => __('You need to receive token number from DPO Pay gateway', 'woocommerce'),
                'placeholder' => __('For Example: 57466282-EBD7-4ED5-B699-8659330A6996', 'woocommerce'),
                'desc_tip'    => true,
            ],
            'default_service_type'       => [
                'title'       => __('Default DPO Service Type', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'Insert a default service type number according to the options accepted by the DPO Pay.',
                    'woocommerce'
                ),
                'placeholder' => __('For Example: 29161', 'woocommerce'),
                'desc_tip'    => true,
            ],
            'logging'                    => [
                'title'       => __('Enable Logging', 'woocommerce'),
                'label'       => __('Enable Logging', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Enable WooCommerce Logging', 'woocommerce'),
                'desc_tip'    => true,
                'default'     => 'no',
            ],
            'ptl_type'                   => [
                'title'       => __('PTL Type ( Optional )', 'woocommerce'),
                'type'        => 'select',
                'description' => __('Define payment time limit  tag is hours or minutes.', 'woocommerce'),
                'options'     => [
                    'hours'   => __('Hours', 'woocommerce'),
                    'minutes' => __('Minutes', 'woocommerce'),
                ],
                'default'     => 'hours',
            ],
            'ptl'                        => [
                'title'       => __('PTL ( Optional )', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Define number of hours to payment time limit', 'woocommerce'),
                'desc_tip'    => true,
            ],
            'successful_status'          => [
                'title'       => __('Successful Order Status', 'woocommerce'),
                'type'        => 'select',
                'description' => __(
                    'Define order status if transaction successful. If "On Hold", stock will NOT be reduced automaticlly.',
                    'woocommerce'
                ),
                'options'     => [
                    'processing' => __('Processing', 'woocommerce'),
                    'completed'  => __('Completed', 'woocommerce'),
                    'on-hold'    => __('On Hold', 'woocommerce'),
                ],
                'default'     => 'processing',
            ],
            'order_meta_service'         => [
                'title'       => __('Add Order Meta to Service', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'Add order meta to DPO services using \',\' to seperate meta keys (e.g. SERVICE_TYPE|META_KEY1,META_KEY2).',
                    'woocommerce'
                ),
                'placeholder' => __('For Example: 29161|billing_company,custom_meta_key,', 'woocommerce'),
            ],
            'order_meta_company_acc_ref' => [
                'title'       => __('Add Order Meta to CompanyAccRef', 'woocommerce'),
                'type'        => 'text',
                'description' => __(
                    'Add order meta to DPO CompanyAccRef using \',\' to seperate meta keys (e.g. META_KEY1,META_KEY2).',
                    'woocommerce'
                ),
                'placeholder' => __('For Example: billing_company,custom_meta_key,', 'woocommerce'),
            ],
            'dpo_logo'                   => [
                'title'       => __('Enable DPO Pay Logo', 'woocommerce'),
                'label'       => __('Enable DPO Pay Logo', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Displays the DPO Pay Logo if enabled.', 'paygatedpo'),
                'desc_tip'    => true,
                'default'     => 'yes',
            ],
            'payment_icons'              => [
                'title'       => __('Include Payment Icons', 'woocommerce'),
                'type'        => 'multiselect',
                'class'       => 'wc-enhanced-select',
                'css'         => 'width: 450px;',
                'description' => __(
                    'Select the payment icons you want to display on checkout.',
                    'woocommerce'
                ),
                'default'     => '',
                'options'     => $this->getPaymentIcons(),
            ],
            'order_filter'               => [
                'title'       => __('DPO Pay order filter', 'woocommerce'),
                'label'       => __("Enable 'dpo_pay_order_create' filter (use with caution)", 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __("Enable 'dpo_pay_order_create'", 'woocommerce'),
                'desc_tip'    => true,
                'default'     => 'no',
            ]
        ];
    }

    /**
     * @return object
     */
    public function getPaymentIcons(): object
    {
        $iconsArray = [];
        foreach ($this->dpoIconsNameList as $key => $icon) {
            $iconsArray[$key] = $icon;
        }

        return (object)$iconsArray;
    }

    /**
     * Output the gateway settings screen.
     *
     * @return void
     */
    public function admin_options(): void
    {
        ?>
        <h2><?php
            _e(self::DPO_GROUP, 'woocommerce'); ?></h2>
        <table class="form-table">
            <caption>DPO Pay</caption>
            <tr>
                <th id="tableHeader">Settings</th>
            </tr>
            <?php
            $this->generate_settings_html(); ?>
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
    public function process_payment($order_id): array
    {
        // Call the parent class's process_payment method
        global $wpdb;
        $parent_response = parent::process_payment($order_id);

        $response = $this->before_payment($order_id);

        if ($response['success'] === false) {
            //show error message
            wc_add_notice(
                __(
                    'Payment error: Unable to connect to the payment gateway, please try again',
                    'woothemes'
                ),
                'error'
            );

            $responseData = [
                'result'   => 'fail',
                'redirect' => '',
            ];
        } else {
            $responseData = $this->updatePostMeta($response, $order_id);
        }

        // Merge parent response with the current response if needed
        if ($parent_response['result'] === 'fail') {
            return $parent_response;
        }

        return $responseData;
    }

    // Check the WooCommerce currency

    /**
     * Updated the post meta
     *
     * @param $response
     * @param $order_id
     *
     * @return string[]
     */
    public function updatePostMeta($response, $order_id): array
    {
        if (!empty($response)) {
            if ($response['result'] != '000') {
                // Show error message
                wc_add_notice(
                    __(
                        'Payment error code: ' . $response['success'] . ', ' . $response['error'],
                        'woothemes'
                    ),
                    'error'
                );

                return [
                    'result'   => 'fail',
                    'redirect' => '',
                ];
            }

            try {
                //Add Transaction ID
                $transRef = $response['transRef'];
                $order    = new WC_Order($order_id);
                $order->set_transaction_id($transRef);
                $order->save();

                // Add record to order - will appear as custom field in admin
                update_post_meta($order_id, 'dpo_reference', $transRef);
                update_post_meta($order_id, 'dpo_trans_token', $response['transToken']);

                // Create DPO Pay gateway payment URL
                $dpo        = new Dpo(false);
                $paymentURL = $dpo->getPayUrl() . '?ID=' . $response['transToken'];

                $responseData = [
                    'redirect' => $paymentURL,
                    'result'   => 'success',
                ];
            } catch (WC_Data_Exception $exception) {
                self::doLogging('Exception: ' . $exception->getMessage());
            }
        } else {
            $response_message = wp_strip_all_tags($response);
            // Show error message
            wc_add_notice(__('Payment error: ' . $response_message, 'woothemes'), 'error');

            $responseData = [
                'result'   => 'fail',
                'redirect' => '',
            ];
        }

        return $responseData;
    }

    /**
     * Partially creates the xml needed for token creation
     * and calls create_send_xml_request
     *
     * @param $order_id
     *
     * @return array|string
     * @throws Exception
     */
    public function before_payment($order_id): array|string
    {
        global $woocommerce;

        if (self::$logging !== false) {
            self::$logger->add('dpo_order', 'Before Payment: ');
        }

        $order = new WC_Order($order_id);

        // Get all products in the cart retrieve service type and description of the product
        $service = '';

        // The loop to get the order items which are WC_Order_Item_Product objects since WC 3+
        foreach ($order->get_items() as $item) {
            // Get the product ID
            $product_id = $item->get_product_id();

            // Get product settings
            $product_data = get_post_meta($product_id);

            // Get product details
            $single_product = wc_get_product($product_id);

            $serviceType = !empty($product_data['service_type'][0]) ? $product_data['service_type'][0] : $this->get_option(
                'default_service_type'
            );
            $serviceDesc = str_replace('&', 'and', $single_product->post->post_title);

            // Replace html with underscores as it is not allowed in XML
            $serviceDesc = str_replace(['<', '>', '/'], '_', $serviceDesc);

            // Create each product service xml
            $service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time('Y/m/d H:i') . '</ServiceDate>
                        </Service>';
        }

        $order_fields = explode('|', $this->order_meta_service); // Split order_meta_service into array

        self::doLogging('Order fields: ' . json_encode($order_fields));

        if (array_key_exists('0', $order_fields) && key_exists('1', $order_fields)) {
            // Check order_meta_service was valid
            $serviceType = $order_fields['0'];
            $serviceDesc = $order_fields['1'];
            if ($serviceType != '' && $serviceDesc != '') {
                // Split order_field_meta into array if applicable

                $order_fields_array = explode(',', $serviceDesc);
                if (key_exists('1', $order_fields_array)) {
                    // Check if multiple meta keys were supplied
                    $serviceDesc = '';
                    foreach ($order_fields_array as $order_field) {
                        $serviceDesc .= $order->get_meta($order_field) . ',';
                    }
                    $serviceDesc = substr_replace(
                        $serviceDesc,
                        '',
                        -1
                    ); // Final $serviceDesc like META_KEY1,META_KEY2
                } else {
                    $serviceDesc = $order->get_meta($serviceDesc);
                }
                $service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time('Y/m/d H:i') . '</ServiceDate>
                        </Service>';
            }
        }

        // Non-numeric values not allowed by DPO
        $phone = preg_replace(['/\+/', '/[^0-9]+/'], ['00', ''], $order->get_billing_phone());

        $companyAccRef = $this->order_meta_company_acc_ref;

        if ($companyAccRef != '') {
            $order_fields_array = explode(',', $companyAccRef); // Split order_field_meta into array if applicable
            if (key_exists('1', $order_fields_array)) {
                // Check if multiple meta keys were supplied
                $companyAccRef = '';
                foreach ($order_fields_array as $order_field) {
                    $companyAccRef .= $order->get_meta($order_field) . ',';
                }
                $companyAccRef = substr_replace(
                    $companyAccRef,
                    '',
                    -1
                ); // Final $companyAccRef like META_KEY1,META_KEY2
            } else {
                $companyAccRef = $order->get_meta($companyAccRef);
            }
        }

        $param = [
            'serviceType'       => $service !== '' ? $service : $this->get_option('default_service_type'),
            'companyToken'      => $this->get_option('company_token'),
            'companyRef'        => $order_id,
            'redirectURL'       => $this->get_return_url($order),
            'backURL'           => esc_url($order->get_cancel_order_url()),
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
            'ptl_type'          => ($this->ptl_type == 'minutes') ?: '',
            'ptl'               => (!empty($this->ptl)) ? $this->ptl : '',
            'paymentCurrency'   => $this->check_woocommerce_currency($order->get_currency()),
            'companyAccRef'     => $companyAccRef,
        ];

        if (self::$logging) {
            self::$logger->add('dpo_order', 'Params: ' . json_encode($param));
        }

        // Load the settings
        $settings = get_option('woocommerce_woocommerce_dpo_settings', false);

        if ($settings['order_filter'] === 'yes') {
            $order = apply_filters('dpo_pay_order_create', $order);
        }

        // Save payment parameters to session
        $woocommerce->session->paymentToken = $param;

        // Create xml and send request return response
        $dpo = new Dpo(false);

        return $dpo->createToken($param);
    }

    /**
     * Checks the WooCommerce currency
     *
     * @param $currency
     *
     * @return mixed|string
     */
    public function check_woocommerce_currency($currency): mixed
    {
        // Check if CFA
        if ($currency === 'CFA') {
            $currency = 'XOF';
        }
        if ($currency === 'MK') {
            $currency = 'MWK';
        }

        return $currency;
    }

    /**
     * Handles the response from DPO portal after order payment
     * Gets transaction token from GET and verifies
     * Updates orders accordingly
     *
     * @param $order_id
     */
    public function check_dpo_response($order_id): void
    {
        global $wpdb;
        $dpo_custom_table = $wpdb->prefix . 'dpo_order_data';

        $transactionToken = sanitize_text_field($_GET['TransactionToken']);
        $order            = wc_get_order($order_id);

        if (empty($transactionToken)) {
            if ($order->get_status() == $this->successful_status) {
                $order->payment_complete();
            } else {
                wp_redirect(wc_get_cart_url());
            }
            exit;
        }
        // Get verify token response from DPO Pay
        $dpo = new Dpo(false);

        $response = $dpo->verifyToken(
            [
                'companyToken' => $this->get_option('company_token'),
                'transToken'   => $transactionToken,
            ]
        );

        if (!empty($response)) {
            // Check selected order status workflow
            try {
                $response = new SimpleXMLElement($response);
            } catch (Exception $exception) {
                self::doLogging('Exception: ' . $exception->getMessage());
            }

            if ($response->Result == '000' && $order->get_id() == (int)$response->CompanyRef) {
                switch ($this->successful_status) {
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
                            __(self::ORDER_APPROVAL_MSG, 'woocommerce')
                        );
                        $order->add_order_note(self::ORDER_APPROVAL_MSG);
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
                do_action('dpo_template_redirect');
            } else {
                $this->updateResponseOrderStatus($response, $order, $wpdb, $dpo_custom_table, $transactionToken);
            }
        } else {
            wc_add_notice(
                __(
                    ' Verification error: Unable to connect to the payment gateway, please try again',
                    'woothemes'
                ),
                'error'
            );
            wp_redirect(wc_get_cart_url());
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
    public function updateResponseOrderStatus($response, $order, $wpdb, $dpo_custom_table, $transactionToken): void
    {
        $error_code = $response->Result[0];
        $error_desc = $response->ResultExplanation[0];

        if ($order->get_status() != $this->successful_status) {
            $order->update_status(
                'failed ',
                __(
                    self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ',
                    'woocommerce'
                )
            );
            $order->add_order_note('');
            wc_add_notice(
                __(self::TXN_FAILED_MSG . $error_code . ', ' . $error_desc, 'woothemes'),
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
            wp_redirect(wc_get_checkout_url());
            exit;
        } elseif ($order->get_status() == $this->successful_status) {
            $order->payment_complete();
        }
    }

    /**
     * get_icon
     *
     * Add SVG icon to checkout
     */
    public function get_icon()
    {
        // Call the parent class's get_icon method
        $parent_icon   = parent::get_icon();
        $settings      = get_option('woocommerce_woocommerce_dpo_settings', false);
        $payment_icons = $settings['payment_icons'];
        $displayIcon   = '';

        if ($payment_icons) {
            $icon = $parent_icon;
            $icon        .= '<br><div style="padding: 25px 0;" id="dpo-icon-container">';
            $dpoImageUrl = plugin_dir_url(__FILE__) . '../assets/images/';
            foreach ($this->dpoIconsNameList as $key => $dpoIconName) {
                if (in_array($key, $payment_icons)) {
                    $icon .= <<<ICON
<img src="{$dpoImageUrl}dpo-$key.png" alt="$dpoIconName"
style="width:auto !important; height: 25px !important; border: none !important; float: left !important;margin-right:5px;margin-bottom: 5px;">
ICON;
                }
            }
            $icon .= '</div><br>';

            $displayIcon = apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        }

        return $displayIcon;
    }

    /**
     * get_icon
     *
     * Add SVG icon to checkout
     */
    public function get_block_icon()
    {
        $settings      = get_option('woocommerce_woocommerce_dpo_settings', false);
        $payment_icons = $settings['payment_icons'];
        $dpoLogo       = $settings['dpo_logo'] ?? '';
        $displayIcon   = '';

        if ($payment_icons) {
            $icon = '';

            if ($dpoLogo === 'yes' || $dpoLogo === 'on') {
                $icon .= '<div style="margin-top:-25px; width:100%"><div>' . $this->get_description() . '</div>' .
                         '<div>' .
                         '<img src="' . $iconSrc = esc_url(
                                                       WC_HTTPS::force_https_url($this->icon)
                                                   ) . '" alt="' . esc_attr(
                                                       $this->get_title()
                                                   ) . '" style="width: 95px !important; height: 50px !important; border: none !important;"></div></div>';
            }

            $icon        .= '<div style="display: inline-block;" id="dpo-icon-container">';
            $dpoImageUrl = plugin_dir_url(__FILE__) . '../assets/images/';
            foreach ($this->dpoIconsNameList as $key => $dpoIconName) {
                if (in_array($key, $payment_icons)) {
                    $icon .= <<<ICON
<img src="{$dpoImageUrl}dpo-$key.png" alt="$dpoIconName"
style="width:auto !important; height: 25px !important; border: none !important; float: left !important;margin-right:5px;margin-bottom: 5px;">
ICON;
                }
            }
            $icon .= '</div><br>';

            $displayIcon = apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        }

        return $displayIcon;
    }

}
