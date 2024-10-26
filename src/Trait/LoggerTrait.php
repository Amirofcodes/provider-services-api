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
        $this->logger->info($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}
