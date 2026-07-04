<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Anyone authenticated can view the catalog. Adjust if you later
     * want products visible to unauthenticated/public traffic too.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Only users with the supplier role may create products.
     */
    public function create(User $user): bool
    {
        return $user->role === 'supplier';
    }

    /**
     * A supplier may only update a product their own organization owns.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->role === 'supplier'
            && $user->organization_id !== null
            && $user->organization_id === $product->supplier_id;
    }

    /**
     * يجوز الحذف في حالتين فقط:
     *   1) الصنف أضافه هذا المستخدم بنفسه (created_by يطابقه) — يشمل
     *      الأصناف التي تضيفها الصيدلية للكتالوج المشترك.
     *   2) مورد يملك الصنف عبر منظمته (توافقًا مع القاعدة القديمة).
     * الأصناف الافتراضية المزروعة (created_by = null و supplier_id = null)
     * تبقى محمية ولا يقدر أحد يحذفها.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($product->created_by !== null && $product->created_by === $user->id) {
            return true;
        }

        return $user->role === 'supplier'
            && $user->organization_id !== null
            && $user->organization_id === $product->supplier_id;
    }
}
