<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuoteScoringService
{
    /**
     * يحسب النقاط الوزنية الإجمالية لكل عرض مقدَّم على المناقصة،
     * ويعيد المجموعة مرتبةً تنازليًا بالنقاط (الأعلى نقاطًا أولًا).
     *
     * المعادلة المعتمدة: تناسب خطي لكل معيار.
     *
     * معيار "أقل سعر":
     *   score = (min_price / quote_price) × weight
     *   — الأرخص يأخذ كامل الوزن، الأغلى يأخذ أقل.
     *
     * معيار "أسرع توريد":
     *   score = (min_days / quote_days) × weight
     *   — الأسرع يأخذ كامل الوزن، الأبطأ يأخذ أقل.
     *
     * معيار "أعلى تقييم سابق":
     *   score = (quote_avg_rating / 5) × weight
     *   — متوسط التقييمات السابقة للمورد على مقياس 1-5.
     *   — مورد بلا تقييمات سابقة يأخذ 0 في هذا المعيار.
     *
     * معيار "شركة معتمدة":
     *   score = is_verified ? weight : 0
     *   — ثنائي: معتمد يأخذ كامل الوزن، غير معتمد يأخذ صفرًا.
     *
     * @param  Rfq                     $rfq
     * @param  Collection<int, Quote>  $quotes  العروض المفتوحة فقط (opened_at IS NOT NULL)
     * @return Collection<int, Quote>  نفس العروض مع إضافة خاصية ->score لكل واحدة
     */
    public function score(Rfq $rfq, Collection $quotes): Collection
    {
        if ($quotes->isEmpty()) {
            return $quotes;
        }

        // --- تجميع المتغيرات العالمية اللازمة للتناسب ---

        // متوسط أسعار كل عرض (مجموع net_unit_price للأصناف / عددها)
        // نستخدم متوسط السعر لأن الأصناف في عرض واحد قد تختلف أسعارها.
        $avgPrices = $quotes->mapWithKeys(function (Quote $quote) {
            $avg = $quote->quoteItems->avg(
                fn ($item) => (float) $item->net_unit_price
            );
            return [$quote->id => $avg ?? PHP_FLOAT_MAX];
        });

        $avgDays = $quotes->mapWithKeys(function (Quote $quote) {
            $avg = $quote->quoteItems->avg('delivery_days');
            return [$quote->id => $avg ?? PHP_INT_MAX];
        });

        $minPrice = $avgPrices->min();
        $minDays  = $avgDays->min();

        // تقييمات الموردين: متوسط كل مورد من supplier_ratings
        $supplierIds = $quotes->pluck('supplier_id')->unique();

        $avgRatings = DB::table('supplier_ratings')
            ->whereIn('supplier_id', $supplierIds)
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->pluck(DB::raw('AVG(rating)'), 'supplier_id')
            ->map(fn ($r) => (float) $r);

        // أوزان هذه المناقصة تحديدًا (قد تختلف من مناقصة لأخرى)
        $wPrice    = (float) $rfq->score_weight_price;
        $wDelivery = (float) $rfq->score_weight_delivery;
        $wRating   = (float) $rfq->score_weight_rating;
        $wVerified = (float) $rfq->score_weight_verified;

        // --- حساب النقاط لكل عرض ---

        return $quotes
            ->map(function (Quote $quote) use (
                $avgPrices, $avgDays, $avgRatings,
                $minPrice, $minDays,
                $wPrice, $wDelivery, $wRating, $wVerified
            ) {
                $quoteAvgPrice = $avgPrices->get($quote->id, PHP_FLOAT_MAX);
                $quoteAvgDays  = $avgDays->get($quote->id, PHP_INT_MAX);

                // معيار 1: أقل سعر (تناسب عكسي — أرخص = أعلى نقاط)
                $priceScore = $minPrice > 0 && $quoteAvgPrice > 0
                    ? ($minPrice / $quoteAvgPrice) * $wPrice
                    : 0.0;

                // معيار 2: أسرع توريد (تناسب عكسي — أسرع = أعلى نقاط)
                $deliveryScore = $minDays > 0 && $quoteAvgDays > 0
                    ? ($minDays / $quoteAvgDays) * $wDelivery
                    : 0.0;

                // معيار 3: أعلى تقييم سابق (تناسب طردي على مقياس 5)
                $avgRating   = $avgRatings->get($quote->supplier_id, 0.0);
                $ratingScore = ($avgRating / 5.0) * $wRating;

                // معيار 4: شركة معتمدة (ثنائي)
                $isVerified    = (bool) ($quote->supplier->is_verified ?? false);
                $verifiedScore = $isVerified ? $wVerified : 0.0;

                $totalScore = round(
                    $priceScore + $deliveryScore + $ratingScore + $verifiedScore,
                    2
                );

                // نُضيف النقاط كـ dynamic attribute على موديل العرض
                // حتى تظهر في الـ JSON response مباشرة
                $quote->score              = $totalScore;
                $quote->score_breakdown    = [
                    'price'    => round($priceScore, 2),
                    'delivery' => round($deliveryScore, 2),
                    'rating'   => round($ratingScore, 2),
                    'verified' => round($verifiedScore, 2),
                ];
                $quote->supplier_avg_rating = round($avgRating, 2);

                return $quote;
            })
            ->sortByDesc('score')
            ->values();
    }
}
