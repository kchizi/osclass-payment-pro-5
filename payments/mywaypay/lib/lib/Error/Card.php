<?php

namespace mywaypay\Error;

class Card extends Base
{
    public function __construct(
        $message,
        $mywaypayParam,
        $mywaypayCode,
        $httpStatus,
        $httpBody,
        $jsonBody,
        $httpHeaders = null
    ) {
        parent::__construct($message, $httpStatus, $httpBody, $jsonBody, $httpHeaders);
        $this->mywaypayParam = $mywaypayParam;
        $this->mywaypayCode = $mywaypayCode;
    }

    public function getmywaypayCode()
    {
        return $this->mywaypayCode;
    }

    public function getmywaypayParam()
    {
        return $this->mywaypayParam;
    }
}
