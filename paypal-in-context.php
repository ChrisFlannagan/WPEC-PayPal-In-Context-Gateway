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
	public $url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

	public function __construct() {
		parent::__construct();
		$this->title = __( 'PayPal In-Context', 'wp-e-commerce' );
		$mode = 'sandbox';
		$this->config_paypal = array (
			'mode' => $mode,
			'acct1.UserName' => esc_attr( $this->setting->get( 'api_username' ) ),
			'acct1.Password' => esc_attr( $this->setting->get( 'api_password' ) ),
			'acct1.Signature' => esc_attr( $this->setting->get( 'api_signature' ) )
		);
		add_action( 'wp_enqueue_scripts', array ( $this, 'load_scripts' ) );
	}

	public function load_scripts() {
		wp_register_script( 'paypalincontextform', plugin_dir_url( __FILE__ ) . 'incontext-includes/form.js' );
		wp_localize_script( 'paypalincontextform', 'wpec_ppic', array( 'mid' => esc_attr( $this->setting->get( 'api_merchantid' ) ) ) );
		wp_enqueue_script( 'paypalincontextform' );
		wp_enqueue_script( 'paypalincontext', 'http://www.paypalobjects.com/api/checkout.js' );
	}

	public function process() {
		require_once( 'incontext-includes/sdk/vendor/paypal/merchant-sdk-php/samples/PPBootStrap.php' );
		if ( $this->setting->get( 'sandbox_mode' ) != '1' ) {
			$mode = 'product';
			$url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
		}
		$paypalService = new PayPalAPIInterfaceServiceService( $this->config_paypal );
		$paymentDetails= new PaymentDetailsType();

		$itemDetails = new PaymentDetailsItemType();
		$itemDetails->Name = 'item';
		$itemAmount = '1.00';
		$itemDetails->Amount = $itemAmount;
		$itemQuantity = '1';
		$itemDetails->Quantity = $itemQuantity;

		$paymentDetails->PaymentDetailsItem[0] = $itemDetails;

		$orderTotal = new BasicAmountType();
		$orderTotal->currencyID = 'USD';
		$orderTotal->value = $itemAmount * $itemQuantity;

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
		// $this->do_transaction();

		// Remove Shortcut option if it exists
		//$sessionid = $_REQUEST['sessionid'];
		//wpsc_delete_customer_meta( 'esc-' . $sessionid );
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

	protected function confirm_transaction() {
		echo "I'M BACK";
		exit();
		/*
		require_once( 'incontext-includes/sdk/vendor/paypal/merchant-sdk-php/samples/PPBootStrap.php' );
		$paypalService                                           = new PayPalAPIInterfaceServiceService( $this->config_paypal );
		$getExpressCheckoutDetailsRequest                        = new GetExpressCheckoutDetailsRequestType( $_GET['token'] );
		$getExpressCheckoutDetailsRequest->Version               = '104.0';
		$getExpressCheckoutReq                                   = new GetExpressCheckoutDetailsReq();
		$getExpressCheckoutReq->GetExpressCheckoutDetailsRequest = $getExpressCheckoutDetailsRequest;

		$getECResponse = $paypalService->GetExpressCheckoutDetails( $getExpressCheckoutReq );
		$this->purchase_log->set( 'processed', WPSC_PAYMENT_STATUS_RECEIVED )->save();
		$this->go_to_transaction_results();
		*/
	}

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