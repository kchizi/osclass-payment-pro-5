<?php

class StripeSCAPayment implements iPayment
{

    public function __construct() { }

    public static function button($products, $extra = null) {
        $line_items = array(); $i = 0;
        if(count($products)==1) {
            $p = current($products);
            $amount = $p['amount']*$p['quantity'];
            $amount_total = $p['amount']*$p['quantity']*((100+$p['tax'])/100);
            $amount_tax = $p['amount']*$p['quantity']*($p['tax']/100);
            $description = $p['description'];
            $product_id = $p['id'];
            if (!isset($products[$p['id']]['currency'])) {
                $products[$p['id']]['currency'] = osc_get_preference('currency', 'payment_pro');
                $line_items['currency_'.$i] = osc_get_preference('currency', 'payment_pro');
            } else {
                $line_items['currency_'.$i] = $products[$p['id']]['currency'];
            }
            $symbol = $products[$p['id']]['symbol'];
            $line_items['name_'.$i] = $p['id'];
            $line_items['amount_'.$i] = $amount_total * 100;
            $line_items['quantity_'.$i] = 1;
            $curr = $line_items['currency_'.$i];
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
                $products[$k]['item_id'] = $p['id'];
                if (!isset($products[$k]['currency'])) {
                    $products[$k]['currency'] = osc_get_preference('currency', 'payment_pro');
                }
                $symbol = $products[$k]['symbol'];
                $line_items['name_'.$i] = $p['id'];
                $line_items['amount_'.$i] = $products[$k]['amount_total'] * 100;
                $line_items['currency_'.$i] = $products[$k]['currency'];
                $line_items['quantity_'.$i] = 1;
                $curr = $line_items['currency_'.$i];
                $i++;
            }
            $description = sprintf(__('%d products', 'payment_pro'), count($products));
            $product_id = 'SVR_PRD';
        }
        $r = rand(0,1000);
        $extra['random'] = $r;
        //$extra['ids'] = $ids;
        $extra['items'] = json_encode($products);
        $extra['details'] = json_encode($extra['details']);
        $extra['amount'] = $amount;
        $extra['amount_tax'] = $amount_tax;
        $extra['amount_total'] = $amount_total;
        $extra['symbol'] = $symbol;
        $email = $extra['email'];
        $extra = osc_apply_filter('payment_pro_stripe_custom', $extra, $products);

        $user = User::newInstance()->findByPrimaryKey($extra['user']);
        $extra['user'] = $user['s_username'];        
        $extra['name'] = $user['s_name'];
        $extra['address'] = $user['s_address'];
        $extra['zip'] = $user['s_zip'];
        $extra['city'] = $user['s_city'];
       
        $secret = Cookie::newInstance()->get_value('oc_userSecret');
        if (empty($secret)) {
            $secret = Session::newInstance()->_get('user_secret');
            if (empty($secret)) {

                //this include contains de osc_genRandomPassword function
                require_once osc_lib_path() . 'osclass/helpers/hSecurity.php';
                $secret = osc_genRandomPassword();

                User::newInstance()->update(
                        array('s_secret' => $secret)
                        , array('pk_i_id' => osc_logged_user_id())
                );
                Session::newInstance()->_set('user_secret', $secret);
            }
        }

        $params = array('userid' => osc_logged_user_id(),
            'secret' => $secret,
            'amount' => $amount_total*100,
            'currency' => strtolower($curr),
            'items' => $extra['items'],
            'details'=> $extra['details']);
        $params = urlencode(json_encode($params));

        $extra = payment_pro_set_custom($extra);
        Params::setParam('extra', $extra);
        ?>
        <li style="cursor:pointer;cursor:hand" id="ssca-btn" class="payment ssca-btn" onclick="javascript:ssca_pay(<?php echo $params; ?>);" >
            <button class="btn btn-success btn-lg"><i class="bi bi-credit-card"></i> <?php _e('stripe','payment_pro'); ?></button>
        </li>
        <?php
    }

    public static function dialogJS() { 
        $stripe = self::getEnvironment();
        $data = payment_pro_get_custom(Params::getParam('extra'));
        if ($data) {
        ?>
<style type="text/css">
#ssca-button-row {
    text-align: center;}
</style>
<div id="ssca-dialog" title="<?php _e('Pay by credit card', 'payment_pro'); ?>" class="" style="display: none;">
<span id="ssca-dialog-text"></span>
<form action="<?php echo osc_base_url(true); ?>" method="post" id="ssca-payment-form" class="nocsrf" >
    <div class="form-group"><label for="amount" class="control-label"><?php  _e('Amount to pay'); ?></label>
        <div class="col-sm-16"><span id="total-amount"><?php echo payment_pro_format_price($data['amount_total']*1000000,$data['symbol']); ?></span></div></div>
    <div class="form-group"><label for="cardholder-name" class="control-label"><?php  _e('Card Holder Name'); ?></label>
        <div class="col-sm-16"><input type="text" id="cardholder-name" class="form-control" value="<?php  echo osc_logged_user_name(); ?>" required></text></div></div>
    <div class="form-group"><label for="card-element" class="control-label"><?php  _e('Card Details'); ?></label>
    <div id="card-element" class="col-sm-16 form-control"></div></div>
    <input type="hidden" name="page" value="ajax" />
    <input type="hidden" name="action" value="runhook" />
    <input type="hidden" name="hook" value="stripe" />
    <input type="hidden" name="extra" value="" id="ssca-extra" />
    <input type="hidden" name="address"  value="<?php  echo $data['address']; ?>" id="ssca-address"/>
    <input type="hidden" name="zip" value="<?php  echo $data['zip']; ?>" id="ssca-zip" />
    <input type="hidden" name="city" value="<?php  echo $data['city']; ?>" id="ssca-city" />
    <input type="hidden" name="email" value="<?php  echo $data['email']; ?>" id="ssca-email" />
    <div id="ssca-button-row"><button id="ssca-button" class="btn btn-success"><?php  _e('Pay with card'); ?></button></div>
</form>
<div id="card-errors" role="alert" class="payment-errors"></div>
</div>
<script type="text/javascript">
    function ssca_pay(params) {
        var ssca = Stripe('<?php  echo $stripe['publishable_key']; ?>');
        var elements = ssca.elements();
        var cardElement = elements.create('card', {hidePostalCode: true});
        var intentId = 'pi';
        cardElement.mount('#card-element');

        setTimeout(openSSCADialog, 150);

        $('#ssca-button').click(function(ev) {
          $('#card-errors').html('<?php echo osc_esc_js(__("Please wait a moment while we're processing your payment", 'payment_pro')); ?>');
          try {
            // create initial payment intent, get client secret
            $.get( "<?php echo osc_base_url(); ?>ssca-sca-pi/init/" + params + "/" + intentId, function( response ) {
              if (response.includes('<')) {
                response = response.substring(0, response.indexOf('<'));
              }
              data = JSON.parse(response);
              if (data.status == 'ok') {
                clientSecret = data.client_secret;
                intentId = data.id;

                ssca.handleCardPayment(
                  clientSecret, cardElement, {
                    payment_method_data: {
                      billing_details: {
                          name: $('#cardholder-name').val(),
                          address: {
                              city: $('#ssca-city').val(),
                              line1: $('#ssca-address').val(),
                              postal_code: $('#ssca-zip').val(),
                          },
                          email: $('#ssca-email').val()}
                    }
                  }
                ).then(function(result) {
                  if (result.error) {
                    $('#ssca-dialog-text').html(result.error.message);
                  } else {
                    var $input = $('<input type=hidden name=sscaToken />').val(result.paymentIntent.id);
                    $('#ssca-payment-form').append($input);
                    $.ajax({
                        type: "POST",
                        url: '<?php echo osc_base_url(true); ?>',
                        data: $("#ssca-payment-form").serialize(),
                        success: function(data)
                        {
                            $('#ssca-dialog-text').html(data);
                            $(this).closest('.ui-dialog-content').dialog('close');
                        }
                    });

                  }
                });
              } else {
                $('#ssca-dialog-text').html(data);
              }
            });

            } catch (error) {
                $('#ssca-dialog-text').html(data.error);
            }
            return false;
        });

    };

    $('div#ssca-dialog').on('dialogclose', function(event) {
        $("#bank_start").show();
        $('#stripe-btn').show();
        $('#ssca-btn').show();
    });

    function openSSCADialog() {
        $('#ssca-dialog').dialog('open');
        $("#bank_start").hide();
        $('#stripe-btn').hide();
        $('#ssca-btn').hide();
    }

    $(document).ready(function(){
        $("#ssca-dialog").dialog({
            autoOpen: false,
            modal: true,
            open: function (event, ui) {
                $(".ui-widget-overlay").addClass('modal-opened');
            },
            close: function(event, ui){
              $(".ui-widget-overlay").removeClass('modal-opened');
            }
        });
    });
    var bootstrapButton = $.fn.button.noConflict() // return $.fn.button to previously assigned value
    $.fn.bootstrapBtn = bootstrapButton            // give $().bootstrapBtn the Bootstrap functionality
</script>

    <?php
        }
    }

    public static function recurringButton($products, $extra = null) {
        $extra['subscription'] = true;
        self::button($products, $extra);
    }

    public static  function ajaxPayment() {
        Session::newInstance()->_drop('ssca_intent_id');
        $token  = Params::getParam('sscaToken');
        
        error_log("STRIPE TOKEN: " . $token);
        if (!empty($token)) {
            payment_pro_cart_drop();
            osc_add_flash_ok_message(sprintf(__('Success! Please write down this transaction ID in case you have any problem: %s', 'payment_pro'), $token));
        } else {
            osc_add_flash_error_message(__('There were an error processing your payment', 'payment_pro'));
        }
        $checkoutURL = osc_get_preference('checkoutURL', 'ppe_attributes');
        $baseURL = osc_get_preference('baseURL', 'ppe_attributes');
        $url = osc_route_url('payment-pro-done', array('tx' => $token));
        if (!empty($checkoutURL) && $checkoutURL == osc_base_url()) {   
            $url = str_replace($checkoutURL, $baseURL, $url);
        }
        payment_pro_js_redirect_to($url);
    }

    public static function processPayment() {
        
        return PAYMENT_PRO_CREATED;
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

