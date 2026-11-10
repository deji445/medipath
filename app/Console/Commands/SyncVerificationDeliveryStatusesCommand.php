<?php

namespace App\Console\Commands;

use App\Services\Verification\BmsDeliveryStatusSynchronizer;
use Illuminate\Console\Command;

final class SyncVerificationDeliveryStatusesCommand extends Command
{
    protected $signature = 'medipath:sync-verification-deliveries {--limit= : Maximum campaigns to check}';

    protected $description = 'Synchronize final BMS delivery statuses without retaining message content or recipients';

    public function handle(BmsDeliveryStatusSynchronizer $synchronizer): int
    {
        $limit = $this->option('limit');
        $summary = $synchronizer->syncPending(is_numeric($limit) ? (int) $limit : null);

        $this->info(sprintf(
            'Checked %d; resolved %d; pending %d; provider failures %d; expired %d.',
            $summary['checked'],
            $summary['resolved'],
            $summary['pending'],
            $summary['failed'],
            $summary['expired'],
        ));

        return self::SUCCESS;
    }
}
