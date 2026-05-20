<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $account_withdraw_id
 * @property string $type
 * @property string $key
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AccountWithdrawPix extends Model
{
    protected ?string $table = 'account_withdraw_pix';

    protected string $primaryKey = 'account_withdraw_id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    protected array $fillable = ['account_withdraw_id', 'type', 'key'];

    public function withdrawal(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(AccountWithdraw::class, 'account_withdraw_id', 'id');
    }
}
