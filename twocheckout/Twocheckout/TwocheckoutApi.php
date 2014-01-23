<?php

abstract class TwocheckoutApi
{
    public static $sid;
    public static $privateKey;
    public static $apiUrl;
    public static $error;
    const VERSION = '0.0.1';

    static function setCredentials($sid, $privateKey, $mode='')
    {
        self::$sid = $sid;
        self::$privateKey = $privateKey;
        if ($mode == 'sandbox') {
            self::$apiUrl = 'https://sandbox.2checkout.com/checkout/api/1/'.$sid.'/rs/authService';
        } else {
            self::$apiUrl = 'https://www.2checkout.com/checkout/api/1/'.$sid.'/rs/authService';
        }
    }
}

require(dirname(__FILE__) . '/TwocheckoutRequester.php');
require(dirname(__FILE__) . '/TwocheckoutCharge.php');
require(dirname(__FILE__) . '/TwocheckoutUtil.php');
require(dirname(__FILE__) . '/TwocheckoutError.php');