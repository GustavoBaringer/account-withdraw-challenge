<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix\KeyValidator;

interface PixKeyValidatorInterface
{
    public function validate(string $key): bool;

    public function supports(string $type): bool;
}
