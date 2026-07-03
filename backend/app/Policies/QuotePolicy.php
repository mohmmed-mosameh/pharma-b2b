<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    /**
     * - المورد: لا يرى إلا عروضه (يتم الفلترة في الـ Controller).
     * - الصيدلية: لا ترى إلا عروض المناقصات التابعة لها (يتم الفلترة
     *   في OrderController الخاص بفتح المظاريف عبر RfqPolicy::view).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['pharmacy', 'supplier'], true)
            && $user->organization_id !== null;
    }

    /**
     * عرض تفاصيل عرض سعر واحد: المورد صاحب العرض، أو الصيدلية
     * صاحبة المناقصة المرتبطة به.
     */
    public function view(User $user, Quote $quote): bool
    {
        if ($user->organization_id === null) {
            return false;
        }

        if ($user->role === 'supplier') {
            return $user->organization_id === $quote->supplier_id;
        }

        if ($user->role === 'pharmacy') {
            return $user->organization_id === $quote->rfq->pharmacy_id;
        }

        return false;
    }

    /**
     * فقط مستخدم بدور "supplier" تابع لمنظمة يمكنه تقديم عرض سعر.
     * شرط "المناقصة مفتوحة" يتم التحقق منه في StoreQuoteRequest
     * لأنه يحتاج الوصول لموديل الـ Rfq من الـ route.
     */
    public function create(User $user): bool
    {
        return $user->role === 'supplier' && $user->organization_id !== null;
    }
}
