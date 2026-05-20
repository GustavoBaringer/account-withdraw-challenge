<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Withdraw\Pix;

use App\Service\Withdraw\Pix\KeyValidator\EmailPixKeyValidator;
use PHPUnit\Framework\TestCase;

class EmailPixKeyValidatorTest extends TestCase
{
    private EmailPixKeyValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EmailPixKeyValidator();
    }

    public function test_supports_email_type(): void
    {
        $this->assertTrue($this->validator->supports('email'));
        $this->assertFalse($this->validator->supports('cpf'));
        $this->assertFalse($this->validator->supports('random'));
    }

    public function test_validates_valid_email(): void
    {
        $this->assertTrue($this->validator->validate('fulano@email.com'));
        $this->assertTrue($this->validator->validate('user+tag@domain.co.uk'));
    }

    public function test_rejects_invalid_email(): void
    {
        $this->assertFalse($this->validator->validate('not-an-email'));
        $this->assertFalse($this->validator->validate('@domain.com'));
        $this->assertFalse($this->validator->validate('user@'));
        $this->assertFalse($this->validator->validate(''));
    }
}
