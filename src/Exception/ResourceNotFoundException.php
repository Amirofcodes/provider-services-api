<?php

namespace App\Exception;

class ResourceNotFoundException extends ApiException
{
    public function __construct(string $resource = '', string $id = '')
    {
        $message = $resource ? sprintf('%s with id %s not found', $resource, $id) : 'Resource not found';
        parent::__construct($message, 'RESOURCE_NOT_FOUND', [], 404);
    }
}
