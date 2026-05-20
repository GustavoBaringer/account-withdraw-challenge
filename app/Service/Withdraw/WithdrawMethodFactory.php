<?php

declare(strict_types=1);

namespace App\Service\Withdraw;

use App\Service\Withdraw\Pix\PixWithdrawStrategy;
use InvalidArgumentException;

class WithdrawMethodFactory
{
    public function __construct(
        private readonly PixWithdrawStrategy $pixStrategy,
    ) {}

    public function make(string $method): WithdrawMethodInterface
    {
        return match (strtoupper($method)) {
            'PIX' => $this->pixStrategy,
            default => throw new InvalidArgumentException("Unsupported withdrawal method: {$method}"),
        };
    }
}
