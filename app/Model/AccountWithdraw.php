<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $id
 * @property string $account_id
 * @property string $method
 * @property string $amount
 * @property bool $scheduled
 * @property string|null $scheduled_for
 * @property bool $done
 * @property bool $error
 * @property string|null $error_reason
 * @property string|null $processed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AccountWithdraw extends Model
{
    protected ?string $table = 'account_withdraw';

    protected string $primaryKey = 'id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'account_id',
        'method',
        'amount',
        'scheduled',
        'scheduled_for',
        'done',
        'error',
        'error_reason',
        'processed_at',
    ];

    protected array $casts = [
        'scheduled' => 'boolean',
        'done' => 'boolean',
        'error' => 'boolean',
        'amount' => 'string',
    ];

    public function account(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    public function pix(): \Hyperf\Database\Model\Relations\HasOne
    {
        return $this->hasOne(AccountWithdrawPix::class, 'account_withdraw_id', 'id');
    }
}
