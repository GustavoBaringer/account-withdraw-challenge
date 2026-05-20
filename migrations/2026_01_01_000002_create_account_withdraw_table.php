<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateAccountWithdrawTable extends Migration
{
    public function up(): void
    {
        Schema::create('account_withdraw', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('method', 50);
            $table->decimal('amount', 15, 2);
            $table->boolean('scheduled')->default(false)->index();
            $table->dateTime('scheduled_for')->nullable()->index();
            $table->boolean('done')->default(false)->index();
            $table->boolean('error')->default(false);
            $table->string('error_reason', 255)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('account')
                ->onDelete('restrict');

            $table->index(['scheduled', 'done', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw');
    }
}
