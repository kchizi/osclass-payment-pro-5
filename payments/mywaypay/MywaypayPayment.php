<?php

class MywaypayPayment implements iPayment
{

    public function __construct() { }

    public static function button($products, $extra = null) {
        if(count($products)==1) {
            $p = current($products);
            $amount = $p['amount']*$p['quantity'];
            $amount_total = $p['amount']*$p['quantity']*((100+$p['tax'])/100);
            $amount_tax = $p['amount']*$p['quantity']*($p['tax']/100);
            $description = $p['description'];
            $product_id = $p['id'];
            $products[$p['id']]['currency'] = osc_get_preference('currency', 'payment_pro');
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
        $extra = osc_apply_filter('payment_pro_mywaypay_custom', $extra, $products);
        $extra = payment_pro_set_custom($extra);

?>
        <li style="cursor:pointer;cursor:hand" class="payment mywaypay-btn" onclick="javascript:mywaypay_pay('<?php echo $amount_total.','.$description.','.$product_id.','.$extra; ?>');" >
            <button class="btn btn-success btn-lg"><i class="bi bi-credit-card"></i> <?php _e('mywaypay','payment_pro'); ?></button>
        </li>
<?php
    }

    public static function dialogJS() { ?>
        <div id="mywaypay-dialog" title="<?php _e('mywaypay', 'payment_pro'); ?>" style="display: none;"><span id="mywaypay-dialog-text"></span></div>
        <form action="<?php echo osc_base_url(true); ?>" method="post" id="mywaypay-payment-form" class="nocsrf" >
            <input type="hidden" name="page" value="ajax" />
            <input type="hidden" name="action" value="runhook" />
            <input type="hidden" name="hook" value="mywaypay" />
            <input type="hidden" name="extra" value="" id="mywaypay-extra" />
        </form>
        <script type="text/javascript">
            function mywaypay_pay(amount_total, description, product_id, extra) {
                var token = function(res){
                    var $input = $('<input type=hidden name=mywaypayToken />').val(res.id);
                    $('#mywaypay-extra').attr('value', extra);
                    $('#mywaypay-payment-form').append($input);
                    $.ajax({
                        type: "POST",
                        url: '<?php echo osc_base_url(true); ?>',
                        data: $("#mywaypay-payment-form").serialize(),
                        success: function(data)
                        {
                            $('#mywaypay-dialog-text').html(data);
                        }
                    });
                    setTimeout(openmywaypayDialog, 150);
                };


                mywaypayCheckout.open({
                    key:         '<?php echo payment_pro_decrypt(osc_get_preference('mywaypay_sandbox', 'payment_pro')?osc_get_preference('mywaypay_consumer_key_test', 'payment_pro'):osc_get_preference('mywaypay_consumer_key', 'payment_pro')); ?>',
                    address:     false,
                    amount:      (amount_total*100),
                    currency:    '<?php echo strtoupper(osc_get_preference("currency", 'payment_pro'));?>',
                    name:        description,
                    description: product_id,
                    panelLabel:  'Checkout',
                    <?php if(osc_get_preference("mywaypay_bitcoin", 'payment_pro')==1) { echo 'bitcoin:     true,'; }; ?>
                    token:       token
                });


                return false;
            };

            function openmywaypayDialog() {
                $('#mywaypay-dialog-text').html('<?php echo osc_esc_js(__("Please wait a moment while we're processing your payment", 'payment_pro')); ?>');
                $('#mywaypay-dialog').dialog('open')
            }

            $(document).ready(function(){
                $("#mywaypay-dialog").dialog({
                    autoOpen: false,
                    modal: true
                });
            });

        </script>

    <?php
    }

    public static function recurringButton($products, $extra = null) {
        $extra['subscription'] = true;
        self::button($products, $extra);
    }

    public static  function ajaxPayment() {
        $status = self::processPayment();
        error_log("mywaypay STATUS: " . $status);
        if ($status==PAYMENT_PRO_COMPLETED) {
            payment_pro_cart_drop();
            osc_add_flash_ok_message(sprintf(__('Success! Please write down this transaction ID in case you have any problem: %s', 'payment_pro'), Params::getParam('mywaypay_transaction_id')));
        } else if ($status==PAYMENT_PRO_CREATED) {
            payment_pro_cart_drop();
            osc_add_flash_ok_message(sprintf(__("Please wait a moment while we're processing your payment. Transaction ID : %s", 'payment_pro'), Params::getParam('mywaypay_transaction_id')));
        } else if ($status==PAYMENT_PRO_ALREADY_PAID) {
            payment_pro_cart_drop();
            osc_add_flash_warning_message(__('Warning! This payment was already paid', 'payment_pro'));
        } else {
            osc_add_flash_error_message(__('There were an error processing your payment', 'payment_pro'));
        }
        payment_pro_js_redirect_to(osc_route_url('payment-pro-done', array('tx' => Params::getParam('mywaypay_transaction_id'))));
    }

    public static function processPayment() {
        $mywaypay = self::getEnvironment();
        \mywaypay\mywaypay::setApiKey($mywaypay['secret_key']);

        $token  = Params::getParam('mywaypayToken');
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
            $plans = \mywaypay\Plan::all();
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
                            break; // at the moment mywaypay can only process one subscription at a time
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

            $customer = \mywaypay\Customer::create(array(
                'email' => $data['email'],
                'card'  => $token
            ));

            try {

                $charge = @\mywaypay\Charge::create(array(
                    'customer' => $customer->id,
                    'amount'   => $data_amount,
                    'currency' => strtoupper(osc_get_preference("currency", 'payment_pro'))
                ));

                if($charge->__get('paid')==1) {

                    $exists = ModelPaymentPro::newInstance()->getPaymentByCode($charge->__get('id'), 'mywaypay', PAYMENT_PRO_COMPLETED);
                    if (isset($exists['pk_i_id'])) {
                        return PAYMENT_PRO_ALREADY_PAID;
                    }
                    Params::setParam('mywaypay_transaction_id', $charge->__get('id'));
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
                        'mywaypay',
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
            } catch(\mywaypay\Error\Card $e) {
                return PAYMENT_PRO_FAILED;
            }
        } else { // PROCESS SUBSCRIPTION
            $customer = \mywaypay\Customer::create(array(
                'email' => $data['email'],
                'card'  => $token,
                'tax_percent' => $plan_tax
            ));

            if($data_amount>0 && isset($data['items']) && is_array($data['items'])) {
                foreach($data['items'] as $k => $item) {
                    $invoiceItem = \mywaypay\InvoiceItem::create(array(
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

            $sub_id = ModelPaymentPro::newInstance()->createSubscription($orig_items, "mywaypay");
            $mywaypay_sub = $customer->updateSubscription(array("plan" => $paid_plan["id"], "tax_percent" => $plan_tax, "prorate" => true));
            ModelPaymentPro::newInstance()->updateSubscriptionSourceCode($sub_id, $mywaypay_sub->__get('id'));
            Params::setParam('mywaypay_transaction_id', $mywaypay_sub->__get('id'));
            return PAYMENT_PRO_CREATED;

        }

        return PAYMENT_PRO_FAILED;

    }


    public static function createPlan($id, $amount, $name, $currency = 'usd', $interval = 'month') {

        $interval = strtolower($interval);
        if(!in_array($interval, array('day', 'month', 'week', 'year'))) {
            $interval = 'month';
        }

        $mywaypay = self::getEnvironment();
        \mywaypay\mywaypay::setApiKey($mywaypay['secret_key']);

        return \mywaypay\Plan::create(array(
                "amount" => $amount,
                "interval" => $interval,
                "name" => $name,
                "currency" => strtolower($currency),
                "id" => $id)
        );
    }

    public static function cancelSubscription($subscr_id, $customer_id) {
        $mywaypay = self::getEnvironment();
        \mywaypay\mywaypay::setApiKey($mywaypay['secret_key']);
        try {
            $customer = \mywaypay\Customer::retrieve($customer_id);
            $sub = $customer->subscriptions->retrieve($subscr_id);
            return $sub->cancel();
        } catch(Exception $e) {
            return false;
        }
    }

    public static function getEnvironment() {
        require_once dirname(__FILE__) . '/lib/init.php';
        if(osc_get_preference('mywaypay_sandbox', 'payment_pro')==0) {
            $mywaypay = array(
                "secret_key"      => payment_pro_decrypt(osc_get_preference('mywaypay_secret_key', 'payment_pro')),
                "publishable_key" => payment_pro_decrypt(osc_get_preference('mywaypay_consumer_key', 'payment_pro'))
            );
        } else {
            $mywaypay = array(
                "secret_key"      => payment_pro_decrypt(osc_get_preference('mywaypay_secret_key_test', 'payment_pro')),
                "publishable_key" => payment_pro_decrypt(osc_get_preference('mywaypay_consumer_key_test', 'payment_pro'))
            );
        }
        return $mywaypay;
    }

}

