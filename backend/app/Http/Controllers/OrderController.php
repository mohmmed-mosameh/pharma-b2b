<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     *
     * Pharmacy users see orders they placed (purchases).
     * Supplier users see orders that route to them (sales).
     * Admins see everything.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();

        $query = Order::query()->with(['pharmacy', 'supplier', 'placedBy', 'items.product']);

        $query = match ($user->role) {
            'pharmacy' => $query->where('pharmacy_id', $user->organization_id),
            'supplier' => $query->where('supplier_id', $user->organization_id),
            default => $query, // admin: unscoped
        };

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * GET /api/orders/{order}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(
            $order->load(['pharmacy', 'supplier', 'placedBy', 'items.product'])
        );
    }

    /**
     * POST /api/orders/checkout
     *
     * Converts the authenticated pharmacy's entire cart into one
     * order PER SUPPLIER represented in the cart. Prices are snapshot
     * from the product's current price at the moment of checkout —
     * later product price changes never retroactively alter placed
     * orders. The whole operation is atomic: either every supplier's
     * order is created and the cart is cleared, or nothing happens.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();

        $cart = Cart::query()
            ->where('pharmacy_id', $user->organization_id)
            ->with('items.product')
            ->firstOrFail();

        $ordersBySupplier = DB::transaction(function () use ($cart, $user) {
            $unassigned = $cart->items->first(
                fn ($item) => $item->product->supplier_id === null
            );

            abort_if(
                $unassigned !== null,
                422,
                "Product '{$unassigned?->product?->name}' has no assigned supplier and cannot be ordered."
            );

            /** @var Collection<int, Collection<int, \App\Models\CartItem>> $itemsBySupplier */
            $itemsBySupplier = $cart->items->groupBy(
                fn ($item) => $item->product->supplier_id
            );

            $createdOrders = new EloquentCollection();

            foreach ($itemsBySupplier as $supplierId => $items) {
                $totalAmount = $items->reduce(
                    fn (string $carry, $item) => bcadd(
                        $carry,
                        bcmul((string) $item->product->price, (string) $item->quantity, 2),
                        2
                    ),
                    '0.00'
                );

                $order = Order::create([
                    'pharmacy_id' => $user->organization_id,
                    'supplier_id' => $supplierId,
                    'placed_by' => $user->id,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->product->price,
                    ]);
                }

                $createdOrders->push($order);
            }

            // Clear the cart now that every line item has been
            // converted into an order. Soft-deletes, per model trait.
            $cart->items()->delete();

            return $createdOrders;
        });

        return response()->json([
            'data' => $ordersBySupplier->load(['supplier', 'items.product']),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * PATCH /api/orders/{order}/status
     *
     * Only the supplier fulfilling this order may update its status.
     * Allowed transitions are enforced inside UpdateOrderStatusRequest.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order->update([
            'status' => $request->validated()['status'],
        ]);

        return response()->json(['message' => 'تم تحديث الحالة بنجاح', 'order' => $order]);
    }
}
