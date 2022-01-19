<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

ob_get_clean();

$stripe = StripeSCAPayment::getEnvironment();

$payload = @file_get_contents('php://input');
$sig_header = '';
if (isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
}
$event = null;
$sourceCode = osc_get_preference('stripe_sca_source', 'payment_pro');
if (empty($sourceCode)) {
    $sourceCode = 'STRIPE';
}
try {
    $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $stripe['webhook_secret']
    );
} catch (\UnexpectedValueException $e) {
    // Invalid payload
    _e('Unexpected value in webhook payload');
    http_response_code(400); // PHP 5.4 or greater
    exit();
} catch (\Stripe\Error\SignatureVerification $e) {
    // Invalid signature
    _e('Invalid webhook signature: ' . $e->getMessage());
    http_response_code(400); // PHP 5.4 or greater
    exit();
}

\Stripe\Stripe::setApiKey($stripe['secret_key']);

$customer_email = '';
if (isset($event->data) && isset($event->data->object) && isset($event->data->object->customer)) {
    $customer_id = $event->data->object->customer;
    $customer_email = $customer_id;
    try {
        $customer = \Stripe\Customer::retrieve($customer_id);
    } catch (Exception $e) {
        _e('Webhook Customer not found: ' . $customer_id);
        http_response_code(400); // PHP 5.4 or greater
        exit();
    }
    if (isset($customer->deleted) && $customer->deleted) {
        print_r("CUSTOMER DELETED");
        http_response_code(400); // PHP 5.4 or greater
        exit();
    }
    if (isset($customer->email)) {
        $customer_email = $customer->email;
    } else if (isset($customer->sources) && isset($customer->sources->data) && isset($customer->sources->data[0]) && isset($customer->sources->data[0]->name)) {
        $customer_email = $customer->sources->data[0]->name;
    } else if (isset($customer->cards) && isset($customer->cards->data) && isset($customer->cards->data[0]) && isset($customer->cards->data[0]->name)) {
        $customer_email = $customer->cards->data[0]->name;
    } else {
        $customer_email = "noemail@stripe.com";;
    }
}

osc_run_hook('payment_pro_stripe_sca_webhook', $event);

$type = $event->type;


switch($type) {
    case 'customer.subscription.created':
        $request = Params::getParamsAsArray();
        osc_run_hook('payment_pro_signup_subscription', $event, $request);
        break;
    case 'customer.subscription.deleted':
        $request = Params::getParamsAsArray();
        osc_run_hook('payment_pro_cancel_subscription', $event, $request);
        break;
    //case 'charge.succeeded':
    case 'invoice.payment_succeeded':

        $exists = ModelPaymentPro::newInstance()->getPaymentByCode($event->data->object->id, $sourceCode, PAYMENT_PRO_COMPLETED);

        // DEBUG
        if (isset($exists['pk_i_id'])) {
            echo "PAYMENT ALREADY EXISTS " . $event->data->object->id . "/" . $exists['pk_i_id'];
            http_response_code(400); // PHP 5.4 or greater
            exit();
            //payment_pro_do_404();
            //return PAYMENT_PRO_ALREADY_PAID;
        }

        // /!\  /!\  /!\ WARNING  /!\  /!\  /!\
        // WE SHOULD CHECK IF THE AMOUNTS ARE CORRECT
        // but we retrieve the event from the Stripe server, so it should be safe

        if(isset($event->data) && isset($event->data->object) && isset($event->data->object->invoice)) {
            $invoice = $event->data->object;
            // SAVE TRANSACTION LOG
            // Second or later payment
            $productsdb = ModelPaymentPro::newInstance()->subscription($invoice->id);
            $first_payment = false;
            if(count($productsdb)==0) {
                // FIRST PAYMENT
                $productsdb = ModelPaymentPro::newInstance()->subscriptionBySourceCode($invoice->id);
                $first_payment = true;
            }

            if(isset($productsdb[0]) && isset($productsdb[0]['s_code'])) {
                $payment_count = ModelPaymentPro::newInstance()->subscriptionCount($productsdb[0]['s_code'])+1;
            } else {
                $payment_count = 1;
            }
            // Prepare items from the DB
            $items = array();
            $amount = 0;
            $amount_tax = 0;
            $amount_total = 0;
            foreach($productsdb as $p) {
                if($first_payment || $p['i_count']==1) {
                    $extra = json_decode($p['s_extra'], true);
                    $amount += $p['i_quantity'] * $p['i_amount'] / 1000000;
                    $amount_tax += $p['i_amount_tax'] / 1000000;
                    $amount_total += $p['i_amount_total'] / 1000000;
                    $extra['customer_id'] = $customer_id;
                    $tmp = explode("-", $p['i_product_type']);
                    $itemId = $tmp[count($tmp) - 1];
                    $item = array(
                        'id' => $p['i_product_type'],
                        'description' => $p['s_concept'],
                        'item_id' => $itemId,
                        'amount' => $p['i_amount'] / 1000000,
                        'tax' => $p['i_tax'] / 100,
                        'amount_total' => $p['i_amount_total'] / 1000000,
                        'quantity' => $p['i_quantity'],
                        'currency' => $p['s_currency_code'],
                        'extra' => $extra,
                        'sub_count' => $payment_count,
                        'sub_id' => $p['s_code']
                    );
                    $items[] = $item;
                    if ($first_payment) {
                        $extra['original'] = true;
                        ModelPaymentPro::newInstance()->updateSubscriptionItemData(
                            $p['pk_i_id'],
                            array(
                                's_extra' => json_encode($extra),
                                //'i_status' => PAYMENT_PRO_COMPLETED,
                                's_source_code' => $event->data->object->id,
                                's_code' => $invoice->id
                            )
                        );
                        osc_run_hook('payment_pro_stripe_first_subscription', $inv_id, $p, $event->data->object->id, $item);
                    }
                }
            }
            // RECREATE EXTRA DATA
            $data = array(
                'items' => $items,
                'amount' => $event->data->object->total,
                'email' => $customer_email
            );

            foreach($items as $k => $item) {
                if (!$first_payment) {
                    $subitemid = ModelPaymentPro::newInstance()->updateSubscriptionItem($inv_id, $item['id'], PAYMENT_PRO_COMPLETED, $item, $event->data->object->id, "Cr Card");
                    $items[$k]['update_item_sub_id'] = $subitemid;
                }
                $tmp = explode("-", $item['id']);
                $items[$k]['item_id'] = $tmp[count($tmp) - 1];
            }
            $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                $event->data->object->id,
                $amount,
                $amount_tax,
                $event->data->object->total/100,
                PAYMENT_PRO_COMPLETED,
                strtoupper($event->data->object->currency),
                $customer_email,
                null,
                $sourceCode,
                $items
            );

            if (isset($extra['details'])) {
                error_log("webhook extra:".implode(',',$extra['details']).'-'.$invoiceId);
                ModelPaymentPro::newInstance()->setInvoiceExtra($invoiceId, json_encode($extra['details']));
            }
            
            //ModelPaymentPro::newInstance()->updateSubscriptionStatusBySourceCode($sub_id, PAYMENT_PRO_COMPLETED);
            $status = PAYMENT_PRO_COMPLETED;
            if ($status == PAYMENT_PRO_COMPLETED) {
                foreach($items as $item) {
                    $itemid = explode("-", $item['id']);
                    $item['item_id'] = $itemid[count($itemid)-1];
                    if (substr($item['id'], 0, 4) == 'SUB-') {
                        $tmp = explode("/", str_replace("SUB-", "", $item['id']));
                        foreach($tmp as $t) {
                            $itemid = explode("-", $t);
                            $tmp_item = $item;
                            $tmp_item['id'] = $t;
                            $tmp_item['item_id'] = $itemid[count($itemid)-1];
                            osc_run_hook('payment_pro_item_paid', $tmp_item, $data, $invoiceId);
                        }
                    } else {
                        osc_run_hook('payment_pro_item_paid', $item, $data, $invoiceId);
                    }
                }
            }
        } else {
            echo "NO SUBSCRIPTION";
            http_response_code(400); // PHP 5.4 or greater
            exit();
            //payment_pro_do_404();
            // SUB ID DOES NOT EXISTS
        }

        break;
    case 'payment_intent.succeeded':
        $exists = ModelPaymentPro::newInstance()->getPaymentByCode($event->data->object->id, $sourceCode, PAYMENT_PRO_COMPLETED);

        // DEBUG
        if (isset($exists['pk_i_id'])) {
            echo "PAYMENT ALREADY EXISTS " . $event->data->object->id . "/" . $exists['pk_i_id'];
            http_response_code(400); // PHP 5.4 or greater
            exit();
            //payment_pro_do_404();
            //return PAYMENT_PRO_ALREADY_PAID;
        }

        // /!\  /!\  /!\ WARNING  /!\  /!\  /!\
        // WE SHOULD CHECK IF THE AMOUNTS ARE CORRECT
        // but we retrieve the event from the Stripe server, so it should be safe

        if(isset($event->data) && isset($event->data->object) && $event->data->object->object == 'payment_intent') {
            $intent = $event->data->object;

            error_log('webhook pi: ' . $intent->id);

            $data = $intent->metadata;
            $data_amount = $data['amount_total']*100;
            if(!isset($data['items']) || !isset($data['amount_total']) || $data['amount_total']<=0) {
                echo "UNEXPECTED AMOUNT";
                http_response_code(400); // PHP 5.4 or greater
                exit();
            }
            $items = json_decode($data['items'],true);
            $status = payment_pro_check_items($items, $data['amount_total']);

            $exists = ModelPaymentPro::newInstance()->getPaymentByCode($intent->id, $sourceCode, PAYMENT_PRO_COMPLETED);
            if (isset($exists['pk_i_id'])) {
                echo "ALREADY PAID"; 
                http_response_code(400); // PHP 5.4 or greater
                exit();
            }
            foreach($items as $k => $item) {
                $tmp = explode("-", $item['id']);
                $items[$k]['item_id'] = $tmp[count($tmp) - 1];
            }
            // SAVE TRANSACTION LOG
            $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                $intent->id,
                $intent->amount / 100,
                $data['amount_tax'],
                $intent->amount / 100,
                $status,
                strtoupper($intent->currency), //currency
                @$data['email'],
                @$data['user']!=''?@$data['user']:$customer->email, //user
                $sourceCode,
                $items
            );

            if (isset($data['details'])) {
                $result = ModelPaymentPro::newInstance()->setInvoiceExtra($invoiceId, $data['details']);
                error_log('webhook extra: ' . $result);
            }

            if ($status == PAYMENT_PRO_COMPLETED) {
                foreach ($items as $item) {
                    $tmp = explode("-", $item['id']);
                    $item['item_id'] = $tmp[count($tmp) - 1];
                    osc_run_hook('payment_pro_item_paid', $item, $data, $invoiceId);
                }
            }
            echo "PROCESSED";
            http_response_code(200); // PHP 5.4 or greater
            exit();
        }
        echo "MISSING INTENT";
        http_response_code(400); // PHP 5.4 or greater
        exit();
        break;
    case 'payment_intent.payment_failed':
        $exists = ModelPaymentPro::newInstance()->getPaymentByCode($event->data->object->id, $sourceCode, PAYMENT_PRO_COMPLETED);

        // DEBUG
        if (isset($exists['pk_i_id'])) {
            echo "PAYMENT ALREADY EXISTS " . $event->data->object->id . "/" . $exists['pk_i_id'];
            http_response_code(400); // PHP 5.4 or greater
            exit();
            //payment_pro_do_404();
            //return PAYMENT_PRO_ALREADY_PAID;
        }
        if(isset($event->data) && isset($event->data->object) && $event->data->object->object == 'payment_intent') {
            $intent = $event->data->object;

            $data = $intent->metadata;
            $data_amount = $data['amount_total']*100;
            if(!isset($data['items']) || !isset($data['amount_total']) || $data['amount_total']<=0) {
                echo "UNEXPECTED AMOUNT";
                http_response_code(400); // PHP 5.4 or greater
                exit();
            }
            $items = json_decode($data['items'],true);
            $status = payment_pro_check_items($items, $data['amount_total']);

            $exists = ModelPaymentPro::newInstance()->getPaymentByCode($intent->id, $sourceCode, PAYMENT_PRO_COMPLETED);
            if (isset($exists['pk_i_id'])) {
                echo "ALREADY PAID"; 
                http_response_code(400); // PHP 5.4 or greater
                exit();
            }
            // SAVE TRANSACTION LOG
            $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                $intent->id,
                $data['amount'],
                $data['amount_tax'],
                $intent->amount / 100,
                PAYMENT_PRO_FAILED,
                strtoupper($intent->currency), //currency
                @$data['email'],
                @$data['user']!=''?@$data['user']:$customer->email, //user
                $sourceCode,
                $items
            );
            $extra = $intent->charges->data[0]->payment_method_details->card->brand;
            $extra .= ' ****' . $intent->charges->data[0]->payment_method_details->card->last4;
            $extra .= ': ' .$intent->charges->data[0]->failure_message;
            ModelPaymentPro::newInstance()->setInvoiceExtra($invoiceId, $extra, "Cr Card");

            echo "PROCESSED FAIL";
            http_response_code(200); // PHP 5.4 or greater
            exit();
        }
        echo "MISSING INTENT";
        http_response_code(400); // PHP 5.4 or greater
        exit();
        break;
}

echo "COMPLETED";
http_response_code(200); // PHP 5.4 or greater
exit();
