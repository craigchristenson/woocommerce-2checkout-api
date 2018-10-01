<?php
/*
  Plugin Name: 2Checkout PayPal Direct Gateway
  Plugin URI:
  Description: Allows you to use 2Checkout PayPal Direct payment method with the WooCommerce plugin.
  Version: 0.0.2
  Author: Craig Christenson
  Author URI: https://www.2checkout.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_twocheckoutpp', 0);

function woocommerce_twocheckoutpp(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_Twocheckoutpp'))
        return;

    class WC_Gateway_Twocheckoutpp extends WC_Payment_Gateway{

        // Logging
        public static $log_enabled = false;
        public static $log = false;

        public function __construct(){

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'twocheckoutpp';
            $this->icon = apply_filters('woocommerce_twocheckoutpp_icon', ''.$plugin_dir.'paypal.png');
            $this->has_fields = true;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->seller_id = $this->get_option('seller_id');
            $this->secret_word = $this->get_option('secret_word');
            $this->description = $this->get_option('description');
            $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Twocheckoutpp', home_url( '/' ) ) );
            $this->debug = $this->get_option('debug');

            self::$log_enabled = $this->debug;

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_twocheckoutpp', array( $this, 'check_ipn_response' ) );

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
                self::$log->add( 'twocheckoutpp', $message );
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_twocheckoutpp_supported_currencies', array('AUD', 'CAD','USD','EUR','GBP') ) ) ) return false;

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
            <h3><?php _e( '2Checkout PayPal', 'woocommerce' ); ?></h3>
            <p><?php _e( '2Checkout - Paypal', 'woocommerce' ); ?></p>

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
                    'label' => __( 'Enable 2Checkout PayPal', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'PayPal', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay with PayPal', 'woocommerce' )
                ),
                'seller_id' => array(
                    'title' => __( 'Seller ID', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter your 2Checkout account number.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'secret_word' => array(
                    'title' => __( 'Secret Word', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter your 2Checkout Secret Word.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                                'debug' => array(
                                    'title'       => __( 'Debug Log', 'woocommerce' ),
                                    'type'        => 'checkbox',
                                    'label'       => __( 'Enable logging', 'woocommerce' ),
                                    'default'     => 'no',
                                    'description' => sprintf( __( 'Log 2Checkout events', 'woocommerce' ), wc_get_log_file_path( 'twocheckoutpp' ) )
                                )
            );

        }

        /**
         * Get 2Checkout Args
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_twocheckout_args( $order ) {
            global $woocommerce;

            $order_id = $order->id;

            if ( 'yes' == $this->debug )
                $this->log('Generating payment form for order ' . $order->get_order_number());

            $twocheckout_args = array();

            $twocheckout_args['sid']                = $this->seller_id;
            $twocheckout_args['paypal_direct']      = 'Y';
            $twocheckout_args['cart_order_id']      = $order_id;
            $twocheckout_args['merchant_order_id']  = $order_id;
            $twocheckout_args['total']              = $order->get_total();
            $twocheckout_args['return_url']         = $order->get_cancel_order_url();
            $twocheckout_args['x_receipt_link_url'] = $this->notify_url;
            $twocheckout_args['currency_code']      = get_woocommerce_currency();
            $twocheckout_args['card_holder_name']   = $order->billing_first_name . ' ' . $order->billing_last_name;
            $twocheckout_args['street_address']     = $order->billing_address_1;
            $twocheckout_args['street_address2']    = $order->billing_address_2;
            $twocheckout_args['city']               = $order->billing_city;
            $twocheckout_args['state']              = $order->billing_state;
            $twocheckout_args['country']            = $order->billing_country;
            $twocheckout_args['zip']                = $order->billing_postcode;
            $twocheckout_args['phone']              = $order->billing_phone;
            $twocheckout_args['email']              = $order->billing_email;

            $twocheckout_args = apply_filters( 'woocommerce_twocheckoutpp_args', $twocheckout_args );

            return $twocheckout_args;
        }

        /**
         * Generate the twocheckout button link
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_twocheckout_form( $order_id ) {
            global $woocommerce;

            $order = new WC_Order( $order_id );

            $twocheckout_args = $this->get_twocheckout_args( $order );

            $twocheckout_args_array = array();

            foreach ($twocheckout_args as $key => $value) {
                $twocheckout_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
            }

            wc_enqueue_js( '
            jQuery("body").block({
                    message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to PayPal to make payment.', 'woocommerce' ) ) . '",
                    baseZ: 99999,
                    overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                    css: {
                        padding:        "20px",
                        zindex:         "9999999",
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:     "24px",
                    }
                });
            jQuery("#submit_twocheckout_payment_form").click();
        ' );

            return '<form action="https://www.2checkout.com/checkout/purchase" method="post" id="paypal_payment_form" target="_top">
                ' . implode( '', $twocheckout_args_array) . '
                <input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="' . __( 'Pay via PayPal', 'woocommerce' ) . '" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'woocommerce' ).'</a>
            </form>';

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

            return array(
                'result'    => 'success',
                'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
            );

        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page( $order ) {

            echo '<p>'.__( 'Thank you for your order, please click the button below to pay with PayPal.', 'woocommerce' ).'</p>';

            echo $this->generate_twocheckout_form( $order );

        }

        /**
         * Check for 2Checkout Response
         *
         * @access public
         * @return void
         */
        function check_ipn_response() {
            global $woocommerce;
            @ob_clean();

            $wc_order_id    = $_REQUEST['merchant_order_id'];

            $compare_string = $this->secret_word . $this->seller_id . $_REQUEST['order_number'] . $_REQUEST['total'];
            $compare_hash1 = strtoupper(md5($compare_string));
            $compare_hash2 = $_REQUEST['key'];
            if ($compare_hash1 != $compare_hash2) {
                wp_die( "2Checkout Hash Mismatch... check your secret word." );
            } else {
                $wc_order   = new WC_Order( absint( $wc_order_id ) );

                // Mark order complete
                $wc_order->payment_complete();

                // Empty cart and clear session
                $woocommerce->cart->empty_cart();

                wp_redirect( $this->get_return_url( $wc_order ) );
                exit;
            }

        }

    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_twocheckoutpp_gateway($methods){
        $methods[] = 'WC_Gateway_Twocheckoutpp';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_twocheckoutpp_gateway');

}
