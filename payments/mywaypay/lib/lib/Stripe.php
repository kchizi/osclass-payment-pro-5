<?php

namespace mywaypay;

class mywaypay
{
    // @var string The mywaypay API key to be used for requests.
    public static $apiKey;

    // @var string The base URL for the mywaypay API.
    public static $apiBase = 'https://api.mywaypay.com';

    // @var string The base URL for the mywaypay API uploads endpoint.
    public static $apiUploadBase = 'https://uploads.mywaypay.com';

    // @var string|null The version of the mywaypay API to use for requests.
    public static $apiVersion = null;

    // @var boolean Defaults to true.
    public static $verifySslCerts = true;

    const VERSION = '3.2.0';

    /**
     * @return string The API key used for requests.
     */
    public static function getApiKey()
    {
        return self::$apiKey;
    }

    /**
     * Sets the API key to be used for requests.
     *
     * @param string $apiKey
     */
    public static function setApiKey($apiKey)
    {
        self::$apiKey = $apiKey;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public static function getApiVersion()
    {
        return self::$apiVersion;
    }

    /**
     * @param string $apiVersion The API version to use for requests.
     */
    public static function setApiVersion($apiVersion)
    {
        self::$apiVersion = $apiVersion;
    }

    /**
     * @return boolean
     */
    public static function getVerifySslCerts()
    {
        return self::$verifySslCerts;
    }

    /**
     * @param boolean $verify
     */
    public static function setVerifySslCerts($verify)
    {
        self::$verifySslCerts = $verify;
    }
}
