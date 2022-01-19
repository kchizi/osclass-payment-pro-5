<?php

namespace mywaypay\Util;

use mywaypay\mywaypayObject;

abstract class Util
{
    /**
     * Whether the provided array (or other) is a list rather than a dictionary.
     *
     * @param array|mixed $array
     * @return boolean True if the given object is a list.
     */
    public static function isList($array)
    {
        if (!is_array($array)) {
            return false;
        }

      // TODO: generally incorrect, but it's correct given mywaypay's response
        foreach (array_keys($array) as $k) {
            if (!is_numeric($k)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Recursively converts the PHP mywaypay object to an array.
     *
     * @param array $values The PHP mywaypay object to convert.
     * @return array
     */
    public static function convertmywaypayObjectToArray($values)
    {
        $results = array();
        foreach ($values as $k => $v) {
            // FIXME: this is an encapsulation violation
            if ($k[0] == '_') {
                continue;
            }
            if ($v instanceof mywaypayObject) {
                $results[$k] = $v->__toArray(true);
            } elseif (is_array($v)) {
                $results[$k] = self::convertmywaypayObjectToArray($v);
            } else {
                $results[$k] = $v;
            }
        }
        return $results;
    }

    /**
     * Converts a response from the mywaypay API to the corresponding PHP object.
     *
     * @param array $resp The response from the mywaypay API.
     * @param array $opts
     * @return mywaypayObject|array
     */
    public static function convertTomywaypayObject($resp, $opts)
    {
        $types = array(
            'account' => 'mywaypay\\Account',
            'alipay_account' => 'mywaypay\\AlipayAccount',
            'bank_account' => 'mywaypay\\BankAccount',
            'balance_transaction' => 'mywaypay\\BalanceTransaction',
            'card' => 'mywaypay\\Card',
            'charge' => 'mywaypay\\Charge',
            'coupon' => 'mywaypay\\Coupon',
            'customer' => 'mywaypay\\Customer',
            'dispute' => 'mywaypay\\Dispute',
            'list' => 'mywaypay\\Collection',
            'invoice' => 'mywaypay\\Invoice',
            'invoiceitem' => 'mywaypay\\InvoiceItem',
            'event' => 'mywaypay\\Event',
            'file' => 'mywaypay\\FileUpload',
            'token' => 'mywaypay\\Token',
            'transfer' => 'mywaypay\\Transfer',
            'plan' => 'mywaypay\\Plan',
            'recipient' => 'mywaypay\\Recipient',
            'refund' => 'mywaypay\\Refund',
            'subscription' => 'mywaypay\\Subscription',
            'fee_refund' => 'mywaypay\\ApplicationFeeRefund',
            'bitcoin_receiver' => 'mywaypay\\BitcoinReceiver',
            'bitcoin_transaction' => 'mywaypay\\BitcoinTransaction',
        );
        if (self::isList($resp)) {
            $mapped = array();
            foreach ($resp as $i) {
                array_push($mapped, self::convertTomywaypayObject($i, $opts));
            }
            return $mapped;
        } elseif (is_array($resp)) {
            if (isset($resp['object']) && is_string($resp['object']) && isset($types[$resp['object']])) {
                $class = $types[$resp['object']];
            } else {
                $class = 'mywaypay\\mywaypayObject';
            }
            return $class::constructFrom($resp, $opts);
        } else {
            return $resp;
        }
    }

    /**
     * @param string|mixed $value A string to UTF8-encode.
     *
     * @return string|mixed The UTF8-encoded string, or the object passed in if
     *    it wasn't a string.
     */
    public static function utf8($value)
    {
        if (is_string($value) && mb_detect_encoding($value, "UTF-8", true) != "UTF-8") {
            return utf8_encode($value);
        } else {
            return $value;
        }
    }
}
