<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    /**
     * GET /api/quotes
     *
     * يرى المورد فقط عروضه الخاصة المقدمة على أي مناقصة.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        abort_unless($request->user()->role === 'supplier', 403);

        $perPage = min((int) $request->input('per_page', 15), 100);

        $quotes = Quote::query()
            ->where('supplier_id', $request->user()->organization_id)
            ->with(['rfq', 'quoteItems.product'])
            ->latest()
            ->paginate($perPage);

        return response()->json(['data' => $quotes]);
    }

    /**
     * GET /api/quotes/{quote}
     *
     * الصيدلية لا يجوز أن ترى الأسعار الحقيقية لعرض لم يُفتح مظروفه
     * بعد، وإلا التفّت على آلية المظاريف المغلقة عبر هذا المسار
     * مباشرة. المورد صاحب العرض يرى سعره الحقيقي دائمًا (هي بياناته).
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load(['rfq', 'supplier', 'creator', 'quoteItems.product']);

        if ($request->user()->role === 'pharmacy' && $quote->opened_at === null) {
            $quote->quoteItems->each(function ($item) {
                $item->makeHidden(['unit_price', 'discount_percentage', 'net_unit_price']);
            });
        }

        return response()->json([
            'data' => $quote,
        ]);
    }

    /**
     * POST /api/rfqs/{rfq}/quotes
     *
     * يقدم المورد عرض سعر شامل لكل أصناف المناقصة دفعة واحدة.
     * كل بند في الطلب (items.*.rfq_item_id) يُربط بصنفه الأصلي
     * (RfqItem) لاستخراج product_id الصحيح عند إنشاء QuoteItem،
     * لضمان التطابق التام مع ما طلبته الصيدلية ولو تكرر نفس المنتج
     * في مناقصات مختلفة بكميات مختلفة.
     */
    public function store(StoreQuoteRequest $request, Rfq $rfq): JsonResponse
    {
        $user = $request->user();

        $rfqItemsById = $rfq->rfqItems()->get()->keyBy('id');

        $quote = DB::transaction(function () use ($request, $rfq, $user, $rfqItemsById) {
            $quote = Quote::create([
                'rfq_id' => $rfq->id,
                'supplier_id' => $user->organization_id,
                'created_by' => $user->id,
                'status' => 'submitted',
            ]);

            foreach ($request->validated()['items'] as $item) {
                /** @var RfqItem $rfqItem */
                $rfqItem = $rfqItemsById->get($item['rfq_item_id']);

                QuoteItem::create([
                    'quote_id'            => $quote->id,
                    'product_id'          => $rfqItem->product_id,
                    'unit_price'          => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    // لو المورد ما حددش الكمية المتوفرة، نفترض قدرته على
                    // توريد الكمية كاملة كما طلبتها الصيدلية، بدل ترك
                    // العمود فارغًا (وهو NOT NULL أصلًا في قاعدة البيانات).
                    'available_qty'       => $item['available_qty'] ?? $rfqItem->quantity,
                    'delivery_days'       => $item['delivery_days'],
                    'notes'               => $item['notes'] ?? null,
                ]);
            }

            return $quote;
        });

        return response()->json([
            'data' => $quote->load(['rfq', 'quoteItems.product']),
        ], JsonResponse::HTTP_CREATED);
    }
}
