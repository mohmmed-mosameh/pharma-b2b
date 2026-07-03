<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AwardRfqRequest extends FormRequest
{
    /**
     * يتم التفويض الكامل (دور + ملكية المناقصة) عبر RfqPolicy::award.
     */
    public function authorize(): bool
    {
        /** @var Rfq $rfq */
        $rfq = $this->route('rfq');

        return $this->user()?->can('award', $rfq) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Rfq $rfq */
        $rfq = $this->route('rfq');

        return [
            'quote_id' => [
                'required',
                'integer',
                Rule::exists(Quote::class, 'id')
                    ->where('rfq_id', $rfq->id)
                    ->where('status', 'submitted'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quote_id.exists' => 'هذا العرض غير صالح للترسية (لا ينتمي للمناقصة أو حالته غير مؤهلة).',
        ];
    }
}
