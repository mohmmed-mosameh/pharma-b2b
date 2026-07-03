<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseExpiredRfqs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rfqs:close-expired';

    /**
     * @var string
     */
    protected $description = 'يغلق تلقائيًا كل المناقصات (RFQs) التي تجاوزت موعد quotes_deadline_at وما زالت بحالة open.';

    public function handle(): int
    {
        $closedCount = 0;

        Rfq::query()
            ->where('status', 'open')
            ->where('quotes_deadline_at', '<=', now())
            ->chunkById(100, function ($rfqs) use (&$closedCount) {
                DB::transaction(function () use ($rfqs, &$closedCount) {
                    foreach ($rfqs as $rfq) {
                        $rfq->update(['status' => 'closed']);
                        $closedCount++;
                    }
                });
            });

        $this->info("تم إغلاق {$closedCount} مناقصة منتهية الصلاحية.");

        return self::SUCCESS;
    }
}
