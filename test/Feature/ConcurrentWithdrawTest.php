<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use App\Exception\InsufficientFundsException;
use App\Mail\MailerInterface;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Service\Withdraw\WithdrawMethodFactory;
use App\Service\Withdraw\WithdrawMethodInterface;
use App\Service\WithdrawService;
use Hyperf\Coroutine\Parallel;
use Hyperf\DbConnection\Db;
use HyperfTest\HttpTestCase;
use Mockery;
use Psr\Log\LoggerInterface;

/**
 * Tests that concurrent withdrawals never produce a negative balance.
 *
 * The SELECT ... FOR UPDATE in WithdrawService ensures only one coroutine
 * can debit the account at a time when balance is insufficient.
 */
class ConcurrentWithdrawTest extends HttpTestCase
{
    private const ACCOUNT_ID = '660e8400-e29b-41d4-a716-446655440099';

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        Db::table('account')->insert([
            'id' => self::ACCOUNT_ID,
            'name' => 'Concurrent Test Account',
            'balance' => '100.00',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function test_concurrent_withdrawals_never_make_balance_negative(): void
    {
        $successCount = 0;
        $failureCount = 0;

        $parallel = new Parallel(10);

        for ($i = 0; $i < 10; $i++) {
            $parallel->add(function () use (&$successCount, &$failureCount, $i) {
                try {
                    $response = $this->client->post(
                        '/account/' . self::ACCOUNT_ID . '/balance/withdraw',
                        [
                            'method' => 'PIX',
                            'pix' => ['type' => 'email', 'key' => "user{$i}@test.com"],
                            'amount' => 20.00,
                            'schedule' => null,
                        ]
                    );
                    if ($response->getStatusCode() === 201) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }
                } catch (\Throwable) {
                    $failureCount++;
                }
            });
        }

        $parallel->wait();

        $account = Account::find(self::ACCOUNT_ID);
        $balance = (float) $account->balance;

        // Balance must never go below zero
        $this->assertGreaterThanOrEqual(0.0, $balance, 'Balance went negative!');

        // With 10 concurrent withdrawals of R$20 each from a R$100 balance,
        // exactly 5 should succeed and the balance should be exactly 0
        $this->assertEquals(5, $successCount, "Expected 5 successful withdrawals, got {$successCount}");
        $this->assertEquals(5, $failureCount, "Expected 5 failed withdrawals, got {$failureCount}");
        $this->assertEquals('0.00', $account->balance, "Expected final balance 0.00");
    }
}
