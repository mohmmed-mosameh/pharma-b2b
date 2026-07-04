<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rfq;
use ArPHP\I18N\Arabic;
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
     *   composer require khaled.alshamaa/ar-php
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

        $html = view('reports.rfq', [
            'rfq'           => $rfq,
            'winningQuote'  => $winningQuote,
            'purchaseOrder' => $purchaseOrder,
            'generatedAt'   => now()->format('Y-m-d H:i'),
        ])->render();

        // dompdf لا يدعم تشكيل الحروف العربية (ربط الحروف ببعضها) ولا
        // يعيد ترتيبها بصريًا، فتظهر كل حرف منفصلًا وبترتيب مقلوب. نمرّر
        // الـ HTML الناتج عبر ArPHP ليحوّل كل مقطع عربي إلى الشكل المرئي
        // الصحيح (حروف متصلة ومرتّبة) قبل تسليمه لـ dompdf، مع تعطيل التفاف
        // الأسطر التلقائي (max_chars كبير جدًا) وترك الأرقام كما هي (hindo=false)
        // لأن التفاف الأسطر والتفريغ من دومبيدف بالكاد ذا صلة بتخطيط HTML/CSS.
        $arabic = new Arabic();
        $html   = $arabic->utf8Glyphs($html, 100000, false);

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $filename = 'rfq-report-' . $rfq->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }
}
