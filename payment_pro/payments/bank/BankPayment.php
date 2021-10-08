<?php

    class BankPayment implements iPayment
    {

        public function __construct()
        {
        }

        public static function button($products, $extra = null)
        {

            $amount = 0;
            $showButton = true;
            foreach ($products as $p) {
                $amount += $p['amount'] * $p['quantity'] * ((100 + $p['tax']) / 100);
                if (substr($p['id'], 0, 3) != "WLT" && osc_get_preference('bank_only_packs', 'payment_pro') == 1) {
                    $showButton = false;
                }
            }

            $r = rand(0, 1000);
            $extra['random'] = $r;
            $data_create = payment_pro_set_custom(array('products' => $products, 'extra' => $extra));
            if ($showButton) {
                ?>

                <li class="payment bank-btn">
                    <div id="bank_start" style="cursor:pointer;cursor:hand" onclick="javascript:bank_pay();">
                        <button class="btn btn-success btn-lg"><i class="bi bi-bank"></i> <?php _e('Bank transfer','payment_pro'); ?></button>
                    </div>
                    <div id="bank_loading" style="cursor:pointer;cursor:hand; display: none;" >
                        <img src="<?php echo PAYMENT_PRO_URL; ?>payments/bank/loading-large.gif">
                    </div>
                    <div class="blockchain stage-loading" style="text-align:center">
                    </div>

                </li>
                <script type="text/javascript">
                    function bank_pay() {
                        $(".payment .btn").hide();
                        $("#bank_loading").show();
                        setTimeout(openBankDialog, 150);
                        $.post(
                            '<?php echo osc_base_url(true); ?>',
                            {
                                page: 'ajax',
                                action: 'runhook',
                                hook: 'banktransfer',
                                data: '<?php echo $data_create?>'
                            },
                            function (response) {
                                $("#bank_info_span").text(response.msg);
                                $("#bank_loading").hide();
                            },
                            'json'
                        );

                        return false;
                    };

                </script>

                <?php
            }
        }
        public static function dialogJS() { 
            $checkoutURL = osc_get_preference('checkoutURL', 'ppe_attributes');
            $baseURL = osc_get_preference('baseURL', 'ppe_attributes');
            $url = osc_route_url('payment-pro-cart-drop');
            if (!empty($checkoutURL) && $checkoutURL == osc_base_url()) {   
                $url = str_replace($checkoutURL, $baseURL, $url);
            }

        ?>
<style type="text/css">
#bank-button-row {
    text-align: center;}
</style>
<div id="bank-dialog" title="<?php _e('Bank Transfer', 'payment_pro'); ?>" class="" style="display: none;">
    <div id="bank_info" style="cursor:pointer;cursor:hand;" >
        <span id="bank_info_span"></span>
    </div>
    <div id="bank-button-row">
        <button id="bank_continue" class="btn btn-success" style="cursor:pointer;cursor:hand; ">
            <?php _e('Continue', 'payment_pro'); ?>
        </button>
    </div>
</div>
<script type="text/javascript">
    $('#bank_continue').click(function(ev) {
        $(location).attr('href','<?php echo $url;?>');
    });
    $('div#bank-dialog').on('dialogclose', function(event) {
        //$(location).attr('href','<?php echo $url;?>');
        $(".payment .btn").show();
    });
    function openBankDialog() {
        var bk = $('#bank-dialog');
        bk.dialog('open');
    }

    $(document).ready(function(){
        $("#bank-dialog").dialog({
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
</script>
<?php
        }
        public static function recurringButton($products, $extra = null)
        {
        }

        public static function processPayment()
        {

            if (Params::getParam('test') == true) {
                return PAYMENT_PRO_FAILED;
            }
            $extra = explode("?", Params::getParam('extra'));
            $data = payment_pro_get_custom(str_replace("@", "+", $extra[0]));
            unset($extra);
            $data['items'] = ModelPaymentPro::newInstance()->getPending(@$data['tx']);
            $transaction_hash = Params::getParam('transaction_hash');
            $value_in_btc = Params::getParam('value') / 100000000;
            $bitcoin_address = ModelPaymentPro::newInstance()->pendingExtra(@$data['tx']);
            $address = Params::getParam('address');
            if (empty($data['items'])) {
                echo "S1";
                return PAYMENT_PRO_FAILED;
            }
            if (osc_get_preference('currency', 'payment_pro') == 'BTC') {
                $status = payment_pro_check_items($data['items'], $value_in_btc);
            } else {
                $status = payment_pro_check_items_blockchain($data['items'], $value_in_btc, $data['xrate']);
            }
            if ($address == '' || !isset($bitcoin_address['s_extra']) || $bitcoin_address['s_extra'] != $address) {
                echo "S2";
                return PAYMENT_PRO_FAILED;
            }

            $amount = 0;
            $amount_tax = 0;
            foreach ($data['items'] as $k => $v) {
                $data['items'][$k]['amount'] = $v['amount'] / 1000000;
                $data['items'][$k]['amount_total'] = $v['amount_total'] / 1000000;
                $data['items'][$k]['amount_tax'] = $v['amount_tax'] / 1000000;
                $data['items'][$k]['tax'] = $v['tax'] / 100;
                $amount += $data['items'][$k]['amount'];
                $amount_tax += $data['items'][$k]['amount_tax'];
            }

            $exists = ModelPaymentPro::newInstance()->getPaymentByCode($transaction_hash, 'BLOCKCHAIN', PAYMENT_PRO_COMPLETED);
            if (isset($exists['pk_i_id'])) {
                return PAYMENT_PRO_ALREADY_PAID;
            }
            foreach ($data['items'] as $item) {
                $tmp = explode("-", $item['id']);
                $item['item_id'] = $tmp[count($tmp) - 1];
            }
            if ((is_numeric(Params::getParam('confirmations')) && Params::getParam('confirmations') >= osc_get_preference('blockchain_confirmations', 'payment_pro')) || Params::getParam('anonymous') == true) {
                // SAVE TRANSACTION LOG
                $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                    $transaction_hash, // transaction code
                    $amount,
                    $amount_tax,
                    $value_in_btc, //amount
                    $status,
                    'BTC', //currency
                    $data['email'], // payer's email
                    $data['user'], //user
                    'BLOCKCHAIN',
                    $data['items']
                );

                if ($status == PAYMENT_PRO_COMPLETED) {
                    foreach ($data['items'] as $item) {
                        osc_run_hook('payment_pro_item_paid', $item, $data, $invoiceId);
                    }
                    ModelPaymentPro::newInstance()->deletePending($data['tx']);
                    ModelPaymentPro::newInstance()->updatePendingInvoiceExtra($data['tx'], $invoiceId, 'BLOCKCHAIN');
                }

                return PAYMENT_PRO_COMPLETED;
            } else {
                // Maybe we could do something here (the payment was correct, but it didn't get enought confirmations yet)
                echo "S3";
                return PAYMENT_PRO_PENDING;
            }

            echo "S4";
            return $status = PAYMENT_PRO_FAILED;
        }

        public static function ajaxCreate()
        {

            ob_get_clean();


            $data = payment_pro_get_custom(Params::getParam("data"));
            if (!isset($data['products']) || !isset($data['extra'])) {
                print json_encode(array('error' => 1, 'msg' => __('Missing data', 'payment_pro')));
                die;
            }
            $products = $data['products'];
            $extra = $data['extra'];
            //$tx_id = ModelPaymentPro::newInstance()->pendingInvoice($products, 10);
            //$extra['tx'] = $tx_id;

            $amount = 0;
            $amount_tax = 0;
            $amount_total = 0;
            $curr = osc_get_preference('currency', 'payment_pro');

            foreach($products as $p) {
                $amt = $p['amount']*$p['quantity'];
                $amount += $amt;
                $amount_tax += $amt*($p['tax']/100);
                $amount_total += $amt*((100+$p['tax'])/100);
                $curr = $p['currency'];
                $symbol = $p['symbol'];
                $tmp = explode("-", $p['id']);
                $products[$p['id']]['item_id'] = $tmp[count($tmp) - 1];
            }

            $code = self::generateCode(6);
            //ModelPaymentPro::newInstance()->setPendingExtra($tx_id, $code, 'BANK');

            $invoiceId = ModelPaymentPro::newInstance()->saveInvoice(
                $code, // transaction code
                $amount, //subtotal
                $amount_tax, //tax
                $amount_total, //total
                PAYMENT_PRO_PENDING,
                $curr,
                $extra['email'], // payer's email
                $extra['user'], //user
                'BANK',
                $products
            );

            if (isset($extra['details'])) {
                ModelPaymentPro::newInstance()->setInvoiceExtra($invoiceId, json_encode($extra['details']));
            }

            $msg = str_replace(
                array(
                    '{BANK_ACCOUNT}',
                    '{CODE}',
                    '{AMOUNT}'
                ),
                array(
                    osc_get_preference('bank_account', 'payment_pro'),
                    $code,
                    payment_pro_format_price($amount_total*1000000, $symbol)
                ),
                osc_get_preference('bank_msg', 'payment_pro')
            );
            
            print json_encode(array('error' => 0, 'msg' => $msg));
            die;
        }

        private static function generateCode($length = null)
        {

            $code = strtoupper(str_ireplace(array('i', '0','1'), array('l', 'o', 'l'), osc_genRandomPassword($length)));
            $dao = ModelPaymentPro::newInstance();
            $table = $dao->getTable_invoice();
            while (true) {
                $dao->dao->select("s_code");
                $dao->dao->from($table);
                $dao->dao->where("s_code", $code);
                $dao->dao->where("s_source", "BANK");
                $dao->dao->limit(1);
                $result = $dao->dao->get();
                if ($result) {
                    if ($result->numRows() > 0) {
                        $code = strtoupper(str_ireplace(array('i', '0','1'), array('l', 'o', 'l'), osc_genRandomPassword($length)));
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
            return $code;
        }
    }


