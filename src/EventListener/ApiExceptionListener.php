<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Log the exception
        $this->logger->error($exception->getMessage(), [
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : null
        ]);

        // Handle ValidationFailedException
        if ($exception instanceof ValidationFailedException) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[] = [
                    'property' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode()
                ];
            }

            $response = $this->createResponse(
                'Validation failed',
                Response::HTTP_BAD_REQUEST,
                'VALIDATION_ERROR',
                $errors
            );
        }
        // Handle our custom ApiException
        elseif ($exception instanceof ApiException) {
            $response = $this->createResponse(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getErrorCode(),
                $exception->getErrors()
            );
        }
        // Handle HttpException
        elseif ($exception instanceof HttpException) {
            $response = $this->createResponse(
                $exception->getMessage(),
                $exception->getStatusCode(),
                'HTTP_ERROR'
            );
        }
        // Handle all other exceptions
        else {
            $response = $this->createResponse(
                $this->environment === 'dev' ? $exception->getMessage() : 'Internal server error',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'INTERNAL_ERROR',
                $this->environment === 'dev' ? ['trace' => $exception->getTraceAsString()] : []
            );
        }

        $event->setResponse($response);
    }

    private function createResponse(
        string $message,
        int $status,
        string $errorCode,
        array $errors = []
    ): JsonResponse {
        $data = [
            'status' => 'error',
            'message' => $message,
            'errorCode' => $errorCode
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        return new JsonResponse($data, $status);
    }
}
