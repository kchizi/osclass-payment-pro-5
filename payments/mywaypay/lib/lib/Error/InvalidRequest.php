<?php

namespace mywaypay\Error;

class InvalidRequest extends Base
{
    public function __construct(
        $message,
        $mywaypayParam,
        $httpStatus = null,
        $httpBody = null,
        $jsonBody = null,
        $httpHeaders = null
    ) {
        parent::__construct($message, $httpStatus, $httpBody, $jsonBody, $httpHeaders);
        $this->mywaypayParam = $mywaypayParam;
    }

    public function getmywaypayParam()
    {
        return $this->mywaypayParam;
    }
}
