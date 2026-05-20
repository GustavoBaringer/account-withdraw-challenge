<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Withdraw;

use App\Service\Withdraw\Pix\PixWithdrawStrategy;
use App\Service\Withdraw\WithdrawMethodFactory;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class WithdrawMethodFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_returns_pix_strategy_for_pix_method(): void
    {
        $pixStrategy = Mockery::mock(PixWithdrawStrategy::class);
        $factory = new WithdrawMethodFactory($pixStrategy);

        $this->assertSame($pixStrategy, $factory->make('PIX'));
        $this->assertSame($pixStrategy, $factory->make('pix'));
    }

    public function test_throws_for_unsupported_method(): void
    {
        $pixStrategy = Mockery::mock(PixWithdrawStrategy::class);
        $factory = new WithdrawMethodFactory($pixStrategy);

        $this->expectException(InvalidArgumentException::class);
        $factory->make('BANK_TRANSFER');
    }
}
