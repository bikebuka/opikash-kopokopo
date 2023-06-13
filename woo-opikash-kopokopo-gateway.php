<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * @package Opikash Kopokopo Woocommerce
 */
/*
Plugin Name: Opikash KopoKopo Lipa Na Mpesa
Plugin URI: millerjuma.co.ke
Description: Opikash KopoKopo Lipa Na Mpesa is a woocommerce extension plugin that allows website owners to receive payment via Mpesa Paybill/Till Number. It uses KopoKopo APIs (K2-Connect) to process payments. The plugin has been developed by <a href='https://opikash.millerjuma.co.ke' target='_blank'>millerjuma.co.ke.</a>
Version: 1.0.0
Author: millerjuma.co.ke
Author URI: https://millerjuma.co.ke
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: woo-opikash-kopokopo-gateway
WC requires at least: 3.0.0
WC tested up to: 6.7
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || class_exists( 'WooCommerce' ) ) {

    add_action( 'plugins_loaded', 'init_kopokopo_class' );
//add_action('woocommerce_checkout_init','disable_billing');

    function init_kopokopo_class() {
        class WC_Opikash_KopoKopo_Gateway extends WC_Payment_Gateway {
            function __construct() {

                // Setup our default vars
                $this->id                 = 'kopokopo';
                $this->method_title       = __('Opikash(KopoKopo)', 'woocommerce');
                $this->method_description = __('Opikash KopoKopo gateway works by adding form fields on the checkout page and then sending the details to Opikash.co.ke for verification and processing. Get API keys from <a href="https://app.kopokopo.com" target="_blank">https://app.kopokopo.com</a>', 'woocommerce');
                $this->icon               = plugins_url( '/images/logo.png', __FILE__ );
                $this->has_fields         = true;
                $this->supports           = array( 'products' );

                $this->init_form_fields();
                $this->init_settings();

                // Get setting values
                $this->title       = "Lipa Na Mpesa";
                $this->description = $this->settings['description'];
                $this->enabled     = $this->settings['enabled'];
                $this->stk_push    = $this->settings['stk_push'];
                $this->sms    	   = $this->settings['sms'];
                $this->paytill     = $this->settings['paytill'];
                $this->description = $this->settings['description'];
                $this->api_user    = '';
                $this->trans_key   = '';

                $this->client_id   = $this->settings['client_id'];
                $this->client_secret	= $this->settings['client_secret'];
                $this->opa   			= $this->settings['opa'];
                $this->is_production= $this->settings['is_production'];
                $this->liveurl            = $this->settings['live_url'];
                $this->sandboxURL            = $this->settings['sandbox_url'];
                $this->callback_url            = $this->settings['callback_url'];

                // Hooks
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );

            }

            function init_form_fields() {

                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Enable/Disable', 'woocommerce' ),
                        'label'       => __( 'Enable KopoKopo', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no'
                    ),
                    'is_production' => array(
                        'title'       => __( 'Sandbox/Production', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'label'       => __( 'Enable Production', 'woocommerce' ),
                        'description' => '',
                        'default'     => "no",
                    ),

                    'stk_push' => array(
                        'title'       => __( 'Enable/Disable', 'woocommerce' ),
                        'label'       => __( 'Enable KopoKopo K2 STK Push', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Enable KopoKopo K2 STK Push', 'woocommerce' ),
                        'default'     => 'no',
                        'desc_tip'    => true
                    ),

                    'sms' => array(
                        'title'       => __( 'Enable/Disable SMS', 'woocommerce' ),
                        'label'       => __( 'Enable SMS notification', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Receive/Send SMS with your own sender ID. Contact info@paytalk.co.ke to setup your SMS account.', 'woocommerce' ),
                        'default'     => 'no',
                        //'desc_tip'    => true
                    ),

                    'paytill' => array(
                        'title'       => __( 'KopoKopo Paybill/Till Number', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => '',
                        'default'     => ''
                    ),

                    'live_url' => array(
                        'title'       => __( 'KopoKopo Live URL', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => '',
                        'default'     => ''
                    ),
                    'sandbox_url' => array(
                        'title'       => __( 'KopoKopo Sandbox URL', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => '',
                        'default'     => ''
                    ),

                    'description' => array(
                        'title'       => __( 'Description', 'woocommerce' ),
                        'type'        => 'textarea',
                        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                        'default'     => 'Pay with Till Number/Paybill via PayTalk.',
                        'desc_tip'    => true
                    ),

                    'client_id' => array(
                        'title'       => __( 'KopoKopo Client ID', 'woocommerce' ),
                        'type'        => 'password',
                        'description' => sprintf( __( 'Get your KopoKopo Client ID from KopoKopo <a href="%s" target="_blank">KopoKopo</a>', 'woocommerce' ), 'https://app.kopokopo.com/' ),
                        'default'     => ''
                    ),

                    'client_secret' => array(
                        'title'       => __( 'KopoKopo Client Secret', 'woocommerce' ),
                        'type'        => 'password',
                        'description' => sprintf( __( 'Get your client secret from KopoKopo <a href="%s" target="_blank">KopoKopo</a>', 'woocommerce' ), 'https://app.kopokopo.com/' ),
                        'default'     => '',
                        'placeholder' => ''
                    ),

                    'opa' => array(
                        'title'       => __( 'Online Payment Account', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => sprintf( __( 'Get your Online Payment Account from KopoKopo <a href="%s" target="_blank">KopoKopo</a>', 'woocommerce' ), 'https://app.kopokopo.com/' ),
                        'default'     => '',
                        'placeholder' => 'Online Payment Account starts with a K eg. K123456'
                    ),
                    'callback_url' => array(
                        'title'       => __( 'Callback URL', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => sprintf( __( 'KopoKopo Callback URL', 'woocommerce' ), 'https://app.kopokopo.com/' ),
                        'default'     => '',
                        'placeholder' => 'Must start with https://'
                    ),


                );

            }

            public function payment_fields() {
                if ($description = $this->get_description()) {
                    echo wpautop(wptexturize($description));
                }
                $stk_push = ( $this->stk_push == "yes" ) ? 'TRUE' : 'FALSE';
                $sms = ( $this->sms == "yes" ) ? 'TRUE' : 'FALSE';
                if($stk_push == "FALSE"){
                    ?>
                    <div style="max-width:300px">
                        <p class="form-row form-row form-row-wide woocommerce-validated" id="mpesa_phone_field" data-o_class="form-row form-row form-row-wide">
                            <label for="mpesa_phone" class="">Phone Number <abbr class="required" title="required">*</abbr></label>
                            <input type="text" class="input-text" name="mpesa_phone" id="mpesa_phone" placeholder="Phone Number" required />
                        </p>
                        <p class="form-row form-row form-row-wide woocommerce-validated" id="mpesa_code_field" data-o_class="form-row form-row form-row-wide">
                            <label for="mpesa_code" class="">Transaction ID <abbr class="required" title="required">*</abbr></label>
                            <input type="text" class="input-text" name="mpesa_code" id="mpesa_code" placeholder="Transaction ID" />
                        </p>
                    </div>
                    <?php
                }else{
                    ?>


                    <div style="max-width:300px">
                        <p class="form-row form-row form-row-wide woocommerce-validated" id="mpesa_phone_field" data-o_class="form-row form-row form-row-wide">
                            <label for="mpesa_phone" class="">Phone Number <abbr class="required" title="required">*</abbr></label>
                            <input type="text" class="input-text" name="mpesa_phone" id="mpesa_phone" placeholder="Phone Number" required />
                        </p>
                    </div>


                    <?php
                }
            }

            public function validate_fields() {
                if($this->stk_push == "FALSE"){
                    if ($_POST['mpesa_phone']) {
                        $success = true;
                    } else {
                        $error_message = __("The ", 'woothemes') . $this->field_title . __(" Phone Number is required", 'woothemes');
                        wc_add_notice(__('Field error: ', 'woothemes') . $error_message, 'error');
                        $success = False;
                    }

                    if ($_POST['mpesa_code']) {
                        $success = true;
                    } else {
                        $error_message = __("The ", 'woothemes') . $this->phone_title . __(" Transaction ID is required", 'woothemes');
                        wc_add_notice(__('Field error: ', 'woothemes') . $error_message, 'error');
                        $success = False;
                    }
                    return $success;

                }else{
                    if ($_POST['mpesa_phone']) {
                        $success = true;
                    } else {
                        $error_message = __("The ", 'woothemes') . $this->field_title . __(" Phone Number is required", 'woothemes');
                        wc_add_notice(__('Field error: ', 'woothemes') . $error_message, 'error');
                        $success = False;
                    }
                }
            }

            /**
             * Generate access token
             * @return mixed
             */
            private function generate_access_token()
            {
                $url =$this->is_production=='yes' ?  $this->liveurl : $this->sandboxURL;

                //remote post
                $consumer_key = trim($this->client_id);
                $consumer_secret = trim($this->client_secret);
                $authorization_url=$url."/oauth/token";
                //get access token
                $data = [
                    'grant_type' => 'client_credentials',
                    'client_id' => $consumer_key,
                    'client_secret' => $consumer_secret
                ];

                $options = [
                    CURLOPT_URL => $authorization_url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($data),
                    CURLOPT_RETURNTRANSFER => true
                ];
                $curl = curl_init();
                curl_setopt_array($curl, $options);
                $response = curl_exec($curl);
                $info = curl_getinfo($curl);

                curl_close($curl);
                //
                if ($info['http_code'] === 200) {
                    $responseData = json_decode($response, true);
                    return $responseData['access_token'];
                } else {
                        throw new Exception( __( 'Ops, We could not process your payment request at this time. ERROR CODE '.$info['http_code'], 'woocommerce' ) );
                }
            }

            /**
             * @param $order_id
             * @return array|void
             */
            public function process_payment( $order_id ) {
                global $woocommerce;
                // Get this Order's information so that we know
                // who to charge and how much
                $customer_order = new WC_Order( $order_id );
                //get order id
                $environment_url =$this->is_production=='yes' ?  $this->liveurl : $this->sandboxURL;

                $mpesa_phone    = isset($_POST['mpesa_phone']) ? ($_POST['mpesa_phone']) : '';
                $mpesa_code    = isset($_POST['mpesa_code']) ? ($_POST['mpesa_code']) : '';
                //
                $payload = [
                    "payment_channel" => "M-PESA STK Push",
                    "till_number" => $this->paytill,
                    "subscriber" => [
                        "first_name" => $customer_order->get_billing_first_name(),
                        "last_name" => $customer_order->get_billing_last_name(),
                        "phone_number" => $mpesa_phone,
                        "email" => $customer_order->get_billing_email()
                    ],
                    "amount" => [
                        "currency" => $customer_order->get_currency(),
                        "value" => $customer_order->get_total()
                    ],
                    "metadata" => [
                        "something" => "Pay Bill Online",
                        "something_else" => "Pay online"
                    ],
                    "_links" => [
                        "callback_url" => $this->callback_url,
                    ]
                ];
                //get access token
                $access_token = $this->generate_access_token();
                // Send this payload to opikash.co.ke for processing
                //
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $environment_url."/api/v1/incoming_payments",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => array(
                        "authorization: Bearer ".$access_token,
                        "content-type: application/json"
                    ),
                ));
                $response = curl_exec($curl);
                $info = curl_getinfo($curl);
                curl_close($curl);

                // Send this payload to opikash.co.ke for processing
                error_log(print_r("***********************************Order Details", true));
                error_log(print_r($response, true));
                error_log(print_r($info, true));
                error_log(print_r("Order Details***********************************", true));
                //
                if ($info['http_code'] === 201) {
                    // Payment has been successful
                    $customer_order->add_order_note( __( 'Opikash.co.ke payment completed.', 'woocommerce' ) );

                    // Mark order as Paid
                    $customer_order->payment_complete();

                    // Reduce stock levels
                    $customer_order->reduce_order_stock();

                    // Empty the cart (Very important step)
                    $woocommerce->cart->empty_cart();

                    // Redirect to thank you page
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $customer_order ),
                    );
                }
                if ($info['http_code'] === 200) {
                    // Store the transaction ID in the order's postmeta
                    if ( empty( $response['body'] ) )
                        throw new Exception( __( 'Opikash\'s Response was empty.', 'woocommerce' ) );

                    if (  $response['body'] == "no_account" )
                        throw new Exception( __( 'Make sure you are using Opikash\'s API Username and API Transaction Key. Paybill/Till Number field is also required. Go to <a href="https://developer.Opikash.co.ke" target="_blank">Opikash.co.ke Developer</a> to copy these credentials.', 'woocommerce' ) );

                    if ( $response['body'] == "used_trans" )
                        throw new Exception( __( 'Sorry, the Transaction ID you are trying to use has already been used. Please check and try again.', 'woocommerce' ) );

                    if ( is_numeric($response['body']) )
                        throw new Exception( __( 'We have detected that you have made payment less <b>Ksh'.$response['body'].'</b>. Kindly pay the balance before we can accept your order. Please make sure you have paid the balance of exactly <b>Ksh'.$response['body'].'</b> to avoid any further delays. Thank you.', 'woocommerce' ) );

                    if ( $response['body'] == "no_trans" )
                        throw new Exception( __( 'Sorry, we could not verify your payment. Please check your Phone and enter your M-Pesa Pin to complete payment.', 'woocommerce' ) );

                    if ( $response['body'] == "stk_fail" )
                        throw new Exception( __( 'Sorry, we are unable to initiate payment at this time, please try again later.', 'woocommerce' ) );
                    if ( $response['body'] == "phone_err" )
                        throw new Exception( __( 'Please enter a valid phone number.', 'woocommerce' ) );

                    // Retrieve the body's resopnse if no errors found

                    if ( ( $response['body'] == "offline") ){

                        $customer_order->add_order_note( __( 'Opikash offline Payment - Awaiting confirmation.', 'woocommerce' ) );

                        // Mark as on-hold
                        $customer_order->update_status('on-hold', __( 'Opikash offline Payment - Awaiting confirmation.', 'woocommerce' ));

                        // Reduce stock levels
                        $customer_order->reduce_order_stock();

                        // Empty the cart
                        $woocommerce->cart->empty_cart();

                        // Redirect to thank you page
                        return array(
                            'result'   => 'success',
                            'redirect' => $this->get_return_url( $customer_order ),
                        );
                    } else {
                        // Transaction was not successful
                        // Add notice to the cart
                        wc_add_notice( $response['response_reason_text'], 'error' );
                        // Add note to the order for your reference
                        $customer_order->add_order_note( 'Error: '. $response['response_reason_text'] );
                    }
                } else {
                    $user_response=json_decode($response);
                    throw new Exception( __( $user_response->error_message.'-Error Code:-'.$user_response->error_code, 'woocommerce' ) );
                }
            }

        }

        function add_init_kopokopo_opikash_class($methods) {
            $methods[] = 'WC_Opikash_KopoKopo_Gateway';
            return $methods;
        }
        add_filter('woocommerce_payment_gateways', 'add_init_kopokopo_opikash_class');
    }
}else{
    function my_error_notice() {
        ?>
        <div class="error notice">
            <p><?php _e( '<b>KopoKopo Lipa Na Mpesa Opikash gateway requires WooCommerce to be activated</b>', 'woocommerce' ); ?></p>
        </div>
        <?php
    }
    add_action( 'admin_notices', 'my_error_notice' );
}