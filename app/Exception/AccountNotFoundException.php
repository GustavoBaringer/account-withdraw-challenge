<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class AccountNotFoundException extends RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Account not found: {$accountId}", 404);
    }
}
