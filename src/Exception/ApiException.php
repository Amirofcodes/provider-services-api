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

class ResourceNotFoundException extends ApiException
{
    public function __construct(string $resource = '', string $id = '')
    {
        $message = $resource ? sprintf('%s with id %s not found', $resource, $id) : 'Resource not found';
        parent::__construct($message, 'RESOURCE_NOT_FOUND', [], 404);
    }
}

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

class BusinessLogicException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'BUSINESS_LOGIC_ERROR', array $errors = [])
    {
        parent::__construct($message, $errorCode, $errors, 422);
    }
}
