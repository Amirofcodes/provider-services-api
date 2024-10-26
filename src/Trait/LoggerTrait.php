<?php

namespace App\Trait;

use Psr\Log\LoggerInterface;

trait LoggerTrait
{
    private LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->enrichContext($context, 'INFO'));
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->enrichContext($context, 'ERROR'));
    }

    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->enrichContext($context, 'DEBUG'));
    }

    private function enrichContext(array $context, string $level): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1];

        $request = null;
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $request = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];
        }

        return array_merge($context, [
            'log_metadata' => [
                'timestamp' => (new \DateTime())->format('c'),
                'request_id' => uniqid('req_', true),
                'level' => $level,
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'caller' => [
                    'class' => $caller['class'] ?? 'unknown',
                    'method' => $caller['function'] ?? 'unknown',
                    'line' => $caller['line'] ?? 0,
                ],
                'request' => $request,
            ]
        ]);
    }
}
