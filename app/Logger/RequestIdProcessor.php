<?php

declare(strict_types=1);

namespace App\Logger;

use App\Middleware\RequestIdMiddleware;
use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = Context::get(RequestIdMiddleware::REQUEST_ID_KEY, 'cli');

        return $record->with(extra: array_merge($record->extra, [
            'request_id' => $requestId,
        ]));
    }
}
