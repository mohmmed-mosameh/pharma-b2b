<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * GET /api/products
     *
     * List products with simple search/filter support.
     * Supported query params:
     *   - search: matches name, generic_name, or company
     *   - category: exact match
     *   - form: exact match
     *   - supplier_id: filter by listing supplier
     *   - per_page: pagination size (default 15, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('supplier');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($form = $request->string('form')->toString()) {
            $query->where('form', $form);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        $products = $query->latest()->paginate($perPage);

        return response()->json($products);
    }

    /**
     * POST /api/products
     *
     * الموردون يضيفون منتجاتهم بسعرها الفعلي، فتُنسب لمنظمتهم (supplier_id
     * من المستخدم المصادَق، وليس من المدخلات، حتى لا يدّعي موردٌ منتجًا
     * لمورد آخر). الصيدليات قد تضيف صنفًا عامًا للكتالوج المشترك أثناء
     * إنشاء مناقصة إذا لم يجدوه؛ في هذه الحالة يبقى supplier_id فارغًا
     * والسعر صفرًا لأنه غير معروف بعد (يُحدَّد لاحقًا من عروض الموردين).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->user()->role === 'supplier') {
            $data['supplier_id'] = $request->user()->organization_id;
        }

        // نُسجّل مَن أضاف الصنف حتى يقدر يحذفه لاحقًا لو غلط، بينما تبقى
        // الأصناف الافتراضية المزروعة (created_by = null) محمية من الحذف.
        $data['created_by'] = $request->user()->id;

        $data['price'] = $data['price'] ?? 0;

        $product = Product::create($data);

        return response()->json(
            $product->load('supplier'),
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * GET /api/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('supplier'));
    }

    /**
     * PUT/PATCH /api/products/{product}
     *
     * Authorization (role + ownership) is enforced inside
     * UpdateProductRequest::authorize(), via ProductPolicy::update().
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Clean up the old file so storage doesn't accumulate orphans.
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json($product->load('supplier'));
    }

    /**
     * DELETE /api/products/{product}
     *
     * Soft-deletes the product (model uses SoftDeletes). Ownership is
     * enforced via the policy, same rule as update().
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        // لا نسمح بحذف صنف مستخدَم فعلًا في مناقصة أو عرض، حتى لا تنكسر
        // المناقصات/التقارير التي تشير إليه.
        if ($product->rfqItems()->exists() || $product->quoteItems()->exists()) {
            abort(422, 'لا يمكن حذف هذا الصنف لأنه مستخدَم في مناقصة أو عرض قائم.');
        }

        $product->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
