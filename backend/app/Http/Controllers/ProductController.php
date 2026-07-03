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
     * Only suppliers may create products. supplier_id is always set
     * from the authenticated user's own organization — never trusted
     * from request input, to prevent a supplier listing products
     * under someone else's organization.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $data['supplier_id'] = $request->user()->organization_id;

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

        $product->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
