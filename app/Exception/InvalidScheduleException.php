<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class InvalidScheduleException extends RuntimeException
{
    public function __construct(string $message = 'Scheduled date must be in the future')
    {
        parent::__construct($message, 422);
    }
}
