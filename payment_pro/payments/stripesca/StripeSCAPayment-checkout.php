<?php

class StripeSCAPayment implements iPayment
{

    public function __construct() { }

    public static function button($products, $extra = null) {
        $line_items = array();
        if(count($products)==1) {
            $p = current($products);
            $amount = $p['amount']*$p['quantity'];
            $amount_total = $p['amount']*$p['quantity']*((100+$p['tax'])/100);
            $amount_tax = $p['amount']*$p['quantity']*($p['tax']/100);
            $description = $p['description'];
            $product_id = $p['id'];
            $products[$p['id']]['currency'] = osc_get_preference('currency', 'payment_pro');
            $item['name'] = $p['id'];
            $item['amount'] = $amount_total * 100;
            $item['currency'] = osc_get_preference('currency', 'payment_pro');
            $item['quantity'] = 1;
            $line_items[] = $item;
        } else {
            $amount = 0;
            $amount_tax = 0;
            $amount_total = 0;
            //$ids = array();
            foreach($products as $k => $p) {
                $amount += $p['amount']*$p['quantity'];
                $products[$k]['amount_total'] = ($p['amount']*$p['quantity']*((100+$p['tax'])/100));
                $amount_total += $products[$k]['amount_total'];
                $products[$k]['amount_tax'] = $p['amount']*$p['quantity']*($p['tax']/100);
                $amount_tax += $products[$k]['amount_tax'];
                $products[$k]['currency'] = osc_get_preference('currency', 'payment_pro');
                $item['name'] = $p['id'];
                $item['amount'] = $products[$k]['amount_total'] * 100;
                $item['currency'] = $products[$k]['currency'];
                $item['quantity'] = 1;
                $line_items[] = $item;
            }
            $description = sprintf(__('%d products', 'payment_pro'), count($products));
            $product_id = 'SVR_PRD';
        }
        $r = rand(0,1000);
        $extra['random'] = $r;
        //$extra['ids'] = $ids;
        $extra['items'] = $products;
        $extra['amount'] = $amount;
        $extra['amount_tax'] = $amount_tax;
        $extra['amount_total'] = $amount_total;
        $email = $extra['email'];
        $extra = osc_apply_filter('payment_pro_stripe_custom', $extra, $products);
        $extra = payment_pro_set_custom($extra);

        $stripe = self::getEnvironment();
        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        try {
            $session = \Stripe\Checkout\Session::create([
              'payment_method_types' => ['card'],
              'line_items' => $line_items,
              'customer_email' => $email,
              'success_url' => osc_route_url('payment-pro-done', array('tx' => '')),
              'cancel_url' => osc_route_url('payment-pro-checkout', array('itemId' => '')),
            ]);
        } catch (exception $err) {
            osc_add_flash_error_message($err->getMessage());
            return;
        }

        echo '<li style="cursor:pointer;cursor:hand" class="payment stripe-btn" onclick="javascript:stripesca_pay(\''.$session->id.'\');" ><img src="'.PAYMENT_PRO_URL . 'payments/stripesca/pay_with_card.png" ></li>';
    }

    public static function dialogJS() { 
        $stripe = self::getEnvironment();

        ?>
        <div id="stripe-error" class="error"></div>
        <script src="https://js.stripe.com/v3"></script>
        <script type="text/javascript">

        function stripesca_pay(sessionId) {
            var stripe = Stripe('<?php echo $stripe['publishable_key']; ?>');

            stripe.redirectToCheckout({
              sessionId: sessionId
            }).then(function (result) {
               $('#stripe-dialog-text').html(result.error.message);
            });

        }
    </script>

    <?php
    }

    public static function recurringButton($products, $extra = null) {
        $extra['subscription'] = true;
        self::button($products, $extra);
    }

    public static  function ajaxPayment() {
        $status = self::processPayment();
        error_log("STRIPE STATUS: " . $status);
        if ($status==PAYMENT_PRO_COMPLETED) {
            payment_pro_cart_drop();
            osc_add_flash_ok_message(sprintf(__('Success! Please write down this transaction ID in case you have any problem: %s', 'payment_pro'), Params::getParam('stripe_transaction_id')));
        } else if ($status==PAYMENT_PRO_CREATED) {
            payment_pro_cart_drop();
            osc_add_flash_ok_message(sprintf(__("Please wait a moment while we're processing your payment. Transaction ID : %s", 'payment_pro'), Params::getParam('stripe_transaction_id')));
        } else if ($status==PAYMENT_PRO_ALREADY_PAID) {
            payment_pro_cart_drop();
            osc_add_flash_warning_message(__('Warning! This payment was already paid', 'payment_pro'));
        } else {
            osc_add_flash_error_message(__('There were an error processing your payment', 'payment_pro'));
        }
        payment_pro_js_redirect_to(osc_route_url('payment-pro-done', array('tx' => Params::getParam('stripe_transaction_id'))));
    }

    public static function processPayment() {
        $stripe = self::getEnvironment();
        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $token  = Params::getParam('stripeToken');
        $data = payment_pro_get_custom(Params::getParam('extra'));
        $data_amount = $data['amount_total']*100;
        if(!isset($data['items']) || !isset($data['amount_total']) || $data['amount_total']<=0) {
            return PAYMENT_PRO_FAILED;
        }
        $status = payment_pro_check_items($data['items'], $data['amount_total']);

        $orig_items = $data['items'];
        $paid_plan = null;
        $plan_tax = 0;
        if(isset($data['subscription']) && $data['subscription']) {
            $plans = \Stripe\Plan::all();
            $plans = $plans->__get('data');

            if(is_array($plans)) {
                foreach($data['items'] as $k => $item) {
                    foreach($plans as $plan) {
                        if($item['id']==$plan->__get('id')) {
                            $data_amount -= $plan->__get('amount')*((100+$item['tax'])/100);

                            $plan_tax = $item['tax'];

                            $paid_plan = array(
                                'id' => $item['id'],
                                'amount' => $item['amount'],
                                'tax' => $item['tax'],
                                'items' => $item
                            );

                            unset($data['items'][$k]);
                            break; // at the moment stripe can only process one subscription at a time
                        }
                    }
                    if($paid_plan!=null) {
                        break;
                    }
                }
            }
        }

        //$paid_plan = null;
        if($paid_plan==null) { // REGULAR PURCHASE

            $customer = \Stripe\Customer::create(array(
                'email' => $data['email'],
                'card'  => $token
            ));

            try {

                $charge = @\Stripe\Charge::create(array(
                    'customer' => $customer->id,
                    'amount'   => $data_amount,
                    'currency' => strtoupper(osc_get_preference("currency", 'payment_pro'))
                ));

                if($charge->__get('paid')==1) {

                    $exists = ModelPaymentPro::newInstance()->getPaymentByCode($charge->__get('id'), 'STRIPE', PAYMENT_PRO_COMPLETED);
                    if (isset($exists['pk_i_id'])) {
                        return PAYMENT_PRO_ALREADY_PAID;
                    }
                    Params::setParam('stripe_transaction_id', $charge->__get('id'));
                    // SAVE TRANSACTION LOG
                    $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                        $charge->__get('id'),
                        $data['amount'],
                        $data['amount_tax'],
                        $charge->__get('amount') / 100,
                        $status,
                        strtoupper($charge->__get('currency')), //currency
                        @$data['email'],
                        @$data['user']!=''?@$data['user']:$customer->email, //user
                        'STRIPE',
                        $data['items']
                    );

                    if ($status == PAYMENT_PRO_COMPLETED) {
                        foreach ($data['items'] as $item) {
                            $tmp = explode("-", $item['id']);
                            $item['item_id'] = $tmp[count($tmp) - 1];
                            osc_run_hook('payment_pro_item_paid', $item, $data, $invoiceId);
                        }
                    }
                    return $status;
                }

                return PAYMENT_PRO_FAILED;
            } catch(\Stripe\Error\Card $e) {
                return PAYMENT_PRO_FAILED;
            }
        } else { // PROCESS SUBSCRIPTION
            $customer = \Stripe\Customer::create(array(
                'email' => $data['email'],
                'card'  => $token,
                'tax_percent' => $plan_tax
            ));

            if($data_amount>0 && isset($data['items']) && is_array($data['items'])) {
                foreach($data['items'] as $k => $item) {
                    $invoiceItem = \Stripe\InvoiceItem::create(array(
                        "customer" => $customer->id,
                        "amount" => (((100+$item['tax'])/100)*$item['quantity']*$item["amount"])*100,
                        "currency" => strtoupper(osc_get_preference("currency", 'payment_pro')),
                        "description" => $item["id"]
                    ));
                }
            }

            foreach($orig_items as $k => $v) {
                $orig_items[$k]['original'] = true;
            }

            $sub_id = ModelPaymentPro::newInstance()->createSubscription($orig_items, "STRIPE");
            $stripe_sub = $customer->updateSubscription(array("plan" => $paid_plan["id"], "tax_percent" => $plan_tax, "prorate" => true));
            ModelPaymentPro::newInstance()->updateSubscriptionSourceCode($sub_id, $stripe_sub->__get('id'));
            Params::setParam('stripe_transaction_id', $stripe_sub->__get('id'));
            return PAYMENT_PRO_CREATED;

        }

        return PAYMENT_PRO_FAILED;

    }


    public static function createPlan($id, $amount, $name, $currency = 'usd', $interval = 'month') {

        $interval = strtolower($interval);
        if(!in_array($interval, array('day', 'month', 'week', 'year'))) {
            $interval = 'month';
        }

        $stripe = self::getEnvironment();
        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        return \Stripe\Plan::create(array(
                "amount" => $amount,
                "interval" => $interval,
                "name" => $name,
                "currency" => strtolower($currency),
                "id" => $id)
        );
    }

    public static function cancelSubscription($subscr_id, $customer_id) {
        $stripe = self::getEnvironment();
        \Stripe\Stripe::setApiKey($stripe['secret_key']);
        try {
            $customer = \Stripe\Customer::retrieve($customer_id);
            $sub = $customer->subscriptions->retrieve($subscr_id);
            return $sub->cancel();
        } catch(Exception $e) {
            return false;
        }
    }

    public static function getEnvironment() {
        require_once dirname(__FILE__) . '/lib/init.php';
        if(osc_get_preference('stripe_sca_sandbox', 'payment_pro')==0) {
            $stripe = array(
                "webhook_secret"  => payment_pro_decrypt(osc_get_preference('stripe_sca_webhook_secret', 'payment_pro')),
                "secret_key"      => payment_pro_decrypt(osc_get_preference('stripe_sca_secret_key', 'payment_pro')),
                "publishable_key" => payment_pro_decrypt(osc_get_preference('stripe_sca_public_key', 'payment_pro'))
            );
        } else {
            $stripe = array(
                "webhook_secret"  => payment_pro_decrypt(osc_get_preference('stripe_sca_webhook_secret_test', 'payment_pro')),
                "secret_key"      => payment_pro_decrypt(osc_get_preference('stripe_sca_secret_key_test', 'payment_pro')),
                "publishable_key" => payment_pro_decrypt(osc_get_preference('stripe_sca_public_key_test', 'payment_pro'))
            );
        }
        return $stripe;
    }

}

