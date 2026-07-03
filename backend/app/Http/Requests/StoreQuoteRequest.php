<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Rfq;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuoteRequest extends FormRequest
{
    /**
     * فقط مستخدم بدور "supplier" تابع لمنظمة، ويُسمح له بتقديم عرض
     * فقط على مناقصة بحالة "open" (لم يغلق باب التقديم بعد).
     */
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'supplier' || $this->user()->organization_id === null) {
            return false;
        }

        /** @var Rfq $rfq */
        $rfq = $this->route('rfq');

        return $rfq->status === 'open';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => [
                'required',
                'integer',
                Rule::exists('rfq_items', 'id')->where('rfq_id', $this->route('rfq')->id),
                'distinct',
            ],
            'items.*.unit_price'          => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.available_qty'       => ['nullable', 'integer', 'min:1'],
            'items.*.delivery_days'       => ['required', 'integer', 'min:0', 'max:365'],
            'items.*.notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * بعد نجاح القواعد الأساسية، نتأكد إن المورد قدّم سعرًا لكل صنف
     * مطلوب في المناقصة، وليس لبعضها فقط، حسب ما طُلب صريحًا
     * ("تقديم عرض سعر لكل صنف في المناقصة").
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var Rfq $rfq */
            $rfq = $this->route('rfq');

            $requiredItemIds = $rfq->rfqItems()->pluck('id')->sort()->values();

            $submittedItemIds = collect($this->input('items', []))
                ->pluck('rfq_item_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($requiredItemIds->toArray() !== $submittedItemIds->toArray()) {
                $validator->errors()->add(
                    'items',
                    'يجب تقديم عرض سعر لكل صنف مطلوب في المناقصة، بدون نقص أو زيادة.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.rfq_item_id.exists' => 'أحد الأصناف المُرسلة لا ينتمي لهذه المناقصة.',
            'items.*.rfq_item_id.distinct' => 'لا يمكن تقديم أكثر من سعر لنفس الصنف.',
        ];
    }
}
