<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRatingRequest extends FormRequest
{
    /**
     * فقط الصيدلية صاحبة أمر الشراء تقيّم المورد، وفقط بعد اكتمال التوريد.
     */
    public function authorize(): bool
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->route('purchaseOrder');

        return $this->user()?->role === 'pharmacy'
            && $this->user()->organization_id === $purchaseOrder->pharmacy_id
            && $purchaseOrder->status === 'completed'
            && $purchaseOrder->rating === null; // منع التقييم المكرر
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating'  => ['required', 'numeric', 'min:1', 'max:5',
                          Rule::in([1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.in' => 'التقييم يجب أن يكون من القيم: 1، 1.5، 2، 2.5، 3، 3.5، 4، 4.5، 5.',
        ];
    }
}
