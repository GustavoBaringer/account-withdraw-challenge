<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Insufficient funds for this withdrawal', 422);
    }
}
