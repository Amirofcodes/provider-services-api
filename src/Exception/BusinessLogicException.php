<?php

namespace App\Exception;

class BusinessLogicException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'BUSINESS_LOGIC_ERROR', array $errors = [])
    {
        parent::__construct($message, $errorCode, $errors, 422);
    }
}
