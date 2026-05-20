<?php

declare(strict_types=1);

namespace App\Service\Withdraw;

use App\Model\Account;
use App\Model\AccountWithdraw;

interface WithdrawMethodInterface
{
    /**
     * Register withdrawal-specific data (e.g. PIX details) for a given withdrawal record.
     */
    public function registerDetails(AccountWithdraw $withdrawal, array $payload): void;
}
