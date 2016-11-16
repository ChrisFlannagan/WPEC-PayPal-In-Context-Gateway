<?php
use PayPal\CoreComponentTypes\BasicAmountType;
use PayPal\Service\PayPalAPIInterfaceServiceService;
use PayPal\EBLBaseComponents\PaymentDetailsType;
use PayPal\EBLBaseComponents\PaymentDetailsItemType;
use PayPal\EBLBaseComponents\SetExpressCheckoutRequestDetailsType;
use PayPal\PayPalAPI\SetExpressCheckoutReq;
use PayPal\PayPalAPI\SetExpressCheckoutRequestType;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsReq;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsRequestType;

class WPSC_Payment_Gateway_PayPal_In_Context extends WPSC_Payment_Gateway {
	/**
	 * Constructor of PayPal In Context Gateway
	 *
	 * @access public
	 * @since 3.9
	 */
	public $config_paypal = array();
	protected $gateway;
	public $url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

	public function __construct( $options, $child = false ) {
		parent::__construct();
		$this->title = __( 'PayPal In-Context', 'wp-e-commerce' );
		$mode = 'sandbox';
		$this->config_paypal = array (
			'mode' => $mode,
			'acct1.UserName' => esc_attr( $this->setting->get( 'api_username' ) ),
			'acct1.Password' => esc_attr( $this->setting->get( 'api_password' ) ),
			'acct1.Signature' => esc_attr( $this->setting->get( 'api_signature' ) )
		);

		require_once( 'php-merchant/gateways/paypal-express-checkout.php' );
		$this->gateway = new PHP_Merchant_Paypal_Express_Checkout( $options );
		$this->gateway->set_options( array(
			'api_username'     => $this->setting->get( 'api_username' ),
			'api_password'     => $this->setting->get( 'api_password' ),
			'api_signature'    => $this->setting->get( 'api_signature' ),
			'cancel_url'       => $this->get_shopping_cart_payment_url(),
			'currency'         => $this->get_currency_code(),
			'test'             => (bool) $this->setting->get( 'sandbox_mode' ),
			'address_override' => 1,
			'solution_type'    => 'mark'
		) );
		// Express Checkout Button
		add_action( 'wp_enqueue_scripts', array ( $this, 'load_scripts' ) );
	}

	public function load_scripts() {
		wp_register_script( 'paypalincontextform', plugin_dir_url( __FILE__ ) . 'incontext-includes/form.js' );
		wp_localize_script( 'paypalincontextform', 'wpec_ppic', array( 'mid' => esc_attr( $this->setting->get( 'api_merchantid' ) ) ) );
		wp_enqueue_script( 'paypalincontextform' );
		wp_enqueue_script( 'paypalincontext', 'http://www.paypalobjects.com/api/checkout.js', array(), null );
	}

	public function process() {
		$order = $this->purchase_log;
		require_once( 'incontext-includes/sdk/vendor/paypal/merchant-sdk-php/samples/PPBootStrap.php' );
		if ( $this->setting->get( 'sandbox_mode' ) != '1' ) {
			$mode = 'product';
			$url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
		}
		$paypalService = new PayPalAPIInterfaceServiceService( $this->config_paypal );
		$paymentDetails= new PaymentDetailsType();

		$item_cnt = 0;
		foreach ( $order->get_cart_contents() as $order_item ) {
			$itemDetails = new PaymentDetailsItemType();
			$itemDetails->Name = $order_item->prodid . ': ' . $order_item->name;
			$itemAmount = $order_item->price;
			$itemDetails->Amount = $itemAmount;
			$itemQuantity = $order_item->quantity;
			$itemDetails->Quantity = $itemQuantity;

			$paymentDetails->PaymentDetailsItem[ $item_cnt ] = $itemDetails;
			$item_cnt++;
		}

		$orderTotal = new BasicAmountType();
		$orderTotal->currencyID = $this->setting->get( 'currency', 'USD' );;
		$orderTotal->value = $order->get( 'totalprice' );

		$paymentDetails->OrderTotal = $orderTotal;
		$paymentDetails->PaymentAction = 'Sale';

		$setECReqDetails = new SetExpressCheckoutRequestDetailsType();
		$setECReqDetails->PaymentDetails[0] = $paymentDetails;
		$setECReqDetails->CancelURL = site_url() . '/store/checkout/payment/?cancel=true';
		$setECReqDetails->ReturnURL = $this->get_return_url();

		$setECReqType = new SetExpressCheckoutRequestType();
		$setECReqType->Version = '104.0';
		$setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

		$setECReq = new SetExpressCheckoutReq();
		$setECReq->SetExpressCheckoutRequest = $setECReqType;

		$setECResponse = $paypalService->SetExpressCheckout($setECReq);
		wp_redirect( $this->url. $setECResponse->Token );
		exit;
	}


	protected function get_return_url() {
		$transact_url = get_option( 'transact_url' );
		$transact_url = apply_filters( 'wpsc_paypal_express_checkout_transact_url', $transact_url );

		$location = add_query_arg( array(
			'sessionid'                => $this->purchase_log->get( 'sessionid' ),
			'payment_gateway'          => 'paypal-in-context',
			'payment_gateway_callback' => 'confirm_transaction',
		),
			$transact_url
		);
		return apply_filters( 'wpsc_paypal_express_checkout_return_url', $location, $this );
	}

	/**
	 * Confirm Transaction Callback
	 *
	 * @return bool
	 *
	 * @since 3.9
	 */
	public function callback_confirm_transaction() {
		if ( ! isset( $_REQUEST['sessionid'] ) || ! isset( $_REQUEST['token'] ) || ! isset( $_REQUEST['PayerID'] ) ) {
			return false;
		}

		// Set the Purchase Log
		$this->set_purchase_log_for_callbacks();

		// Display the Confirmation Page
		$this->do_transaction();

		// Remove Shortcut option if it exists
		$sessionid = $_REQUEST['sessionid'];
		wpsc_delete_customer_meta( 'esc-' . $sessionid );
	}

	/**
	 * Creates a new Purchase Log entry and set it to the current object
	 *
	 * @return null
	 */
	protected function set_purchase_log_for_callbacks( $sessionid = false ) {
		// Define the sessionid if it's not passed
		if ( $sessionid === false ) {
			$sessionid = $_REQUEST['sessionid'];
		}

		// Create a new Purchase Log entry
		$purchase_log = new WPSC_Purchase_Log( $sessionid, 'sessionid' );

		if ( ! $purchase_log->exists() ) {
			return null;
		}

		// Set the Purchase Log for the gateway object
		$this->set_purchase_log( $purchase_log );
	}

	/**
	 * Process the transaction through the PayPal APIs
	 *
	 * @since 3.9
	 */
	public function do_transaction() {
		$args = array_map( 'urldecode', $_GET );
		extract( $args, EXTR_SKIP );
		if ( ! isset( $sessionid ) || ! isset( $token ) || ! isset( $PayerID ) ) {
			return;
		}
		$this->set_purchase_log_for_callbacks();
		$total = $this->convert( $this->purchase_log->get( 'totalprice' ) );
		$options = array(
			'token'         => $token,
			'payer_id'      => $PayerID,
			'message_id'    => $this->purchase_log->get( 'id' ),
			'invoice'		=> $this->purchase_log->get( 'sessionid' ),
		);
		$options += $this->checkout_data->get_gateway_data();
		$options += $this->purchase_log->get_gateway_data( parent::get_currency_code(), $this->get_currency_code() );
		if ( $this->setting->get( 'ipn', false ) ) {
			$options['notify_url'] = $this->get_notify_url();
		}
		// GetExpressCheckoutDetails
		$details = $this->gateway->get_details_for( $token );
		$this->log_payer_details( $details );
		$response = $this->gateway->purchase( $options );
		$this->log_protection_status( $response );
		$location = remove_query_arg( 'payment_gateway_callback' );
		if ( $response->has_errors() ) {
			$errors = $response->get_params();
			if ( isset( $errors['L_ERRORCODE0'] ) && '10486' == $errors['L_ERRORCODE0'] ) {
				wp_redirect( $this->get_redirect_url( array( 'token' => $token ) ) );
				exit;
			}
			wpsc_update_customer_meta( 'paypal_express_checkout_errors', $response->get_errors() );
			$location = add_query_arg( array( 'payment_gateway_callback' => 'display_paypal_error' ) );
		} elseif ( $response->is_payment_completed() || $response->is_payment_pending() ) {
			$location = remove_query_arg( 'payment_gateway' );
			if ( $response->is_payment_completed() ) {
				$this->purchase_log->set( 'processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT );
			} else {
				$this->purchase_log->set( 'processed', WPSC_Purchase_Log::ORDER_RECEIVED );
			}
			$this->purchase_log->set( 'transactid', $response->get( 'transaction_id' ) )
			                   ->set( 'date', time() )
			                   ->save();
		} else {
			$location = add_query_arg( array( 'payment_gateway_callback' => 'display_generic_error' ) );
		}
		wp_redirect( esc_url_raw( $location ) );
		exit;
	}

	public function callback_display_paypal_error() {
		add_filter( 'wpsc_get_transaction_html_output', array( $this, 'filter_paypal_error_page' ) );
	}
	public function callback_display_generic_error() {
		add_filter( 'wpsc_get_transaction_html_output', array( $this, 'filter_generic_error_page' ) );
	}
	/**
	 * Records the Payer ID, Payer Status and Shipping Status to the Purchase
	 * Log on GetExpressCheckout Call
	 *
	 * @return void
	 */
	public function log_payer_details( $details ) {
		if ( isset( $details->get( 'payer' )->id ) && !empty( $details->get( 'payer' )->id ) ) {
			$payer_id = $details->get( 'payer' )->id;
		} else {
			$payer_id = 'not set';
		}
		if ( isset( $details->get( 'payer' )->status ) && !empty( $details->get( 'payer' )->status ) ) {
			$payer_status = $details->get( 'payer' )->status;
		} else {
			$payer_status = 'not set';
		}
		if ( isset( $details->get( 'payer' )->shipping_status ) && !empty( $details->get( 'payer' )->shipping_status ) ) {
			$payer_shipping_status = $details->get( 'payer' )->shipping_status;
		} else {
			$payer_shipping_status = 'not set';
		}
		$paypal_log = array(
			'payer_id'        => $payer_id,
			'payer_status'    => $payer_status,
			'shipping_status' => $payer_shipping_status,
			'protection'      => null,
		);
		wpsc_update_purchase_meta( $this->purchase_log->get( 'id' ), 'paypal_ec_details' , $paypal_log );
	}
	/**
	 * Records the Protection Eligibility status to the Purchase Log on
	 * DoExpressCheckout Call
	 *
	 * @return void
	 */
	public function log_protection_status( $response ) {
		$params = $response->get_params();
		if ( isset( $params['PAYMENTINFO_0_PROTECTIONELIGIBILITY'] ) ) {
			$elg                      = $params['PAYMENTINFO_0_PROTECTIONELIGIBILITY'];
		} else {
			$elg = false;
		}
		$paypal_log               = wpsc_get_purchase_meta( $this->purchase_log->get( 'id' ), 'paypal_ec_details', true );
		$paypal_log['protection'] = $elg;
		wpsc_update_purchase_meta( $this->purchase_log->get( 'id' ), 'paypal_ec_details' , $paypal_log );
	}
	public function callback_process_confirmed_payment() {
		$args = array_map( 'urldecode', $_GET );
		extract( $args, EXTR_SKIP );
		if ( ! isset( $sessionid ) || ! isset( $token ) || ! isset( $PayerID ) ) {
			return;
		}
		$this->set_purchase_log_for_callbacks();
		$total = $this->convert( $this->purchase_log->get( 'totalprice' ) );
		$options = array(
			'token'         => $token,
			'payer_id'      => $PayerID,
			'message_id'    => $this->purchase_log->get( 'id' ),
			'invoice'       => $this->purchase_log->get( 'sessionid' ),
		);
		$options += $this->checkout_data->get_gateway_data();
		$options += $this->purchase_log->get_gateway_data( parent::get_currency_code(), $this->get_currency_code() );
		if ( $this->setting->get( 'ipn', false ) ) {
			$options['notify_url'] = $this->get_notify_url();
		}
		// GetExpressCheckoutDetails
		$details = $this->gateway->get_details_for( $token );
		$this->log_payer_details( $details );
		$response = $this->gateway->purchase( $options );
		$this->log_protection_status( $response );
		$location = remove_query_arg( 'payment_gateway_callback' );
		if ( $response->has_errors() ) {
			wpsc_update_customer_meta( 'paypal_express_checkout_errors', $response->get_errors() );
			$location = add_query_arg( array( 'payment_gateway_callback' => 'display_paypal_error' ) );
		} elseif ( $response->is_payment_completed() || $response->is_payment_pending() ) {
			$location = remove_query_arg( 'payment_gateway' );
			if ( $response->is_payment_completed() ) {
				$this->purchase_log->set( 'processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT );
			} else {
				$this->purchase_log->set( 'processed', WPSC_Purchase_Log::ORDER_RECEIVED );
			}
			$this->purchase_log->set( 'transactid', $response->get( 'transaction_id' ) )
			                   ->set( 'date', time() )
			                   ->save();
		} else {
			$location = add_query_arg( array( 'payment_gateway_callback' => 'display_generic_error' ) );
		}
		wp_redirect( esc_url_raw( $location ) );
		exit;
	}
	/**
	 * Error Page Template
	 *
	 * @since 3.9
	 */
	public function filter_paypal_error_page() {
		$errors = wpsc_get_customer_meta( 'paypal_express_checkout_errors' );
		ob_start();
		?>
		<p>
			<?php _e( 'Sorry, your transaction could not be processed by PayPal. Please contact the site administrator. The following errors are returned:' , 'wp-e-commerce' ); ?>
		</p>
		<ul>
			<?php foreach ( $errors as $error ): ?>
				<li><?php echo esc_html( $error['details'] ) ?> (<?php echo esc_html( $error['code'] ); ?>)</li>
			<?php endforeach; ?>
		</ul>
		<p><a href="<?php echo esc_url( $this->get_shopping_cart_payment_url() ); ?>"><?php ( 'Click here to go back to the checkout page.') ?></a></p>
		<?php
		$output = apply_filters( 'wpsc_paypal_express_checkout_gateway_error_message', ob_get_clean(), $errors );
		return $output;
	}
	/**
	 * Generic Error Page Template
	 *
	 * @since 3.9
	 */
	public function filter_generic_error_page() {
		ob_start();
		?>
		<p><?php _e( 'Sorry, but your transaction could not be processed by PayPal for some reason. Please contact the site administrator.' , 'wp-e-commerce' ); ?></p>
		<p><a href="<?php echo esc_attr( $this->get_shopping_cart_payment_url() ); ?>"><?php _e( 'Click here to go back to the checkout page.', 'wp-e-commerce' ) ?></a></p>
		<?php
		$output = apply_filters( 'wpsc_paypal_express_checkout_generic_error_message', ob_get_clean() );
		return $output;
	}
	/**
	 * Settings Form Template
	 *
	 * @since 3.9
	 */


	/**s
	 * Displays the setup form
	 *
	 * @access public
	 * @since 3.9
	 * @uses WPSC_Checkout_Form::get()
	 * @uses WPSC_Checkout_Form::field_drop_down_options()
	 * @uses WPSC_Checkout_Form::get_field_id_by_unique_name()
	 * @uses WPSC_Payment_Gateway_Setting::get()
	 *
	 * @return void
	 */
	public function setup_form() {
		?>
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Account Credentials', 'wp-e-commerce' ); ?></h4>
				<?php echo get_option( 'transact_url' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-paypal-in-context-api-merchantid"><?php _e( 'Merchant ID', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'api_merchantid' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'api_merchantid' ) ); ?>" id="wpsc-paypal-express-in-context-merchantid" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-paypal-in-context-api-username"><?php _e( 'API Username', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'api_username' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'api_username' ) ); ?>" id="wpsc-paypal-express-in-context-username" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-paypal-in-context-api-password"><?php _e( 'API Password', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'api_password' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'api_password' ) ); ?>" id="wpsc-paypal-express-in-context-password" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-paypal-in-context-api-signature"><?php _e( 'API Signature', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'api_signature' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'api_signature' ) ); ?>" id="wpsc-paypal-express-in-context-signature" />
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Sandbox Mode', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox_mode' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_mode' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wp-e-commerce' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox_mode' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_mode' ) ); ?>" value="0" /> <?php _e( 'No', 'wp-e-commerce' ); ?></label>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'IPN', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'ipn' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'ipn' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wp-e-commerce' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'ipn' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'ipn' ) ); ?>" value="0" /> <?php _e( 'No', 'wp-e-commerce' ); ?></label>
			</td>
		</tr>

		<?php
	}

	/**
	 * Check if the selected currency is supported by the gateway
	 *
	 * @return bool
	 *
	 * @since 3.9
	 */
	protected function is_currency_supported() {
		return in_array( parent::get_currency_code(), $this->gateway->get_supported_currencies() );
	}
	/**
	 * Return the Currency ISO code
	 *
	 * @return string
	 *
	 * @since 3.9
	 */
	public function get_currency_code() {
		$code = parent::get_currency_code();
		if ( ! in_array( $code, $this->gateway->get_supported_currencies() ) ) {
			$code = $this->setting->get( 'currency', 'USD' );
		}
		return $code;
	}
	/**
	 * Convert an amount (integer) to the supported currency
	 * @param integer $amt
	 *
	 * @return integer
	 *
	 * @since 3.9
	 */
	protected function convert( $amt ) {
		if ( $this->is_currency_supported() ) {
			return $amt;
		}
		return wpsc_convert_currency( $amt, parent::get_currency_code(), $this->get_currency_code() );
	}

	/**
	 * Return Customer to Review Order Page if there are Shipping Costs.
	 *
	 * @param string $url
	 * @return string
	 */
	public function review_order_url( $url ) {
		if ( wpsc_uses_shipping() ) {
			$url = wpsc_get_checkout_url( 'review-order' );
		}

		return $url;
	}
}