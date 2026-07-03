<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRfqRequest extends FormRequest
{
    /**
     * فقط مستخدم بدور "pharmacy" تابع لمنظمة، يمكنه إنشاء مناقصة.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'pharmacy'
            && $this->user()->organization_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'quotes_deadline_at' => ['required', 'date', 'after:now'],
            'opening_at' => ['required', 'date', 'after:quotes_deadline_at'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id'),
                'distinct',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes'    => ['nullable', 'string', 'max:1000'],

            'score_weight_price'    => ['sometimes', 'integer', 'min:0', 'max:100'],
            'score_weight_delivery' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'score_weight_rating'   => ['sometimes', 'integer', 'min:0', 'max:100'],
            'score_weight_verified' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $v) {
            $fields = ['score_weight_price', 'score_weight_delivery',
                       'score_weight_rating', 'score_weight_verified'];

            $anyProvided = collect($fields)->some(fn ($f) => $this->has($f));

            if (! $anyProvided) {
                return; // استخدام القيم الافتراضية من قاعدة البيانات
            }

            $total = collect($fields)->sum(fn ($f) => (int) $this->input($f, 0));

            if ($total !== 100) {
                $v->errors()->add(
                    'score_weights',
                    "يجب أن يكون مجموع أوزان معايير التقييم 100 بالضبط. المجموع الحالي: {$total}."
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
            'opening_at.after'               => 'يجب أن يكون وقت فتح المظاريف بعد آخر موعد لتقديم العروض.',
            'items.*.product_id.distinct'    => 'لا يمكن تكرار نفس المنتج أكثر من مرة في المناقصة.',
        ];
    }
}
