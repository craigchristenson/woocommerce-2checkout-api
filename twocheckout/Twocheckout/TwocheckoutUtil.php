<?php

class Twocheckout_Util
{

    static function return_resp($contents) {
        $arrayObject = self::objectToArray($contents);
        self::checkError($arrayObject);
        return $arrayObject;
    }

    public static function objectToArray($object)
    {
        $object = json_decode($object, true);
        $array=array();
        foreach($object as $member=>$data)
        {
            $array[$member]=$data;
        }
        return $array;
    }

    public static function checkError($contents)
    {
        if (isset($contents['exception'])) {
            throw new Twocheckout_Error($contents['exception']['errorMsg'], $contents['exception']['errorCode']);
        }
    }

}