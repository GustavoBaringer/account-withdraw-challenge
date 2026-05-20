<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Pix;

use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Service\Withdraw\WithdrawMethodInterface;
use Hyperf\Validation\ValidationException;

class PixWithdrawStrategy implements WithdrawMethodInterface
{
    public function __construct(
        private readonly PixKeyValidatorRegistry $keyValidatorRegistry,
    ) {}

    public function registerDetails(AccountWithdraw $withdrawal, array $payload): void
    {
        $type = $payload['pix']['type'];
        $key = $payload['pix']['key'];

        if (!$this->keyValidatorRegistry->validate($type, $key)) {
            throw new \InvalidArgumentException("Invalid PIX key for type '{$type}': {$key}");
        }

        AccountWithdrawPix::create([
            'account_withdraw_id' => $withdrawal->id,
            'type' => $type,
            'key' => $key,
        ]);
    }
}
