<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix\KeyValidator;

class EmailPixKeyValidator implements PixKeyValidatorInterface
{
    public function validate(string $key): bool
    {
        return (bool) filter_var($key, FILTER_VALIDATE_EMAIL);
    }

    public function supports(string $type): bool
    {
        return $type === 'email';
    }
}
