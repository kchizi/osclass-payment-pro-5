<?php

require_once PAYMENT_PRO_PATH . 'payments/mywaypay/MywaypayPayment.php';
osc_add_hook('ajax_mywaypay', array('MywaypayPayment', 'ajaxPayment'));

osc_add_route('mywaypay-webhook', 'payment/mywaypay-webhook', 'payment/mywaypay-webhook', PAYMENT_PRO_PLUGIN_FOLDER . 'payments/mywaypay/webhook.php');

function payment_pro_mywaypay_install() {
    osc_set_preference('mywaypay_secret_key', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_consumer_key', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_secret_key_test', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_consumer_key_test', payment_pro_crypt(''), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_sandbox', 'sandbox', 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_enabled', '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('mywaypay_bitcoin', '0', 'payment_pro', 'BOOLEAN');
}
osc_add_hook('payment_pro_install', 'payment_pro_mywaypay_install');

function payment_pro_mywaypay_conf_save() {
    osc_set_preference('mywaypay_secret_key', payment_pro_crypt(Params::getParam("mywaypay_secret_key")), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_consumer_key', payment_pro_crypt(Params::getParam("mywaypay_consumer_key")), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_secret_key_test', payment_pro_crypt(Params::getParam("mywaypay_secret_key_test")), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_consumer_key_test', payment_pro_crypt(Params::getParam("mywaypay_consumer_key_test")), 'payment_pro', 'STRING');
    osc_set_preference('mywaypay_sandbox', Params::getParam("mywaypay_sandbox") ? Params::getParam("mywaypay_sandbox") : '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('mywaypay_enabled', Params::getParam("mywaypay_enabled") ? Params::getParam("mywaypay_enabled") : '0', 'payment_pro', 'BOOLEAN');
    osc_set_preference('mywaypay_bitcoin', Params::getParam("mywaypay_bitcoin") ? Params::getParam("mywaypay_bitcoin") : '0', 'payment_pro', 'BOOLEAN');

    if(Params::getParam("mywaypay_enabled")==1) {
        payment_pro_register_service('mywaypay', __FILE__);
    } else {
        payment_pro_unregister_service('mywaypay');
    }
}
osc_add_hook('payment_pro_conf_save', 'payment_pro_mywaypay_conf_save');



function payment_pro_mywaypay_conf_form() {
    require_once dirname(__FILE__) . '/admin/conf.php';
}
osc_add_hook('payment_pro_conf_form', 'payment_pro_mywaypay_conf_form', 2);

function payment_pro_mywaypay_conf_footer() {
    require_once dirname(__FILE__) . '/admin/footer.php';
}
osc_add_hook('payment_pro_conf_footer', 'payment_pro_mywaypay_conf_footer');


function payment_pro_mywaypay_load_lib() {
    if(Params::getParam('page')=='custom' && Params::getParam('route')=='payment-pro-checkout') {
        osc_register_script('payment-pro-mywaypay', 'https://checkout.mywaypay.com/v2/checkout.js', array('jquery'));
        osc_enqueue_script('payment-pro-mywaypay');
    }
}
osc_add_hook('init', 'payment_pro_mywaypay_load_lib');

osc_add_hook('payment_pro_checkout_footer', array('MywaypayPayment', 'dialogJS'));