<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

class RequestIdMiddleware implements MiddlewareInterface
{
    public const REQUEST_ID_KEY = 'X-Request-Id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine(self::REQUEST_ID_KEY) ?: Uuid::uuid4()->toString();

        Context::set(self::REQUEST_ID_KEY, $requestId);

        $response = $handler->handle($request);

        return $response->withHeader(self::REQUEST_ID_KEY, $requestId);
    }
}
