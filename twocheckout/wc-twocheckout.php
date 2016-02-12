<?php
/*
  Plugin Name: 2Checkout Payment Gateway
  Plugin URI:
  Description: Allows you to use 2Checkout payment gateway with the WooCommerce plugin.
  Version: 0.0.2
  Author: Craig Christenson
  Author URI: https://www.2checkout.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_twocheckout', 0);

function woocommerce_twocheckout(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_Twocheckout'))
        return;

    class WC_Gateway_Twocheckout extends WC_Payment_Gateway{

        // Logging
        public static $log_enabled = false;
        public static $log = false;

        public function __construct(){

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'twocheckout';
            $this->icon = apply_filters('woocommerce_twocheckout_icon', ''.$plugin_dir.'twocheckout.png');
            $this->has_fields = true;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->seller_id = $this->get_option('seller_id');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->private_key = $this->get_option('private_key');
            $this->description = $this->get_option('description');
            $this->sandbox = $this->get_option('sandbox');
            $this->debug = $this->get_option('debug');

            self::$log_enabled = $this->debug;

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use()){
                $this->enabled = false;
            }
        }

        /**
        * Logging method
        * @param  string $message
        */
        public static function log( $message ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = new WC_Logger();
                }
                self::$log->add( 'twocheckout', $message );
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
          $supported_currencies = array(
            'AFN', 'ALL', 'DZD', 'ARS', 'AUD', 'AZN', 'BSD', 'BDT', 'BBD',
            'BZD', 'BMD', 'BOB', 'BWP', 'BRL', 'GBP', 'BND', 'BGN', 'CAD',
            'CLP', 'CNY', 'COP', 'CRC', 'HRK', 'CZK', 'DKK', 'DOP', 'XCD',
            'EGP', 'EUR', 'FJD', 'GTQ', 'HKD', 'HNL', 'HUF', 'INR', 'IDR',
            'ILS', 'JMD', 'JPY', 'KZT', 'KES', 'LAK', 'MMK', 'LBP', 'LRD',
            'MOP', 'MYR', 'MVR', 'MRO', 'MUR', 'MXN', 'MAD', 'NPR', 'TWD',
            'NZD', 'NIO', 'NOK', 'PKR', 'PGK', 'PEN', 'PHP', 'PLN', 'QAR',
            'RON', 'RUB', 'WST', 'SAR', 'SCR', 'SGF', 'SBD', 'ZAR', 'KRW',
            'LKR', 'SEK', 'CHF', 'SYP', 'THB', 'TOP', 'TTD', 'TRY', 'UAH',
            'AED', 'USD', 'VUV', 'VND', 'XOF', 'YER');

            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_twocheckout_supported_currencies', $supported_currencies ) ) ) return false;

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( '2Checkout', 'woocommerce' ); ?></h3>
            <p><?php _e( '2Checkout - Credit Card/Paypal', 'woocommerce' ); ?></p>

            <?php if ( $this->is_valid_for_use() ) : ?>

                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table><!--/.form-table-->

            <?php else : ?>
                <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( '2Checkout does not support your store currency.', 'woocommerce' ); ?></p></div>
            <?php
            endif;
        }


        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable 2Checkout', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Credit Card/PayPal', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay with Credit Card/PayPal', 'woocommerce' )
                ),
                'seller_id' => array(
                    'title' => __( 'Seller ID', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter your 2Checkout account number; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'publishable_key' => array(
                    'title' => __( 'Publishable Key', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter your 2Checkout Publishable Key; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'private_key' => array(
                    'title' => __( 'Private Key', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter your 2Checkout Private Key; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'sandbox' => array(
                    'title' => __( 'Sandbox/Production', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Use 2Checkout Sandbox', 'woocommerce' ),
                    'default' => 'no'
                ),
                                'debug' => array(
                                    'title'       => __( 'Debug Log', 'woocommerce' ),
                                    'type'        => 'checkbox',
                                    'label'       => __( 'Enable logging', 'woocommerce' ),
                                    'default'     => 'no',
                                    'description' => sprintf( __( 'Log 2Checkout events', 'woocommerce' ), wc_get_log_file_path( 'twocheckout' ) )
                                )
            );

        }

        /**
         * Generate the credit card payment form
         *
         * @access public
         * @param none
         * @return string
         */
        function payment_fields() {
            $plugin_dir = plugin_dir_url(__FILE__);
            // Description of payment method from settings
            if ($this->description) { ?>
                <p><?php
                echo $this->description; ?>
                </p><?php
            } ?>

            <ul class="woocommerce-error" style="display:none" id="twocheckout_error_creditcard">
            <li>Credit Card details are incorrect, please try again.</li>
            </ul>

            <fieldset>

            <input id="sellerId" type="hidden" maxlength="16" width="20" value="<?php echo $this->seller_id ?>">
            <input id="publishableKey" type="hidden" width="20" value="<?php echo $this->publishable_key ?>">
            <input id="token" name="token" type="hidden" value="">

            <!-- Credit card number -->
            <p class="form-row form-row-first">
                <label for="ccNo"><?php echo __( 'Credit Card number', 'woocommerce' ) ?> <span class="required">*</span></label>
                <input type="text" class="input-text" id="ccNo" autocomplete="off" value="" />

            </p>

            <div class="clear"></div>

            <!-- Credit card expiration -->
            <p class="form-row form-row-first">
                <label for="cc-expire-month"><?php echo __( 'Expiration date', 'woocommerce') ?> <span class="required">*</span></label>
                <select id="expMonth" class="woocommerce-select woocommerce-cc-month">
                    <option value=""><?php _e( 'Month', 'woocommerce' ) ?></option><?php
                    $months = array();
                    for ( $i = 1; $i <= 12; $i ++ ) {
                        $timestamp = mktime( 0, 0, 0, $i, 1 );
                        $months[ date( 'n', $timestamp ) ] = date( 'F', $timestamp );
                    }
                    foreach ( $months as $num => $name ) {
                        printf( '<option value="%02d">%s</option>', $num, $name );
                    } ?>
                </select>
                <select id="expYear" class="woocommerce-select woocommerce-cc-year">
                    <option value=""><?php _e( 'Year', 'woocommerce' ) ?></option>
                    <?php
                    $years = array();
                    for ( $i = date( 'y' ); $i <= date( 'y' ) + 15; $i ++ ) {
                        printf( '<option value="20%u">20%u</option>', $i, $i );
                    }
                    ?>
                </select>
            </p>
            <div class="clear"></div>

            <!-- Credit card security code -->
            <p class="form-row">
            <label for="cvv"><?php _e( 'Card security code', 'woocommerce' ) ?> <span class="required">*</span></label>
            <input type="text" class="input-text" id="cvv" autocomplete="off" maxlength="4" style="width:55px" />
            <span class="help"><?php _e( '3 or 4 digits usually found on the signature strip.', 'woocommerce' ) ?></span>
            </p>

            <div class="clear"></div>

            </fieldset>

           <script type="text/javascript">
                var formName = "order_review";
                var myForm = document.getElementsByName('checkout')[0];
                if(myForm) {
                    myForm.id = "tcoCCForm";
                    formName = "tcoCCForm";
                }
                jQuery('#' + formName).on("click", function(){
                    jQuery('#place_order').unbind('click');
                    jQuery('#place_order').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                });

                function successCallback(data) {
                    clearPaymentFields();
                    jQuery('#token').val(data.response.token.token);
                    jQuery('#place_order').unbind('click');
                    jQuery('#place_order').click(function(e) {
                        return true;
                    });
                    jQuery('#place_order').click();
                }

                function errorCallback(data) {
                    if (data.errorCode === 200) {
                        TCO.requestToken(successCallback, errorCallback, formName);
                    } else if(data.errorCode == 401) {
                        clearPaymentFields();
                        jQuery('#place_order').click(function(e) {
                            e.preventDefault();
                            retrieveToken();
                        });
                        jQuery("#twocheckout_error_creditcard").show();

                    } else{
                        clearPaymentFields();
                        jQuery('#place_order').click(function(e) {
                            e.preventDefault();
                            retrieveToken();
                        });
                        alert(data.errorMsg);
                    }
                }

                var retrieveToken = function () {
                    jQuery("#twocheckout_error_creditcard").hide();
                    if (jQuery('div.payment_method_twocheckout:first').css('display') === 'block') {
                        jQuery('#ccNo').val(jQuery('#ccNo').val().replace(/[^0-9\.]+/g,''));
                        TCO.requestToken(successCallback, errorCallback, formName);
                    } else {
                        jQuery('#place_order').unbind('click');
                        jQuery('#place_order').click(function(e) {
                            return true;
                        });
                        jQuery('#place_order').click();
                    }
                }

                function clearPaymentFields() {
                    jQuery('#ccNo').val('');
                    jQuery('#cvv').val('');
                    jQuery('#expMonth').val('');
                    jQuery('#expYear').val('');
                }

            </script>

            <?php if ($this->sandbox == 'yes'): ?>
                <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/<?php echo $this->seller_id ?>"></script>
                <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/2co.js"></script>
            <?php else: ?>
                <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/<?php echo $this->seller_id ?>"></script>
                <script type="text/javascript" src="https://www.2checkout.com/checkout/api/2co.js"></script>
            <?php endif ?>
            <?php
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if ( 'yes' == $this->debug )
                $this->log( 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

            // 2Checkout Args
            $twocheckout_args = array(
                                    'token'         => $_POST['token'],
                                    'sellerId'      => $this->seller_id,
                                    'currency' => get_woocommerce_currency(),
                                    'total'         => $order->get_total(),

                                    // Order key
                                    'merchantOrderId'    => $order->get_order_number(),

                                    // Billing Address info
                                    "billingAddr" => array(
                                        'name'          => $order->billing_first_name . ' ' . $order->billing_last_name,
                                        'addrLine1'     => $order->billing_address_1,
                                        'addrLine2'     => $order->billing_address_2,
                                        'city'          => $order->billing_city,
                                        'state'         => $order->billing_state,
                                        'zipCode'       => $order->billing_postcode,
                                        'country'       => $order->billing_country,
                                        'email'         => $order->billing_email,
                                        'phoneNumber'   => $order->billing_phone
                                    )
                                );

            try {
                if ($this->sandbox == 'yes') {
                    TwocheckoutApi::setCredentials($this->seller_id, $this->private_key, 'sandbox');
                } else {
                    TwocheckoutApi::setCredentials($this->seller_id, $this->private_key);
                }
                $charge = Twocheckout_Charge::auth($twocheckout_args);
                if ($charge['response']['responseCode'] == 'APPROVED') {
                    $order->payment_complete();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
            } catch (Twocheckout_Error $e) {
                wc_add_notice($e->getMessage(), $notice_type = 'error' );
                return;
            }
        }

    }

    include plugin_dir_path(__FILE__).'Twocheckout/TwocheckoutApi.php';

    /**
     * Add the gateway to WooCommerce
     **/
    function add_twocheckout_gateway($methods){
        $methods[] = 'WC_Gateway_Twocheckout';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_twocheckout_gateway');

}
