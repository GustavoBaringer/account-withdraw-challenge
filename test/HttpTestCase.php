<?php

declare(strict_types=1);

namespace HyperfTest;

use Hyperf\DbConnection\Db;
use Hyperf\Testing\Client;
use PHPUnit\Framework\TestCase;

abstract class HttpTestCase extends TestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        $this->client = make(Client::class);
    }

    protected function refreshDatabase(): void
    {
        Db::unprepared('SET FOREIGN_KEY_CHECKS = 0');
        Db::table('account_withdraw_pix')->truncate();
        Db::table('account_withdraw')->truncate();
        Db::table('account')->truncate();
        Db::unprepared('SET FOREIGN_KEY_CHECKS = 1');
    }
}
