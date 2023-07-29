<?php
require 'vendor/autoload.php';

use \Kopokopo\SDK\K2;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
//session
if (!function_exists('wc_opikash_kopokopo_session')) {
    function wc_opikash_kopokopo_session()
    {
        if (!session_id()) {
            error_log("***************************");
            error_log("session started");
            error_log("***************************");
            session_start();
        }
    }
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
                $this->api_key	= $this->settings['api_key'];
                $this->opa   			= $this->settings['opa'];
                $this->is_production= $this->settings['is_production'];
                $this->liveurl            = $this->settings['live_url'];
                $this->sandboxURL            = $this->settings['sandbox_url'];
                $this->callback_url            = $this->settings['callback_url'];

                // Hooks
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
                // Initialize the session
                add_action('init', 'wc_opikash_kopokopo_session');
                add_action( 'woocommerce_api_verify_payment', array( $this, 'verify_payment' ) );
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
                        'description' => __( 'Receive/Send SMS with your own sender ID. Contact info@millerjuma.co.ke to setup your SMS account.', 'woocommerce' ),
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
                        'default'     => 'Pay with Till Number.',
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
                    'api_key' => array(
                        'title'       => __( 'KopoKopo API KEY', 'woocommerce' ),
                        'type'        => 'password',
                        'description' => sprintf( __( 'Get your API KEY from KopoKopo <a href="%s" target="_blank">KopoKopo</a>', 'woocommerce' ), 'https://app.kopokopo.com/' ),
                        'default'     => '',
                        'placeholder' => 'Optional'
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
                // Get this Order's information so that we know
                // who to charge and how much
                $customer_order = new WC_Order( $order_id );
                //set order id to session
                wc_opikash_kopokopo_session();
                $_SESSION['order_awaiting_payment'] = $order_id;
                //get access token
                $environment_url =$this->is_production=='yes' ?  $this->liveurl : $this->sandboxURL;

                $mpesa_phone    = isset($_POST['mpesa_phone']) ? ($_POST['mpesa_phone']) : '';

                $options = [
                    'clientId' => $this->client_id,
                    'clientSecret' => $this->client_secret,
                    'apiKey' => $this->api_key,
                    'baseUrl' => $environment_url,
                ];
                $K2 = new K2($options);
                //
                $stk = $K2->StkService();

                $response = $stk->initiateIncomingPayment([
                    'paymentChannel' => 'M-PESA STK Push',
                    'tillNumber' => $this->paytill,
                    'firstName' => $customer_order->get_billing_first_name(),
                    'lastName' => $customer_order->get_billing_last_name(),
                    'phoneNumber' => $this->getMsisdn($mpesa_phone),
                    'amount' =>  round($customer_order->get_total()),
                    'currency' => 'KES',
                    'email' =>$customer_order->get_billing_email(),
                    'callbackUrl' => $this->callback_url,
                    'accessToken' => $this->generate_access_token(),
                ]);
                if($response['status'] == 'success')
                {
                    //record session
                    $this->record_session_to_db([
                        'order_id' => $order_id,
                        'phone_number' => $this->getMsisdn($mpesa_phone),
                    ]);
                    $customer_order->add_order_note( __( 'Opikash offline Payment - Awaiting confirmation.', 'woocommerce' ) );
                    // Mark as on-hold
                    $customer_order->update_status('on-hold', __( 'Opikash offline Payment - Awaiting confirmation.', 'woocommerce' ));
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $customer_order ),
                    );
                }
                else {
                    wc_add_notice( __('Payment Failed:', 'woothemes') . ' Sorry, we could not process your order at this time. '.$response['status'], 'error' );
                }
            }

            /**
             * @param string $number
             * @return string
             */
            public function getMsisdn(string $number): string
            {
                $number = trim($number);
                $number = str_replace(' ', '', $number);
                $number = str_replace('+', '', $number);

                if (substr($number, 0, 3) === '254'){

                    if (substr($number, 0, 4) === '2540')
                        $number = substr_replace($number, '', 3, 1);

                    return $number;
                }

                $number = $number[0] === '0' ? ltrim($number, 0) : $number;
                return '+254'.$number;
            }
            /**
             * @return void
             */
            public function verify_payment(): void
            {
                $response=json_decode(file_get_contents('php://input'), true);
                //log
                $data=$response['data']['attributes'];
                $record=$data['event']['resource'];
                //get session
                $session=$this->get_session_from_db($record['sender_phone_number']);
                //orderId
                $order_id = $session['order_id'];
                //check if order is paid
                if ($data['status'] == 'Success')
                {
                    $this->record_transaction($record, $order_id);
                    //complete order
                    $order = wc_get_order( $order_id );

                    $order->update_status( 'processing' );
                    wc_reduce_stock_levels( $order_id );
                    WC()->cart->empty_cart();
                    //
                    $order = wc_get_order( $order_id );
                    $order->payment_complete();
                    $order->update_status('processing', __( 'Opikash payment processing.', 'woocommerce' ));
                    //
                    $order->add_order_note(
                        sprintf(
                            __( 'Opikash payment processing with Transaction ID %s', 'woocommerce' ),
                            $data['event']['resource']['id']
                        )
                    );
                }
                else
                {
                    $order = wc_get_order( $order_id );
                    $order->update_status('failed', __( 'Opikash payment failed.', 'woocommerce' ));
                }
                //remove session
                $this->remove_session_from_db($record['sender_phone_number']);
            }
            /**
             * @param $transaction
             * @return void
             */
            public function record_transaction($transaction,$order_id): void
            {
                global $wpdb;
                $charset_collate = $wpdb->get_charset_collate();
                //get order id from url params
                global $woocommerce;

                // Retrieve the value of the session variable or fallback to $_GET value
                $table = $wpdb->prefix.'opikash_kopokopo_transactions';
                $create_ddl="CREATE TABLE $table  (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        transaction_id varchar(150) DEFAULT '' NULL,
                        order_id varchar(150) DEFAULT '' NULL,
                        sender_phone_number varchar(150) DEFAULT '' NULL,
                        origination_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                        sender_first_name varchar(150) DEFAULT '' NULL,
                        sender_middle_name varchar(150) DEFAULT '' NULL,
                        sender_last_name varchar(150) DEFAULT '' NULL,
                        till_number varchar(150) DEFAULT '' NULL,
                        reference varchar(150) DEFAULT '' NULL,
                        currency varchar(150) DEFAULT '' NULL,
                        amount varchar(100) DEFAULT 0 NULL,
                        system_name varchar(100) DEFAULT '' NULL,
                        status varchar(100) DEFAULT '' NULL,
                        PRIMARY KEY  (id)
	    ) $charset_collate;";

                $data=[
                    "order_id"           =>$order_id,
                    "transaction_id"           =>$transaction['id'],
                    "sender_phone_number" =>$transaction['sender_phone_number'],
                    "origination_time" =>$transaction['origination_time'],
                    "sender_first_name"        => $transaction['sender_first_name'],
                    "sender_middle_name"        =>$transaction['sender_middle_name'],
                    "sender_last_name"       =>$transaction['sender_last_name'],
                    "till_number"     =>$transaction['till_number'] ,
                    "reference"   =>$transaction['reference'],
                    "currency"    =>$transaction['currency'],
                    "amount"    =>$transaction['amount'],
                    "system_name"    =>$transaction['system'],
                    "status"    =>$transaction['status'],
                ];
                maybe_create_table( $table, $create_ddl );
                $format = array('%s','%d');
                $wpdb->insert($table,$data,$format);
            }

            /**
             * @param $data
             * @return void
             */
            public function record_session_to_db($data): void
            {
                global $wpdb;
                $charset_collate = $wpdb->get_charset_collate();
                $table = $wpdb->prefix.'opikash_kopokopo_sessions';
                $create_ddl="CREATE TABLE $table  (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        phone_number varchar(150) DEFAULT '' NULL,
                        order_id varchar(150) DEFAULT '' NULL,
                        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                        PRIMARY KEY  (id)
                ) $charset_collate;";

                $data=[
                    "phone_number"           =>$data['phone_number'],
                    "order_id"           =>$data['order_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                maybe_create_table( $table, $create_ddl );
                $format = array('%s','%d');
                $wpdb->insert($table,$data,$format);
            }

            /**
             * @param $phone_number
             * @return array
             */
            public function get_session_from_db($phone_number): array
            {
                global $wpdb;
                $table = $wpdb->prefix . 'opikash_kopokopo_sessions';
                $sql = $wpdb->prepare("SELECT * FROM $table WHERE phone_number = %s ORDER BY created_at DESC LIMIT 1", $phone_number);
                return $wpdb->get_row($sql, ARRAY_A);
            }
            /**
             * @param $phone_number
             * @return void
             */
            public function remove_session_from_db($phone_number): void
            {
                global $wpdb;
                $table = $wpdb->prefix.'opikash_kopokopo_sessions';
                $sql="DELETE FROM $table WHERE phone_number=$phone_number";
                $wpdb->query($sql);
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
