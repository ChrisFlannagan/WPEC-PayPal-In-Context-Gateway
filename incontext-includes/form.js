window.paypalCheckoutReady = function () {
    paypal.checkout.setup( wpec_ppic.mid, {
        environment: 'sandbox',
        container: 'wpsc-checkout-form'
    });
};