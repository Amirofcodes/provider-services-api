<?php

namespace App\Exception;

class ValidationException extends ApiException
{
    public function __construct(array $violations)
    {
        parent::__construct(
            'Validation failed',
            'VALIDATION_FAILED',
            $violations,
            400
        );
    }
}
