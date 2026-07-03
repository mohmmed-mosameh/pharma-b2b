<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

class RfqPolicy
{
    /**
     * كل من الصيدلية والمورد مسموح له برؤية قائمة المناقصات
     * (الـ scoping الفعلي - مناقصاتي فقط / المفتوحة فقط - يتم
     * داخل الـ Controller حسب الدور).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['pharmacy', 'supplier'], true)
            && $user->organization_id !== null;
    }

    /**
     * - الصيدلية: ترى فقط مناقصاتها الخاصة.
     * - المورد: يرى أي مناقصة طالما كانت أو أصبحت "open" على الأقل
     *   مرة (لا يُسمح له برؤية مناقصة لم تُفتح للعروض بعد إن وُجد
     *   مفهوم "مسودة" مستقبلًا؛ حاليًا كل المناقصات تُنشأ بحالة open).
     */
    public function view(User $user, Rfq $rfq): bool
    {
        if ($user->organization_id === null) {
            return false;
        }

        if ($user->role === 'pharmacy') {
            return $user->organization_id === $rfq->pharmacy_id;
        }

        if ($user->role === 'supplier') {
            return true;
        }

        return false;
    }

    /**
     * فقط مستخدم بدور "pharmacy" يمكنه إنشاء مناقصة (التحقق الكامل
     * من الحقول يتم في StoreRfqRequest).
     */
    public function create(User $user): bool
    {
        return $user->role === 'pharmacy' && $user->organization_id !== null;
    }

    /**
     * فقط الصيدلية صاحبة المناقصة، ولا يجوز الترسية إلا إذا كانت
     * المناقصة في حالة "opened" (تم فتح المظاريف فعليًا).
     */
    public function award(User $user, Rfq $rfq): bool
{
    return $user->role === 'pharmacy'
        && $user->organization_id !== null
        && $user->organization_id === $rfq->pharmacy_id
        && in_array($rfq->status, ['open', 'opened', 'pending_opening'], true);
}
}
