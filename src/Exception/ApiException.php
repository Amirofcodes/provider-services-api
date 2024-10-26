<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException
{
    private array $errors = [];
    private string $errorCode;

    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $errors = [],
        int $statusCode = 400,
        \Throwable $previous = null,
        array $headers = []
    ) {
        $this->errorCode = $errorCode;
        $this->errors = $errors;
        parent::__construct($statusCode, $message, $previous, $headers, $statusCode);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
