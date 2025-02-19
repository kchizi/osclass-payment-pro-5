<?php

namespace mywaypay;

class BitcoinReceiver extends ExternalAccount
{
    /**
     * @return string The class URL for this resource. It needs to be special
     *    cased because it doesn't fit into the standard resource pattern.
     */
    public static function classUrl()
    {
        return "/v1/bitcoin/receivers";
    }

    /**
     * @return string The instance URL for this resource. It needs to be special
     *    cased because it doesn't fit into the standard resource pattern.
     */
    public function instanceUrl()
    {
        $result = parent::instanceUrl();
        if ($result) {
            return $result;
        } else {
            $id = $this['id'];
            $id = Util\Util::utf8($id);
            $extn = urlencode($id);
            $base = BitcoinReceiver::classUrl();
            return "$base/$extn";
        }
    }

    /**
     * @param string $id The ID of the Bitcoin Receiver to retrieve.
     * @param array|string|null $opts
     *
     * @return BitcoinReceiver
     */
    public static function retrieve($id, $opts = null)
    {
        return self::_retrieve($id, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return Collection of BitcoinReceivers
     */
    public static function all($params = null, $opts = null)
    {
        return self::_all($params, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return BitcoinReceiver The created Bitcoin Receiver item.
     */
    public static function create($params = null, $opts = null)
    {
        return self::_create($params, $opts);
    }
}
