<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientFundsException;
use App\Exception\InvalidScheduleException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        if ($throwable instanceof ValidationException) {
            return $this->jsonResponse($response, 422, [
                'message' => 'Validation failed',
                'errors' => $throwable->errors(),
            ]);
        }

        if ($throwable instanceof AccountNotFoundException) {
            return $this->jsonResponse($response, 404, [
                'message' => $throwable->getMessage(),
            ]);
        }

        if ($throwable instanceof InsufficientFundsException || $throwable instanceof InvalidScheduleException) {
            return $this->jsonResponse($response, 422, [
                'message' => $throwable->getMessage(),
            ]);
        }

        $this->logger->error('Unhandled exception', [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile() . ':' . $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
        ]);

        return $this->jsonResponse($response, 500, [
            'message' => 'Internal server error',
        ]);
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    private function jsonResponse(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream(json_encode($data, JSON_UNESCAPED_UNICODE)));
    }
}
