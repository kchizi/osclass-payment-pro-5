<?php

namespace mywaypay\Util;

use mywaypay\Error;

class RequestOptions
{
    public $headers;
    public $apiKey;

    public function __construct($key = null, $headers = array())
    {
        $this->apiKey = $key;
        $this->headers = $headers;
    }

    /**
     * Unpacks an options array and merges it into the existing RequestOptions
     * object.
     * @param array|string|null $options a key => value array
     *
     * @return RequestOptions
     */
    public function merge($options)
    {
        $other_options = self::parse($options);
        if ($other_options->apiKey === null) {
            $other_options->apiKey = $this->apiKey;
        }
        $other_options->headers = array_merge($this->headers, $other_options->headers);
        return $other_options;
    }

    /**
     * Unpacks an options array into an RequestOptions object
     * @param array|string|null $options a key => value array
     *
     * @return RequestOptions
     */
    public static function parse($options)
    {
        if ($options instanceof self) {
            return $options;
        }

        if (is_null($options)) {
            return new RequestOptions(null, array());
        }

        if (is_string($options)) {
            return new RequestOptions($options, array());
        }

        if (is_array($options)) {
            $headers = array();
            $key = null;
            if (array_key_exists('api_key', $options)) {
                $key = $options['api_key'];
            }
            if (array_key_exists('idempotency_key', $options)) {
                $headers['Idempotency-Key'] = $options['idempotency_key'];
            }
            if (array_key_exists('mywaypay_account', $options)) {
                $headers['mywaypay-Account'] = $options['mywaypay_account'];
            }
            if (array_key_exists('mywaypay_version', $options)) {
                $headers['mywaypay-Version'] = $options['mywaypay_version'];
            }
            return new RequestOptions($key, $headers);
        }

        $message = 'The second argument to mywaypay API method calls is an '
           . 'optional per-request apiKey, which must be a string, or '
           . 'per-request options, which must be an array. (HINT: you can set '
           . 'a global apiKey by "mywaypay::setApiKey(<apiKey>)")';
        throw new Error\Api($message);
    }
}
