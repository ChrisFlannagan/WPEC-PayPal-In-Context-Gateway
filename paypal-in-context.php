<?php
use PayPal\Service;

class WPSC_Payment_Gateway_PayPal_In_Context extends WPSC_Payment_Gateway {
	/**
	 * Constructor of PayPal In Context Gateway
	 *
	 * @access public
	 * @since 3.9
	 */
	public function __construct() {
		parent::__construct();
		$this->title = __( 'PayPal In-Context', 'wp-e-commerce' );
		add_action( 'wp_enqueue_scripts', array ( $this, 'load_scripts' ) );
	}

	public function load_scripts() {
		wp_register_script( 'paypalincontextform', plugin_dir_url( __FILE__ ) . 'incontext-includes/form.js' );
		wp_localize_script( 'paypalincontextform', 'wpec_ppic', array( 'mid' => esc_attr( $this->setting->get( 'api_merchantid' ) ) ) );
		wp_enqueue_script( 'paypalincontextform' );
		wp_enqueue_script( 'paypalincontext', 'http://www.paypalobjects.com/api/checkout.js' );
	}

	public function process() {
		require_once( 'incontext-includes/sdk/PPBootStrap.php' );
		$mode = 'sandbox';
		if ( $this->setting->get( 'sandbox_mode' ) != '1' ) {
			$mode = 'product';
		}
		$config = array (
			'mode' => $mode,
			'acct1.UserName' => esc_attr( $this->setting->get( 'api_username' ) ),
			'acct1.Password' => esc_attr( $this->setting->get( 'api_password' ) ),
			'acct1.Signature' => esc_attr( $this->setting->get( 'api_signature' ) )
		);
		$paypalService = new PayPalAPIInterfaceServiceService($config);
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
		$setECReqDetails->CancelURL = 'https://devtools-paypal.com/guide/expresscheckout/php?cancel=true';
		$setECReqDetails->ReturnURL = 'https://devtools-paypal.com/guide/expresscheckout/php?success=true';

		$setECReqType = new SetExpressCheckoutRequestType();
		$setECReqType->Version = '104.0';
		$setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

		$setECReq = new SetExpressCheckoutReq();
		$setECReq->SetExpressCheckoutRequest = $setECReqType;

		$setECResponse = $paypalService->SetExpressCheckout($setECReq);
	}


	/**
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
}