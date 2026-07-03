<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

class CartPolicy
{
    /**
     * Any pharmacy user may view/manage their own organization's cart.
     * There is no "viewAny" concept here — a cart is always scoped to
     * the caller's own organization via the controller, never listed.
     */
    public function view(User $user, Cart $cart): bool
    {
        return $user->role === 'pharmacy'
            && $user->organization_id !== null
            && $user->organization_id === $cart->pharmacy_id;
    }

    /**
     * Only pharmacy users may add items to a cart, and only to their
     * own organization's cart.
     */
    public function update(User $user, Cart $cart): bool
    {
        return $user->role === 'pharmacy'
            && $user->organization_id !== null
            && $user->organization_id === $cart->pharmacy_id;
    }

    /**
     * Removing a cart item follows the same ownership rule as update.
     */
    public function delete(User $user, Cart $cart): bool
    {
        return $user->role === 'pharmacy'
            && $user->organization_id !== null
            && $user->organization_id === $cart->pharmacy_id;
    }
}
