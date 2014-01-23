<?php

class Twocheckout_Charge extends TwocheckoutApi
{

    public static function auth($params=array())
    {
        $request = new Twocheckout_Requester();
        $result = $request->do_call($params);
        return Twocheckout_Util::return_resp($result);
    }

}