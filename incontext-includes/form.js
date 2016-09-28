window.paypalCheckoutReady = function () {
    console.log(wpec_ppic.mid);
    paypal.checkout.setup( wpec_ppic.mid, {
        environment: 'sandbox',
        container: 'wpsc-checkout-form'
    });
};