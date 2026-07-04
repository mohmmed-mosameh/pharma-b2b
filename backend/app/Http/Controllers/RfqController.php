<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AwardRfqRequest;
use App\Http\Requests\StoreRfqRequest;
use App\Models\Quote;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Services\QuoteScoringService;
use App\Services\RfqReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RfqController extends Controller
{
    public function __construct(
        private readonly QuoteScoringService $scoringService,
        private readonly RfqReportService    $reportService,
    ) {}
    /**
     * GET /api/rfqs
     *
     * - الصيدلية: ترى فقط مناقصاتها.
     * - المورد: يرى فقط المناقصات المفتوحة لتقديم العروض (open).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Rfq::class);

        $user = $request->user();

        $query = Rfq::query()->with(['pharmacy', 'creator', 'rfqItems.product']);

        $query = match ($user->role) {
            'pharmacy' => $query->where('pharmacy_id', $user->organization_id),
            'supplier' => $query->where('status', 'open'),
            default => $query,
        };

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json([
            'data' => $query->latest()->paginate($perPage),
        ]);
    }

    /**
     * GET /api/rfqs/{rfq}
     */
    public function show(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('view', $rfq);

        return response()->json([
            'data' => $rfq->load(['pharmacy', 'creator', 'rfqItems.product']),
        ]);
    }

    /**
     * POST /api/rfqs
     *
     * تُنشأ المناقصة بحالة "open" مباشرة، مع كل أصنافها (RfqItem)
     * في عملية واحدة atomic عبر DB Transaction.
     */
    public function store(StoreRfqRequest $request): JsonResponse
    {
        $user = $request->user();

        $rfq = DB::transaction(function () use ($request, $user) {
            $rfq = Rfq::create([
                'title' => $request->validated()['title'],
                'pharmacy_id' => $user->organization_id,
                'created_by' => $user->id,
                'status' => 'open',
                'quotes_deadline_at' => $request->validated()['quotes_deadline_at'],
                'opening_at' => $request->validated()['opening_at'],
            ]);

            foreach ($request->validated()['items'] as $item) {
                RfqItem::create([
                    'rfq_id' => $rfq->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $rfq;
        });

        return response()->json([
            'data' => $rfq->load(['pharmacy', 'creator', 'rfqItems.product']),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * GET /api/rfqs/{rfq}/quotes
     *
     * يعرض قائمة العروض المقدمة على المناقصة، مع إخفاء الحقول
     * السعرية (unit_price, discount_percentage, net_unit_price)
     * للعروض التي لم تُفتح بعد (opened_at = null)، طبقًا للتصميم:
     * "العروض تبقى مخفية السعر لحد وقت فتح المظاريف"، وحتى داخل
     * مرحلة الفتح، كل عرض يُفتح بشكل فردي بضغطة منفصلة من الصيدلية.
     * متاح أيضًا والمناقصة لا تزال "open"، حتى تقدر الصيدلية تتابع
     * أسماء الشركات التي قدّمت عروضًا أولًا بأول قبل إغلاق باب التقديم
     * (الأسعار تبقى مخفية دائمًا لحد ما opened_at يصير معبّى).
     */
    public function openQuotes(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('view', $rfq);

        abort_unless(
            $request->user()->role === 'pharmacy'
                && $request->user()->organization_id === $rfq->pharmacy_id,
            403,
            'فقط الصيدلية صاحبة المناقصة يمكنها عرض المظاريف.'
        );

        // أول مرة تُزار فيها هذه الشاشة بعد الإغلاق، تتحول الحالة من
        // "closed" إلى "pending_opening" لتعكس أن الصيدلية دخلت
        // مرحلة فتح المظاريف، لكن لم تفتح أي عرض بعد بالضرورة.
        if ($rfq->status === 'closed') {
            $rfq->update(['status' => 'pending_opening']);
        }

        $rfq->load([
            'quotes' => fn ($query) => $query->whereIn('status', ['submitted', 'awarded', 'rejected'])
                ->with(['supplier', 'creator', 'quoteItems.product']),
        ]);

        // إخفاء الحقول السعرية للعروض غير المفتوحة بعد، حتى لا
        // يصل السعر للواجهة الأمامية أصلًا قبل أوانه (وليس فقط
        // إخفاءً شكليًا في الواجهة).
        $rfq->quotes->each(function (Quote $quote) {
            if ($quote->opened_at === null) {
                $quote->quoteItems->each(function ($item) {
                    $item->makeHidden(['unit_price', 'discount_percentage', 'net_unit_price']);
                });
            }
        });

        $allOpened = $rfq->quotes->every(fn (Quote $q) => $q->opened_at !== null);

        // احسب النقاط فقط لما تكون كل المظاريف مفتوحة، لأن المقارنة
        // بين عرض مفتوح وآخر مخفي غير عادلة ومضللة للصيدلية.
        $scoredQuotes = $allOpened
            ? $this->scoringService->score($rfq, $rfq->quotes)
            : $rfq->quotes;

        // تصنيف كل شركة: "معتمد" إذا فازت بـ 3 مناقصات أو أكثر (عدد أوامر
        // الشراء المنسوبة لها)، وإلا "جديد". نرفق العدد مع بيانات الشركة
        // ليعرضه الفرونت في عمود الملاحظات بشاشة فتح المظاريف.
        $winsBySupplier = PurchaseOrder::query()
            ->whereIn('supplier_id', $scoredQuotes->pluck('supplier_id')->unique()->filter()->all())
            ->selectRaw('supplier_id, COUNT(*) as wins')
            ->groupBy('supplier_id')
            ->pluck('wins', 'supplier_id');

        $scoredQuotes->each(function (Quote $quote) use ($winsBySupplier) {
            if ($quote->supplier) {
                $wins = (int) ($winsBySupplier[$quote->supplier_id] ?? 0);
                $quote->supplier->setAttribute('wins_count', $wins);
                $quote->supplier->setAttribute('is_approved', $wins >= 3);
            }
        });

        return response()->json([
            'data' => $rfq->setRelation('quotes', $scoredQuotes),
            'all_opened' => $allOpened,
        ]);
    }

    /**
     * POST /api/rfqs/{rfq}/quotes/{quote}/open
     *
     * فتح مظروف عرض واحد بعينه. عند فتح آخر عرض متبقٍ في المناقصة،
     * تتحول حالة المناقصة تلقائيًا إلى "opened" (كل المظاريف مفتوحة
     * الآن)، تمهيدًا لتفعيل شاشة الترسية في الواجهة.
     */
    public function openSingleQuote(Request $request, Rfq $rfq, Quote $quote): JsonResponse
    {
        $this->authorize('view', $rfq);

        abort_unless(
            $request->user()->role === 'pharmacy'
                && $request->user()->organization_id === $rfq->pharmacy_id,
            403,
            'فقط الصيدلية صاحبة المناقصة يمكنها فتح المظاريف.'
        );

        abort_unless(
            $quote->rfq_id === $rfq->id,
            404,
            'هذا العرض لا ينتمي لهذه المناقصة.'
        );

        abort_unless(
            in_array($rfq->status, ['pending_opening', 'opened'], true),
            422,
            'لا يمكن فتح المظاريف إلا بعد إغلاق باب تقديم العروض والدخول لمرحلة الفتح.'
        );

        if ($quote->opened_at === null) {
            $quote->update(['opened_at' => now()]);
        }

        $allOpened = Quote::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', 'submitted')
            ->whereNull('opened_at')
            ->doesntExist();

        if ($allOpened && $rfq->status !== 'opened') {
            $rfq->update(['status' => 'opened']);
        }

        return response()->json([
            'data' => $quote->load(['supplier', 'quoteItems.product']),
            'all_opened' => $allOpened,
        ]);
    }

    /**
     * POST /api/rfqs/{rfq}/award
     *
     * ترسية العطاء: تختار الصيدلية عرضًا فائزًا (Quote)، فيتحول
     * النظام تلقائيًا إلى:
     *   - تحديث حالة الـ Quote الفائز إلى "awarded".
     *   - تحديث باقي العروض على نفس المناقصة إلى "rejected".
     *   - تحديث حالة المناقصة نفسها إلى "awarded".
     *   - إنشاء PurchaseOrder نهائي مرتبط بالـ Rfq والـ Quote والمورد،
     *     بإجمالي يُحسب من جمع (unit_price × available_qty) لكل
     *     بنود العرض الفائز.
     * العملية بالكامل atomic: تفشل كلها أو تنجح كلها معًا.
     */
    public function award(AwardRfqRequest $request, Rfq $rfq): JsonResponse
    {
        $winningQuote = Quote::query()
            ->where('id', $request->validated()['quote_id'])
            ->with('quoteItems')
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(
            $winningQuote->opened_at !== null,
            422,
            'لا يمكن الترسية على عرض لم يُفتح مظروفه بعد.'
        );

        $purchaseOrder = DB::transaction(function () use ($rfq, $winningQuote) {
            // يُحسب الإجمالي من السعر الصافي بعد الخصم (net_unit_price)
            // وليس unit_price الخام، حتى يتطابق المبلغ النهائي في أمر
            // الشراء مع السعر الفعلي الذي رأته الصيدلية في شاشة فتح
            // المظاريف وشاشة الترسية.
            $totalAmount = $winningQuote->quoteItems->reduce(
                fn (string $carry, $item) => bcadd(
                    $carry,
                    bcmul($item->net_unit_price, (string) $item->available_qty, 2),
                    2
                ),
                '0.00'
            );

            $purchaseOrder = PurchaseOrder::create([
                'rfq_id' => $rfq->id,
                'quote_id' => $winningQuote->id,
                'supplier_id' => $winningQuote->supplier_id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            $winningQuote->update(['status' => 'awarded']);

            Quote::query()
                ->where('rfq_id', $rfq->id)
                ->where('id', '!=', $winningQuote->id)
                ->where('status', 'submitted')
                ->update(['status' => 'rejected']);

            $rfq->update(['status' => 'awarded']);

            return $purchaseOrder;
        });

        return response()->json([
            'data' => $purchaseOrder->load(['rfq', 'quote.supplier', 'supplier']),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * GET /api/rfqs/{rfq}/report
     *
     * تحميل تقرير PDF للمناقصة المُرسَّاة. متاح فقط للصيدلية صاحبة
     * المناقصة وبعد اكتمال مرحلة الترسية.
     */
    public function downloadReport(Request $request, Rfq $rfq): Response
    {
        $this->authorize('view', $rfq);

        abort_unless(
            $request->user()->role === 'pharmacy'
                && $request->user()->organization_id === $rfq->pharmacy_id,
            403,
            'فقط الصيدلية صاحبة المناقصة يمكنها تحميل التقرير.'
        );

        abort_unless(
            $rfq->status === 'awarded',
            422,
            'التقرير متاح فقط بعد ترسية المناقصة.'
        );

        return $this->reportService->generate($rfq);
    }
}
