<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;
use Ramsey\Uuid\Uuid;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        Db::table('account')->insert([
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'Conta Teste',
                'balance' => '1000.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440001',
                'name' => 'Conta Saldo Zero',
                'balance' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
