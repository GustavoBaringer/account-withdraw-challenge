<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Model\AccountWithdraw;
use App\Service\WithdrawService;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

#[Crontab(
    rule: '*/5 * * * * *',
    name: 'ProcessScheduledWithdrawals',
    memo: 'Process pending scheduled PIX withdrawals'
)]
class ProcessScheduledWithdrawsCrontab
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly WithdrawService $withdrawService,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        $this->logger->debug('Crontab: starting scheduled withdrawals processing');

        $ids = $this->claimPendingWithdrawalIds();

        if (empty($ids)) {
            $this->logger->debug('Crontab: no pending scheduled withdrawals');
            return;
        }

        $processed = 0;
        $errors = 0;

        foreach ($ids as $id) {
            try {
                $withdrawal = AccountWithdraw::with('pix')->find($id);
                if (!$withdrawal || $withdrawal->done) {
                    continue;
                }

                $this->withdrawService->processScheduledWithdrawal($withdrawal);
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->logger->error('Crontab: error processing withdrawal', [
                    'withdrawal_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->logger->info('Crontab: scheduled withdrawals batch complete', [
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Claim a batch of pending withdrawal IDs using SELECT FOR UPDATE SKIP LOCKED.
     *
     * SKIP LOCKED means concurrent cron workers on different app replicas will
     * each pick a disjoint set of rows — no duplication, no blocking.
     *
     * The transaction is kept short: we only hold locks long enough to claim IDs.
     * The actual debit logic runs in a separate transaction inside WithdrawService.
     *
     * @return string[]
     */
    private function claimPendingWithdrawalIds(): array
    {
        return Db::transaction(function () {
            $rows = Db::select(
                'SELECT id FROM account_withdraw
                 WHERE scheduled = 1
                   AND done = 0
                   AND scheduled_for <= ?
                 ORDER BY scheduled_for ASC
                 LIMIT ?
                 FOR UPDATE SKIP LOCKED',
                [date('Y-m-d H:i:s'), self::BATCH_SIZE]
            );

            return array_column($rows, 'id');
        });
    }
}
