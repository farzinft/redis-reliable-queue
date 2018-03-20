<?php

namespace Reliable\Exceptions;

use Exception;
use Throwable;

class MethodNotFoundException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }

}