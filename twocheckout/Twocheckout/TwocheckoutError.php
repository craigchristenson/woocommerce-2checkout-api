<?php

class Twocheckout_Error extends Exception
{
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}