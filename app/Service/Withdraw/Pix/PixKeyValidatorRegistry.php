<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix;

use App\Service\Withdraw\Pix\KeyValidator\PixKeyValidatorInterface;
use InvalidArgumentException;

class PixKeyValidatorRegistry
{
    /** @var PixKeyValidatorInterface[] */
    private array $validators;

    public function __construct(
        private readonly KeyValidator\EmailPixKeyValidator $emailValidator,
        private readonly KeyValidator\CpfPixKeyValidator $cpfValidator,
        private readonly KeyValidator\RandomPixKeyValidator $randomValidator,
    ) {
        $this->validators = [
            $this->emailValidator,
            $this->cpfValidator,
            $this->randomValidator,
        ];
    }

    public function validate(string $type, string $key): bool
    {
        foreach ($this->validators as $validator) {
            if ($validator->supports($type)) {
                return $validator->validate($key);
            }
        }
        return true;
    }
}
