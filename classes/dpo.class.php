<?php
/*
 * Copyright (c) 2020 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * DPO Group Gateway
 *
 * Provides a DPO Group Payment Gateway.
 *
 * @class       woocommerce_paygate
 * @package     WooCommerce
 * @category    Payment Gateways
 * @author      DPO Group
 *
 */
class WC_Gateway_DPO extends WC_Payment_Gateway
{

    const VERSION_DPO = '1.0.15';

    protected $plugin_url;
    protected $company_token;
    protected $test_company_token = '9F416C11-127B-4DE2-AC7F-D5710E4C5E0A';
    protected $live_company_token;
    protected $default_service_type;
    protected $test_default_service_type = '3854';
    protected $live_default_service_type;
    protected $successful_status;
    protected $url;
    protected $pay_url;
    protected $test_url     = 'https://secure1.sandbox.directpay.online/API/v6/';
    protected $test_pay_url = 'https://secure1.sandbox.directpay.online/payv2.php';
    protected $live_url     = 'https://secure.3gdirectpay.com/API/v6/';
    protected $live_pay_url = 'https://secure.3gdirectpay.com/payv2.php';
    protected $ptl_type;
    protected $ptl;
    protected $image_url;
    protected $order_meta_service;
    protected $order_meta_company_acc_ref;
    protected $test_mode;

    public function __construct()
    {

        $this->id                 = 'woocommerce_dpo';
        $this->plugin_url         = trailingslashit( plugins_url( null, dirname( __FILE__ ) ) );
        $this->icon               = $this->plugin_url . '/assets/images/logo.svg';
        $this->has_fields         = true;
        $this->method_title       = 'DPO Group';
        $this->method_description = __( 'This payment gateway works by sending the customer to DPO Group to complete their payment.',
            'paygate' );
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables in settings
        $dpo_url                          = $this->get_option( 'dpo_url' );
        $pay_url                          = $this->get_option( 'pay_url' );
        $this->enabled                    = $this->get_option( 'enabled' );
        $this->title                      = $this->get_option( 'title' );
        $this->description                = $this->get_option( 'description' );
        $this->live_company_token         = $this->get_option( 'company_token' );
        $this->live_default_service_type  = $this->get_option( 'default_service_type' );
        $this->successful_status          = $this->get_option( 'successful_status' );
        $this->live_url                   = $dpo_url == '' ? $this->live_url : $dpo_url;
        $this->live_pay_url               = $pay_url == '' ? $this->live_pay_url : $pay_url;
        $this->ptl_type                   = $this->get_option( 'ptl_type' );
        $this->ptl                        = $this->get_option( 'ptl' );
        $this->image_url                  = $this->get_option( 'image_url' );
        $this->order_meta_service         = $this->get_option( 'order_meta_service' );
        $this->order_meta_company_acc_ref = $this->get_option( 'order_meta_company_acc_ref' );
        $this->test_mode                  = $this->get_option( 'test_mode' );

        if ( $this->test_mode == 'yes' ) {
            $this->company_token        = $this->test_company_token;
            $this->default_service_type = $this->test_default_service_type;
            $this->url                  = $this->test_url;
            $this->pay_url              = $this->test_pay_url;
        } else {
            $this->company_token        = $this->live_company_token;
            $this->default_service_type = $this->live_default_service_type;
            $this->url                  = $this->live_url;
            // Add backwards compatibility with previous url structure
            if ( $this->url == 'https://secure.3gdirectpay.com' ) {
                $this->url .= '/API/v6/';
            }
            $this->pay_url = $this->live_pay_url;
            // Add backwards compatibility with previous url structure
            if ( $this->pay_url == 'pay.php' || $this->pay_url == 'payv2.php' ) {
                $this->pay_url = 'https://secure.3gdirectpay.com/' . $this->pay_url;
            }
        }

        // Save options
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options',
            ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }

        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'check_dpo_response' ) );

    }

    // Plugin input settings
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled'                    => array(
                'title'       => __( 'Enable/Disable', 'woocommerce' ),
                'label'       => __( 'Enable DPO Group Gateway', 'woocommerce' ),
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.',
                    'paygatedpo' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'                      => array(
                'title'       => __( 'Title', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'paygatedpo' ),
                'desc_tip'    => false,
                'default'     => __( 'DPO Payment Gateway', 'woocommerce' ),
            ),
            'description'                => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.',
                    'paygatedpo' ),
                'default'     => 'Pay via DPO Group',
            ),
            'company_token'              => array(
                'title'       => __( 'Company Token', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'You need to receive token number from DPO Group gateway', 'woocommerce' ),
                'placeholder' => __( 'For Example: 57466282-EBD7-4ED5-B699-8659330A6996', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'default_service_type'       => array(
                'title'       => __( 'Default DPO Service Type', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Insert a default service type number according to the options accepted by the DPO Group.',
                    'woocommerce' ),
                'placeholder' => __( 'For Example: 29161', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'test_mode'                  => array(
                'title'       => __( 'Enable Test Mode', 'woocommerce' ),
                'label'       => __( 'Enable Test Mode', 'woocommerce' ),
                'type'        => 'checkbox',
                'description' => __( 'Uses test accounts if enabled. No real transactions processed', 'paygatedpo' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'dpo_url'                    => array(
                'title'       => __( 'DPO Group API URL', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'The default is https://secure.3gdirectpay.com/API/v6/. You should not have to change this',
                    'woocommerce' ),
                'default'     => 'https://secure.3gdirectpay.com/API/v6/',
            ),
            'pay_url'                    => array(
                'title'       => __( 'DPO Pay URL', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'The default is https://secure.3gdirectpay.com/payv2.php. You should not have to change this',
                    'woocommerce' ),
                'default'     => 'https://secure.3gdirectpay.com/payv2.php',
            ),
            'ptl_type'                   => array(
                'title'       => __( 'PTL Type ( Optional )', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Define payment time limit  tag is hours or minutes.', 'woocommerce' ),
                'options'     => array(
                    'hours'   => __( 'Hours', 'woocommerce' ),
                    'minutes' => __( 'Minutes', 'woocommerce' ),
                ),
                'default'     => 'hours',
            ),
            'ptl'                        => array(
                'title'       => __( 'PTL ( Optional )', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Define number of hours to payment time limit', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'successful_status'          => array(
                'title'       => __( 'Successful Order Status', 'woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Define order status if transaction successful. If "On Hold", stock will NOT be reduced automaticlly.',
                    'woocommerce' ),
                'options'     => array(
                    'processing' => __( 'Processing', 'woocommerce' ),
                    'completed'  => __( 'Completed', 'woocommerce' ),
                    'on-hold'    => __( 'On Hold', 'woocommerce' ),
                ),
                'default'     => 'processing',
            ),
            'order_meta_service'         => array(
                'title'       => __( 'Add Order Meta to Service', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Add order meta to DPO services using \',\' to seperate meta keys (e.g. SERVICE_TYPE|META_KEY1,META_KEY2).',
                    'woocommerce' ),
                'placeholder' => __( 'For Example: 29161|billing_company,custom_meta_key,', 'woocommerce' ),
            ),
            'order_meta_company_acc_ref' => array(
                'title'       => __( 'Add Order Meta to CompanyAccRef', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Add order meta to DPO CompanyAccRef using \',\' to seperate meta keys (e.g. META_KEY1,META_KEY2).',
                    'woocommerce' ),
                'placeholder' => __( 'For Example: billing_company,custom_meta_key,', 'woocommerce' ),
            ),

        );
    }

    // WooCommerce DPO Group settings html
    public function admin_options()
    {
        ?>
        <h2><?php _e( 'DPO Group', 'woocommerce' );?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html();?>
        </table>
        <?php
}

    public function process_payment( $order_id )
    {

        $response = $this->before_payment( $order_id );

        if ( $response === false ) {

            //show error message
            wc_add_notice( __( 'Payment error: Unable to connect to the payment gateway, please try again',
                'woothemes' ), 'error' );

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );

        } else {

            if ( $this->is_valid_xml( $response ) ) {

                // Convert the XML result into array
                $xml = new SimpleXMLElement( $response );

                if ( $xml->Result[0] != '000' ) {

                    // Show error message
                    wc_add_notice( __( 'Payment error code: ' . $xml->Result[0] . ', ' . $xml->ResultExplanation[0],
                        'woothemes' ), 'error' );

                    return array(
                        'result'   => 'fail',
                        'redirect' => '',
                    );
                }
                // Create DPO Group gateway payment URL
                $paymentURL = $this->pay_url . "?ID=" . $xml->TransToken[0];

                return array(
                    'redirect' => $paymentURL,
                    'result'   => 'success',
                );

            } else {
                $response_message = wp_strip_all_tags( $response );
                // Show error message
                wc_add_notice( __( 'Payment error: ' . $response_message, 'woothemes' ), 'error' );
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }
        }
    }

    // Get all form details from user
    public function before_payment( $order_id )
    {

        global $woocommerce;

        $order = new WC_Order( $order_id );

        // Non-numeric values not allowed by DPO
        $phone = preg_replace( ['/\+/', '/[^0-9]+/'], ['00', ''], $order->get_billing_phone() );

        $param = array(
            'order_id'   => $order_id,
            'amount'     => '<PaymentAmount>' . $order->get_total() . '</PaymentAmount>',
            'first_name' => '<customerFirstName>' . $order->get_billing_first_name() . '</customerFirstName>',
            'last_name'  => '<customerLastName>' . $order->get_billing_last_name() . '</customerLastName>',
            'phone'      => '<customerPhone>' . $phone . '</customerPhone>',
            'email'      => '<customerEmail>' . $order->get_billing_email() . '</customerEmail>',
            'address'    => '<customerAddress>' . $order->get_billing_address_1() . '</customerAddress>',
            'city'       => '<customerCity>' . $order->get_billing_city() . '</customerCity>',
            'zipcode'    => '<customerZip>' . $order->get_billing_postcode() . '</customerZip>',
            'country'    => '<customerCountry>' . $order->get_billing_country() . '</customerCountry>',
            'ptl_type'   => ( $this->ptl_type == 'minutes' ) ? '<PTLtype>minutes</PTLtype>' : "",
            'ptl'        => ( !empty( $this->ptl ) ) ? '<PTL>' . $this->ptl . '</PTL>' : "",
            'currency'   => $this->check_woocommerce_currency( $order->get_currency() ),
        );

        // Save payment parametres to session
        $woocommerce->session->paymentToken = $param;

        // Create xml and send request return response
        $response = $this->create_send_xml_request( $param, $order, $order_id );

        return $response;
    }

    // Check the WooCommerce currency
    public function check_woocommerce_currency( $currency )
    {
        // Check if CFA
        if ( $currency === 'CFA' ) {
            $currency = 'XOF';
        }

        return $currency;
    }

    // Create xml and send by curl return response
    public function create_send_xml_request( $param, $order, $order_id )
    {

        // URL for DPO Group to send the buyer to after review and continue from DPO Group.
        $returnURL = $this->get_return_url( $order );

        // URL for DPO Group to send the buyer to if they cancel the payment.
        $cancelURL = esc_url( $order->get_cancel_order_url() );

        // Get all pruducts in the cart retrieve service type and description of the product
        $service = '';

        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );

        // The loop to get the order items which are WC_Order_Item_Product objects since WC 3+
        foreach ( $order->get_items() as $item_id => $item ) {
            // Get the product ID
            $product_id = $item->get_product_id();

            // Get product settings
            $product_data = get_post_meta( $product_id );

            // Get product details
            $single_product = new WC_Product( $product_id );

            $serviceType = !empty( $product_data["service_type"][0] ) ? $product_data["service_type"][0] : $this->default_service_type;
            $serviceDesc = preg_replace( '/&/', 'and', $single_product->post->post_title );

            // Create each product service xml
            $service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time( 'Y/m/d H:i' ) . '</ServiceDate>
                        </Service>';
        }
        // Check order_meta_service and add to services if applicable
        $order_fields = explode( '|', $this->order_meta_service ); // Split order_meta_service into array
        if ( key_exists( '0', $order_fields ) && key_exists( '1', $order_fields ) ) {
            // Check order_meta_service was valid
            $serviceType = $order_fields['0'];
            $serviceDesc = $order_fields['1'];
            if ( $serviceType != "" && $serviceDesc != "" ) {
                $order_fields_array = explode( ',', $serviceDesc ); // Split order_field_meta into array if applicable
                if ( key_exists( '1', $order_fields_array ) ) {
                    // Check if multiple meta keys were supplied
                    $serviceDesc = '';
                    foreach ( $order_fields_array as $order_field ) {
                        $serviceDesc .= get_post_meta( $order_id, $order_field, true ) . ',';
                    }
                    $serviceDesc = substr_replace( $serviceDesc, "",
                        -1 ); // Final $serviceDesc like META_KEY1,META_KEY2
                } else {
                    $serviceDesc = get_post_meta( $order_id, $serviceDesc, true );
                }
                $service .= '<Service>
                            <ServiceType>' . $serviceType . '</ServiceType>
                            <ServiceDescription>' . $serviceDesc . '</ServiceDescription>
                            <ServiceDate>' . current_time( 'Y/m/d H:i' ) . '</ServiceDate>
                        </Service>';
            }
        }

        // Check order_meta_company_acc_ref and add to companyAccRef if applicable
        $companyAccRef = $this->order_meta_company_acc_ref;
        if ( $companyAccRef != "" ) {
            $order_fields_array = explode( ',', $companyAccRef ); // Split order_field_meta into array if applicable
            if ( key_exists( '1', $order_fields_array ) ) {
                // Check if multiple meta keys were supplied
                $companyAccRef = '';
                foreach ( $order_fields_array as $order_field ) {
                    $companyAccRef .= get_post_meta( $order_id, $order_field, true ) . ',';
                }
                $companyAccRef = substr_replace( $companyAccRef, "",
                    -1 ); // Final $companyAccRef like META_KEY1,META_KEY2
            } else {
                $companyAccRef = get_post_meta( $order_id, $companyAccRef, true );
            }
            $companyAccRef = '<CompanyAccRef>' . $companyAccRef . '</CompanyAccRef>';
        }

        $input_xml = '<?xml version="1.0" encoding="utf-8"?>
                <API3G>
                    <CompanyToken>' . $this->company_token . '</CompanyToken>
                    <Request>createToken</Request>
                    <Transaction>' . $param["first_name"] .
        $param["last_name"] .
        $param["phone"] .
        $param["email"] .
        $param["address"] .
        $param["city"] .
        $param["zipcode"] .
        $param["country"] .
        $companyAccRef .
        $param["amount"] . '
                        <PaymentCurrency>' . $param["currency"] . '</PaymentCurrency>
                        <CompanyRef>' . $param["order_id"] . '</CompanyRef>
                        <RedirectURL>' . htmlspecialchars( $returnURL ) . '</RedirectURL>
                        <BackURL>' . htmlspecialchars( $cancelURL ) . '</BackURL>
                        <CompanyRefUnique>0</CompanyRefUnique>
                        ' . $param["ptl_type"] .
            $param["ptl"] . '
                    </Transaction>
                    <Services>' . $service . '</Services>
                </API3G>';

        $response = $this->createCURL( $input_xml );

        return $response;
    }

    // Generate Curl and return response
    public function createCURL( $input_xml )
    {

        $url = $this->url;

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSLVERSION, 6 );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: text/xml' ) );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $input_xml );

        $response = curl_exec( $ch );

        curl_close( $ch );

        return $response;
    }

    // Verify DPO Group response
    public function check_dpo_response( $order_id )
    {

        global $woocommerce;

        $transactionToken = $_GET['TransactionToken'];
        $order            = wc_get_order( $order_id );

        if ( empty( $transactionToken ) ) {
            if ( $order->get_status() == $this->successful_status ) {
                $order->payment_complete();
            } else {
                wp_redirect( wc_get_cart_url() );
                exit;
            }
        } else {

            // Get verify token response from DPO Group
            $response = $this->verifytoken( $transactionToken );

            if ( $response ) {
                // Check selected order status workflow
                if ( $response->Result[0] == '000' ) {
                    switch ( $this->successful_status ) {
                        case 'on-hold':
                            $order->update_status( 'on-hold',
                                __( 'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ',
                                    'woocommerce' ) );
                            $order->add_order_note( 'The transaction paid successfully and waiting for approval. Notice that the stock will NOT reduced automaticlly. ' );
                            break;
                        case 'completed':
                            $order->update_status( 'completed',
                                __( 'The transaction paid successfully and order approved.', 'woocommerce' ) );
                            $order->add_order_note( 'The transaction paid successfully and order approved.' );
                            $order->payment_complete();
                            break;
                        default:
                            $order->update_status( 'processing',
                                __( 'The transaction paid successfully and waiting for approval.', 'woocommerce' ) );
                            $order->add_order_note( 'The transaction paid successfully and waiting for approval.' );
                            $order->payment_complete();
                            break;
                    }

                } else {

                    $error_code = $response->Result[0];
                    $error_desc = $response->ResultExplanation[0];

                    if ( $order->get_status() != $this->successful_status ) {
                        $order->update_status( 'failed ',
                            __( 'Payment Failed: ' . $error_code . ', ' . $error_desc . '. Notice that the stock is NOT reduced. ',
                                'woocommerce' ) );
                        $order->add_order_note( '' );
                        wc_add_notice( __( 'Payment Failed: ' . $error_code . ', ' . $error_desc, 'woothemes' ),
                            'error' );
                        wp_redirect( WC()->cart->get_checkout_url() );
                        exit;
                    } elseif ( $order->get_status() == $this->successful_status ) {
                        $order->payment_complete();
                    }
                }
            } else {

                wc_add_notice( __( ' Verification error: Unable to connect to the payment gateway, please try again',
                    'woothemes' ), 'error' );
                wp_redirect( wc_get_cart_url() );
                exit;
            }
        }
    }

    // VerifyToken response from DPO Group
    public function verifytoken( $transactionToken )
    {

        $input_xml = '<?xml version="1.0" encoding="utf-8"?>
                    <API3G>
                      <CompanyToken>' . $this->company_token . '</CompanyToken>
                      <Request>verifyToken</Request>
                      <TransactionToken>' . $transactionToken . '</TransactionToken>
                    </API3G>';

        $response = $this->createCURL( $input_xml );

        if ( $response !== false ) {
            // Convert the XML result into array
            $xml = new SimpleXMLElement( $response );

            return $xml;
        }

        return false;
    }

    /**
     * get_icon
     *
     * Add SVG icon to checkout
     */
    public function get_icon()
    {
        $icon_url = $this->icon;
        $icon     = '<img src="' . esc_url( WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->get_title() ) . '" style="width: auto !important; height: 25px !important; border: none !important;">';

        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

    /**
     * is_valid_xml
     *
     * Check if XML is valid and correct
     */
    public function is_valid_xml( $xml )
    {
        $doc = @simplexml_load_string( $xml );
        if ( $doc ) {
            return true; // this is valid
        } else {
            return false; // this is not valid
        }
    }

}
