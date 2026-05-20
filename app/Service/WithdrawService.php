<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientFundsException;
use App\Exception\InvalidScheduleException;
use App\Mail\MailerInterface;
use App\Mail\Notification\WithdrawCompletedNotification;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Service\Withdraw\WithdrawMethodFactory;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class WithdrawService
{
    public function __construct(
        private readonly WithdrawMethodFactory $methodFactory,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function withdraw(string $accountId, array $data): AccountWithdraw
    {
        $amount = $data['amount'];
        $method = strtoupper($data['method']);
        $schedule = $data['schedule'] ?? null;

        if ($schedule !== null) {
            $scheduledAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $schedule);
            if (!$scheduledAt || $scheduledAt <= new \DateTimeImmutable()) {
                throw new InvalidScheduleException();
            }
        }

        return Db::transaction(function () use ($accountId, $amount, $method, $schedule, $data) {
            /** @var Account|null $account */
            $account = Account::lockForUpdate()->find($accountId);

            if (!$account) {
                throw new AccountNotFoundException($accountId);
            }

            $withdrawalId = Uuid::uuid4()->toString();

            $isScheduled = $schedule !== null;

            if (!$isScheduled) {
                $this->checkBalance($account, $amount);
                $this->debitBalance($account, $amount);
            }

            /** @var AccountWithdraw $withdrawal */
            $withdrawal = AccountWithdraw::create([
                'id' => $withdrawalId,
                'account_id' => $accountId,
                'method' => $method,
                'amount' => (string) $amount,
                'scheduled' => $isScheduled,
                'scheduled_for' => $isScheduled ? $schedule . ':00' : null,
                'done' => !$isScheduled,
                'error' => false,
                'processed_at' => !$isScheduled ? date('Y-m-d H:i:s') : null,
            ]);

            $strategy = $this->methodFactory->make($method);
            $strategy->registerDetails($withdrawal, $data);

            if (!$isScheduled) {
                $withdrawal->load('pix');
                $this->sendConfirmationEmail($account, $withdrawal);
                $this->logger->info('Immediate withdrawal processed', [
                    'withdrawal_id' => $withdrawalId,
                    'account_id' => $accountId,
                    'amount' => $amount,
                    'method' => $method,
                ]);
            } else {
                $this->logger->info('Withdrawal scheduled', [
                    'withdrawal_id' => $withdrawalId,
                    'account_id' => $accountId,
                    'amount' => $amount,
                    'scheduled_for' => $schedule,
                ]);
            }

            return $withdrawal;
        });
    }

    public function processScheduledWithdrawal(AccountWithdraw $withdrawal): void
    {
        Db::transaction(function () use ($withdrawal) {
            // Re-check withdrawal status inside the transaction to guard against race conditions.
            $fresh = AccountWithdraw::lockForUpdate()->find($withdrawal->id);
            if (!$fresh || $fresh->done) {
                return;
            }

            /** @var Account $account */
            $account = Account::lockForUpdate()->find($fresh->account_id);

            try {
                $this->checkBalance($account, $fresh->amount);
                $this->debitBalance($account, $fresh->amount);

                $fresh->done = true;
                $fresh->error = false;
                $fresh->processed_at = date('Y-m-d H:i:s');
                $fresh->save();

                $fresh->load('pix');
                $this->sendConfirmationEmail($account, $fresh);

                $this->logger->info('Scheduled withdrawal processed successfully', [
                    'withdrawal_id' => $fresh->id,
                    'account_id' => $fresh->account_id,
                    'amount' => $fresh->amount,
                ]);
            } catch (InsufficientFundsException $e) {
                $fresh->done = true;
                $fresh->error = true;
                $fresh->error_reason = 'insufficient_funds';
                $fresh->processed_at = date('Y-m-d H:i:s');
                $fresh->save();

                $this->logger->warning('Scheduled withdrawal failed: insufficient funds', [
                    'withdrawal_id' => $fresh->id,
                    'account_id' => $fresh->account_id,
                    'amount' => $fresh->amount,
                    'balance' => $account->balance,
                ]);
            }
        });
    }

    private function checkBalance(Account $account, string|float $amount): void
    {
        if (bccomp((string) $amount, $account->balance, 2) > 0) {
            throw new InsufficientFundsException();
        }
    }

    private function debitBalance(Account $account, string|float $amount): void
    {
        $newBalance = bcsub($account->balance, (string) $amount, 2);
        $account->balance = $newBalance;
        $account->save();
    }

    private function sendConfirmationEmail(Account $account, AccountWithdraw $withdrawal): void
    {
        try {
            $notification = new WithdrawCompletedNotification($account, $withdrawal);
            $this->mailer->send($notification);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send withdrawal confirmation email', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
