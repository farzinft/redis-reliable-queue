<?php

namespace Reliable\Exceptions;

use Exception;
use Throwable;

class ClassNotFoundException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }

}