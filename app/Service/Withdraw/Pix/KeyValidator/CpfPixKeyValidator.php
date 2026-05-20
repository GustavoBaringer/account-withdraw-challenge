<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix\KeyValidator;

class CpfPixKeyValidator implements PixKeyValidatorInterface
{
    public function validate(string $key): bool
    {
        $cpf = preg_replace('/\D/', '', $key);
        return strlen($cpf) === 11;
    }

    public function supports(string $type): bool
    {
        return $type === 'cpf';
    }
}
