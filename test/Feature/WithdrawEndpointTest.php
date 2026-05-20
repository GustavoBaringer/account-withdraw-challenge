<?php

declare(strict_types=1);

namespace HyperfTest\Feature;

use Hyperf\Testing\Client;
use HyperfTest\HttpTestCase;

class WithdrawEndpointTest extends HttpTestCase
{
    private const TEST_ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const TEST_ACCOUNT_EMPTY_ID = '550e8400-e29b-41d4-a716-446655440001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
        $this->seedTestAccounts();
    }

    public function test_immediate_pix_withdraw_returns_201(): void
    {
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 100.00,
                'schedule' => null,
            ]
        );

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('PIX', $data['method']);
        $this->assertTrue($data['done']);
        $this->assertFalse($data['scheduled']);
    }

    public function test_scheduled_pix_withdraw_returns_201_with_pending_state(): void
    {
        $futureDate = date('Y-m-d H:i', strtotime('+1 hour'));
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 50.00,
                'schedule' => $futureDate,
            ]
        );

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($data['scheduled']);
        $this->assertFalse($data['done']);
    }

    public function test_insufficient_funds_returns_422(): void
    {
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_EMPTY_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 1.00,
                'schedule' => null,
            ]
        );

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('Insufficient funds', $data['message']);
    }

    public function test_past_schedule_returns_422(): void
    {
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 10.00,
                'schedule' => '2020-01-01 10:00',
            ]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_account_not_found_returns_404(): void
    {
        $response = $this->client->post(
            '/account/nonexistent-uuid/balance/withdraw',
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 10.00,
                'schedule' => null,
            ]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_invalid_method_returns_422(): void
    {
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'BANK_TRANSFER',
                'amount' => 10.00,
            ]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_zero_amount_returns_422(): void
    {
        $response = $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 0,
            ]
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_immediate_withdraw_debits_account_balance(): void
    {
        $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 100.00,
                'schedule' => null,
            ]
        );

        $account = \App\Model\Account::find(self::TEST_ACCOUNT_ID);
        $this->assertEquals('900.00', $account->balance);
    }

    public function test_scheduled_withdraw_does_not_debit_balance(): void
    {
        $futureDate = date('Y-m-d H:i', strtotime('+2 hours'));

        $this->client->post(
            "/account/{$this::TEST_ACCOUNT_ID}/balance/withdraw",
            [
                'method' => 'PIX',
                'pix' => ['type' => 'email', 'key' => 'receiver@email.com'],
                'amount' => 100.00,
                'schedule' => $futureDate,
            ]
        );

        $account = \App\Model\Account::find(self::TEST_ACCOUNT_ID);
        $this->assertEquals('1000.00', $account->balance);
    }

    protected function seedTestAccounts(): void
    {
        \Hyperf\DbConnection\Db::table('account')->insert([
            [
                'id' => self::TEST_ACCOUNT_ID,
                'name' => 'Conta Teste',
                'balance' => '1000.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => self::TEST_ACCOUNT_EMPTY_ID,
                'name' => 'Conta Saldo Zero',
                'balance' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
