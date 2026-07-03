<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    /**
     * GET /api/cart
     *
     * Returns the authenticated pharmacy user's organization cart,
     * creating an empty one on first access so the response shape is
     * always consistent for the frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensurePharmacyUser($request);

        $cart = Cart::query()->firstOrCreate(
            ['pharmacy_id' => $request->user()->organization_id]
        );

        $this->authorize('view', $cart);

        $cart->load(['items.product.supplier']);

        return response()->json([
            'data' => $cart,
            'total' => $this->cartTotal($cart),
        ]);
    }

    /**
     * POST /api/cart/items
     *
     * Adds a product to the cart, or updates the quantity if it is
     * already present. Quantity is treated as an absolute set value,
     * not an increment, to keep client-side "edit quantity" UI simple.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensurePharmacyUser($request);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::query()->firstOrCreate(
            ['pharmacy_id' => $request->user()->organization_id]
        );

        $this->authorize('update', $cart);

        $item = CartItem::query()->updateOrCreate(
            [
                'cart_id' => $cart->id,
                'product_id' => $validated['product_id'],
            ],
            [
                'quantity' => $validated['quantity'],
            ]
        );

        $cart->load(['items.product.supplier']);

        return response()->json([
            'data' => $cart,
            'total' => $this->cartTotal($cart),
        ], $item->wasRecentlyCreated ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
    }

    /**
     * DELETE /api/cart/items/{cartItem}
     *
     * Removes a single line item from the authenticated user's own
     * organization cart. 404s (via route model binding) if the item
     * belongs to a different cart entirely, and 403s if it belongs
     * to the same organization but the role check fails.
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->ensurePharmacyUser($request);

        $cart = $cartItem->cart;

        $this->authorize('delete', $cart);

        $cartItem->delete();

        $cart->load(['items.product.supplier']);

        return response()->json([
            'data' => $cart,
            'total' => $this->cartTotal($cart),
        ]);
    }

    /**
     * Aborts with 403 if the authenticated user is not a pharmacy
     * user attached to an organization. Centralized here since every
     * action in this controller requires it.
     */
    private function ensurePharmacyUser(Request $request): void
    {
        abort_unless(
            $request->user()?->role === 'pharmacy' && $request->user()->organization_id !== null,
            403,
            'Only pharmacy users may manage a cart.'
        );
    }

    /**
     * Computes the cart total from current product prices. Uses live
     * pricing (not a snapshot) since nothing has been purchased yet —
     * the cart total should always reflect today's catalog price.
     */
    private function cartTotal(Cart $cart): string
    {
        $total = $cart->items->reduce(
            fn (string $carry, CartItem $item) => bcadd(
                $carry,
                bcmul((string) $item->product->price, (string) $item->quantity, 2),
                2
            ),
            '0.00'
        );

        return $total;
    }
}
