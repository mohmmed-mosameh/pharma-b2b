<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Both pharmacy and supplier roles may list orders; the controller
     * is responsible for scoping the query (pharmacy sees purchases,
     * supplier sees sales). This gate just blocks unrelated roles
     * (e.g. a supplier with no organization) from hitting the endpoint.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['pharmacy', 'supplier'], true)
            && $user->organization_id !== null;
    }

    /**
     * A single order may be viewed by the pharmacy that placed it or
     * the supplier fulfilling it — never by an unrelated organization.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->organization_id === null) {
            return false;
        }

        return $user->organization_id === $order->pharmacy_id
            || $user->organization_id === $order->supplier_id;
    }

    /**
     * Only a pharmacy user may place an order (checkout). The actual
     * cart-ownership check happens in CartPolicy/controller; this gate
     * just confirms the role for the checkout action as a whole.
     */
    public function create(User $user): bool
    {
        return $user->role === 'pharmacy' && $user->organization_id !== null;
    }

    /**
     * Only the supplier fulfilling this specific order may update its
     * status. A pharmacy may never change order status, even for its
     * own order.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role === 'supplier'
            && $user->organization_id !== null
            && $user->organization_id === $order->supplier_id;
    }
}
