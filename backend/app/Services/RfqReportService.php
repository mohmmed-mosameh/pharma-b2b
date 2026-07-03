<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rfq;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class RfqReportService
{
    /**
     * يولّد تقرير PDF للمناقصة المُرسَّاة ويعيده كـ Response جاهز
     * للتحميل المباشر من المتصفح.
     *
     * يتطلب هذا الـ Service:
     *   composer require barryvdh/laravel-dompdf
     *
     * والـ Blade view:
     *   resources/views/reports/rfq.blade.php
     */
    public function generate(Rfq $rfq): Response
    {
        $rfq->loadMissing([
            'pharmacy',
            'creator',
            'rfqItems.product',
            'quotes' => fn ($q) => $q->where('status', 'awarded')
                ->with(['supplier', 'quoteItems.product']),
            'purchaseOrders.supplier',
        ]);

        $winningQuote   = $rfq->quotes->first();
        $purchaseOrder  = $rfq->purchaseOrders->first();

        $pdf = Pdf::loadView('reports.rfq', [
            'rfq'           => $rfq,
            'winningQuote'  => $winningQuote,
            'purchaseOrder' => $purchaseOrder,
            'generatedAt'   => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'portrait');

        $filename = 'rfq-report-' . $rfq->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }
}
