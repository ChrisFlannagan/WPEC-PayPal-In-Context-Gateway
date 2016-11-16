#########################################

PayPal In Context - WP Ecommerce

Originally this was being built as it's own gateway option for WPEC.

Current status is still it's own gateway.  This repo's files need to be
added to:

WP-e-Commerce-master/wpsc-components/merchant-core-v3/gateways/

#########################################

Current Status
--------------

Currently the plugin will work and check the user out using in-context.
If for any reason in-context won't work, then it falls back to standard
express checkout taking the user to paypal.com's website to finish the
transaction.

Current Issues & Unfinished Items
---------------------------------

1) The gateway needs to be combined with the paypal-express-checkout.php
gateway file and be optional for in-context (default on). 

 - The biggest issue I encountered was lack of documentation on WPEC.
 Some things just didn't work and I couldn't reverse engineer the gateway
 process enough to figure it out.
 - The express checkout gateway uses a different .js library and php sdk.
 I believe it's an older version and I was unable to get that to align
 with the latest sdk and .js library PayPal provides that I use in this
 gateway plugin.
 
2) There were miscellaneous admin features that were requested.  These
can't be implemented until issue 1 is fixed.  Primarily needing to issue
refunds through the admin panel of WPEC.
