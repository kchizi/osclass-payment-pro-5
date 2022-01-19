<?php

namespace mywaypay;

class AuthenticationErrorTest extends TestCase
{
    public function testInvalidCredentials()
    {
        mywaypay::setApiKey('invalid');
        try {
            Customer::create();
        } catch (Error\Authentication $e) {
            $this->assertSame(401, $e->getHttpStatus());
        }
    }
}
