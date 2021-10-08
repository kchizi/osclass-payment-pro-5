<?php

require_once PAYMENT_PRO_PATH . 'payments/stripesca/StripeSCAPayment.php';
osc_add_hook('ajax_stripe', array('StripeSCAPayment', 'ajaxPayment'));

osc_add_route('stripe-sca-webhook', 'stripe-sca-webhook', 'stripe-sca-webhook', PAYMENT_PRO_PLUGIN_FOLDER . 'payments/stripesca/webhook.php');
osc_add_route('stripe-sca-payment-intent', 'ssca-sca-pi/([a-zA-Z]+)/([a-zA-Z0-9\.\+\-\%\@\-\_]+)/([a-zA-Z0-9\-\_]+)', 'ssca-sca-pi/{action}/{data}/{id}', PAYMENT_PRO_PLUGIN_FOLDER . 'payments/stripesca/payment_intent.php', false, 'custom', 'custom', __('Init Checkout', 'payment_pro'));

if (!function_exists('payment_pro_stripe_sca_install')) {
function payment_pro_stripe_sca_install() {
    osc_set_preference('stripe_sca_secret_key', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_public_key', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_secret_key_test', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_public_key_test', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_sandbox', 'sandbox', 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_enabled', '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('stripe_sca_bitcoin', '0', 'payment_pro', 'BOOLEAN');
}
}
osc_add_hook('payment_pro_install', 'payment_pro_stripe_sca_install');

if (!function_exists('payment_pro_stripe_sca_conf_save')) {
function payment_pro_stripe_sca_conf_save() {
    osc_set_preference('stripe_sca_secret_key', payment_pro_crypt(Params::getParam("stripe_sca_secret_key")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_public_key', payment_pro_crypt(Params::getParam("stripe_sca_public_key")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_webhook_secret', payment_pro_crypt(Params::getParam("stripe_sca_webhook_secret")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_secret_key_test', payment_pro_crypt(Params::getParam("stripe_sca_secret_key_test")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_public_key_test', payment_pro_crypt(Params::getParam("stripe_sca_public_key_test")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_webhook_secret_test', payment_pro_crypt(Params::getParam("stripe_sca_webhook_secret_test")), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_source', Params::getParam("stripe_sca_source"), 'payment_pro', 'STRING');
    osc_set_preference('stripe_sca_sandbox', Params::getParam("stripe_sca_sandbox") ? Params::getParam("stripe_sca_sandbox") : '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('stripe_sca_enabled', Params::getParam("stripe_sca_enabled") ? Params::getParam("stripe_sca_enabled") : '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('stripe_sca_bitcoin', Params::getParam("stripe_sca_bitcoin") ? Params::getParam("stripe_sca_bitcoin") : '0', 'payment_pro', 'BOOLEAN');

    if(Params::getParam("stripe_sca_enabled")==1) {
        payment_pro_register_service('StripeSCA', __FILE__);
    } else {
        payment_pro_unregister_service('StripeSCA');
    }
}
}
osc_add_hook('payment_pro_conf_save', 'payment_pro_stripe_sca_conf_save');



if (!function_exists('payment_pro_stripe_sca_conf_form')) {
function payment_pro_stripe_sca_conf_form() {
    require_once dirname(__FILE__) . '/admin/conf.php';
}
}
osc_add_hook('payment_pro_conf_form', 'payment_pro_stripe_sca_conf_form', 2);

if (!function_exists('payment_pro_stripe_sca_conf_footer')) {
function payment_pro_stripe_sca_conf_footer() {
    require_once dirname(__FILE__) . '/admin/footer.php';
}
}
osc_add_hook('payment_pro_conf_footer', 'payment_pro_stripe_sca_conf_footer');

if (!function_exists('payment_pro_stripe_sca_load_lib')) {
function payment_pro_stripe_sca_load_lib() {
    if(Params::getParam('page')=='custom' && strpos(Params::getParam('route'), 'checkout') !== false) {
        echo '<script type="text/javascript" src="https://js.stripe.com/v3/"></script>';
    }
}
}
osc_add_hook('footer', 'payment_pro_stripe_sca_load_lib');

osc_add_hook('payment_pro_checkout_footer', array('StripeSCAPayment', 'dialogJS'));