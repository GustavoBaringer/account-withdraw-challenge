<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Crontab\ProcessScheduledWithdrawsCrontab;
use App\Mail\MailerInterface;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Service\Withdraw\WithdrawMethodFactory;
use App\Service\WithdrawService;
use Hyperf\DbConnection\Db;
use HyperfTest\HttpTestCase;
use Mockery;
use Psr\Log\LoggerInterface;

class CrontabProcessingTest extends HttpTestCase
{
    private const ACCOUNT_ID = '770e8400-e29b-41d4-a716-446655440099';

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        Db::table('account')->insert([
            'id' => self::ACCOUNT_ID,
            'name' => 'Cron Test Account',
            'balance' => '500.00',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createWithdrawService(): WithdrawService
    {
        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')->andReturnNull();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info', 'debug', 'warning', 'error')->andReturnNull();

        return make(WithdrawService::class, [
            'mailer' => $mailer,
            'logger' => $logger,
        ]);
    }

    public function test_crontab_processes_due_scheduled_withdrawal(): void
    {
        $withdrawalId = 'cron-test-id-001';

        Db::table('account_withdraw')->insert([
            'id' => $withdrawalId,
            'account_id' => self::ACCOUNT_ID,
            'method' => 'PIX',
            'amount' => '100.00',
            'scheduled' => 1,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'done' => 0,
            'error' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Db::table('account_withdraw_pix')->insert([
            'account_withdraw_id' => $withdrawalId,
            'type' => 'email',
            'key' => 'receiver@email.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createWithdrawService();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info', 'debug')->andReturnNull();

        $crontab = new ProcessScheduledWithdrawsCrontab($service, $logger);
        $crontab->execute();

        $withdrawal = AccountWithdraw::find($withdrawalId);
        $this->assertTrue($withdrawal->done);
        $this->assertFalse($withdrawal->error);
        $this->assertNotNull($withdrawal->processed_at);

        $account = Account::find(self::ACCOUNT_ID);
        $this->assertEquals('400.00', $account->balance);
    }

    public function test_crontab_marks_error_when_insufficient_funds(): void
    {
        $withdrawalId = 'cron-test-id-002';

        Db::table('account_withdraw')->insert([
            'id' => $withdrawalId,
            'account_id' => self::ACCOUNT_ID,
            'method' => 'PIX',
            'amount' => '9999.00',
            'scheduled' => 1,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'done' => 0,
            'error' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Db::table('account_withdraw_pix')->insert([
            'account_withdraw_id' => $withdrawalId,
            'type' => 'email',
            'key' => 'receiver@email.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createWithdrawService();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info', 'debug', 'warning', 'error')->andReturnNull();

        $crontab = new ProcessScheduledWithdrawsCrontab($service, $logger);
        $crontab->execute();

        $withdrawal = AccountWithdraw::find($withdrawalId);
        $this->assertTrue($withdrawal->done);
        $this->assertTrue($withdrawal->error);
        $this->assertEquals('insufficient_funds', $withdrawal->error_reason);

        $account = Account::find(self::ACCOUNT_ID);
        $this->assertEquals('500.00', $account->balance);
    }

    public function test_crontab_does_not_process_future_scheduled_withdrawals(): void
    {
        $withdrawalId = 'cron-test-id-003';

        Db::table('account_withdraw')->insert([
            'id' => $withdrawalId,
            'account_id' => self::ACCOUNT_ID,
            'method' => 'PIX',
            'amount' => '50.00',
            'scheduled' => 1,
            'scheduled_for' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'done' => 0,
            'error' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Db::table('account_withdraw_pix')->insert([
            'account_withdraw_id' => $withdrawalId,
            'type' => 'email',
            'key' => 'future@email.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createWithdrawService();
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info', 'debug')->andReturnNull();

        $crontab = new ProcessScheduledWithdrawsCrontab($service, $logger);
        $crontab->execute();

        $withdrawal = AccountWithdraw::find($withdrawalId);
        $this->assertFalse($withdrawal->done);

        $account = Account::find(self::ACCOUNT_ID);
        $this->assertEquals('500.00', $account->balance);
    }
}
