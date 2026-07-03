<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRatingRequest;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\SupplierRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierRatingController extends Controller
{
    /**
     * POST /api/purchase-orders/{purchaseOrder}/rating
     *
     * الصيدلية تقيّم المورد بعد اكتمال التوريد (status = completed).
     * مسموح بتقييم واحد فقط لكل أمر شراء (unique على purchase_order_id).
     */
    public function store(StoreSupplierRatingRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $rating = SupplierRating::create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id'       => $purchaseOrder->supplier_id,
            'pharmacy_id'       => $purchaseOrder->pharmacy_id,
            'rated_by'          => $request->user()->id,
            'rating'            => $request->validated()['rating'],
            'comment'           => $request->validated()['comment'] ?? null,
        ]);

        return response()->json([
            'data' => $rating->load(['supplier', 'pharmacy', 'ratedBy']),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * GET /api/suppliers/{organization}/ratings
     *
     * عرض تقييمات مورد معين مع متوسطه الكلي.
     * متاح لأي مستخدم مسجّل (الصيدليات تستعرضه قبل منح العطاء).
     */
    public function index(Request $request, Organization $organization): JsonResponse
    {
        abort_unless(
            $organization->type === 'supplier',
            404,
            'المنظمة المطلوبة ليست مورّدًا.'
        );

        $perPage = min((int) $request->input('per_page', 15), 100);

        $ratings = SupplierRating::query()
            ->where('supplier_id', $organization->id)
            ->with(['pharmacy', 'ratedBy'])
            ->latest()
            ->paginate($perPage);

        $avgRating = SupplierRating::query()
            ->where('supplier_id', $organization->id)
            ->avg('rating');

        return response()->json([
            'data'       => $ratings,
            'avg_rating' => $avgRating ? round((float) $avgRating, 2) : null,
        ]);
    }
}
