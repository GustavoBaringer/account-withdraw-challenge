<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientFundsException;
use App\Exception\InvalidScheduleException;
use App\Mail\MailerInterface;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Service\Withdraw\WithdrawMethodFactory;
use App\Service\Withdraw\WithdrawMethodInterface;
use App\Service\WithdrawService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WithdrawServiceTest extends TestCase
{
    private MockInterface $methodFactory;
    private MockInterface $mailer;
    private MockInterface $logger;
    private WithdrawService $service;

    protected function setUp(): void
    {
        $this->methodFactory = Mockery::mock(WithdrawMethodFactory::class);
        $this->mailer = Mockery::mock(MailerInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        $this->logger->shouldReceive('info', 'debug', 'warning', 'error')->andReturnNull()->byDefault();

        $this->service = new WithdrawService(
            $this->methodFactory,
            $this->mailer,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_throws_invalid_schedule_for_past_date(): void
    {
        $this->expectException(InvalidScheduleException::class);

        $this->service->withdraw('some-account', [
            'method' => 'PIX',
            'pix' => ['type' => 'email', 'key' => 'test@email.com'],
            'amount' => 100.00,
            'schedule' => '2020-01-01 10:00',
        ]);
    }

    public function test_insufficient_funds_on_immediate_withdraw(): void
    {
        $this->expectException(InsufficientFundsException::class);

        $account = new Account();
        $account->id = '550e8400-e29b-41d4-a716-446655440000';
        $account->balance = '50.00';

        Account::shouldReceive('lockForUpdate->find')
            ->with('550e8400-e29b-41d4-a716-446655440000')
            ->andReturn($account);

        $this->service->withdraw('550e8400-e29b-41d4-a716-446655440000', [
            'method' => 'PIX',
            'pix' => ['type' => 'email', 'key' => 'test@email.com'],
            'amount' => 100.00,
            'schedule' => null,
        ]);
    }

    public function test_process_scheduled_withdrawal_marks_error_on_insufficient_funds(): void
    {
        $account = new Account();
        $account->id = '550e8400-e29b-41d4-a716-446655440000';
        $account->balance = '0.00';

        Account::shouldReceive('lockForUpdate->find')
            ->with('550e8400-e29b-41d4-a716-446655440000')
            ->andReturn($account);

        $withdrawal = Mockery::mock(AccountWithdraw::class)->makePartial();
        $withdrawal->id = 'some-id';
        $withdrawal->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $withdrawal->amount = '100.00';
        $withdrawal->shouldReceive('save')->andReturn(true);

        $this->logger->shouldReceive('warning')->once();

        $this->service->processScheduledWithdrawal($withdrawal);

        $this->assertTrue($withdrawal->done);
        $this->assertTrue($withdrawal->error);
        $this->assertEquals('insufficient_funds', $withdrawal->error_reason);
    }
}
