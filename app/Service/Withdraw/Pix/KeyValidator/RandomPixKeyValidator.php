<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix\KeyValidator;

class RandomPixKeyValidator implements PixKeyValidatorInterface
{
    public function validate(string $key): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key);
    }

    public function supports(string $type): bool
    {
        return $type === 'random';
    }
}
