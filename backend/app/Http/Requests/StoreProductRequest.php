<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * الموردون يضيفون منتجاتهم بسعرها (يُنسب المنتج لمنظمتهم). الصيدليات
     * أيضًا يمكنها إضافة صنف غير موجود بالكتالوج أثناء إنشاء مناقصة (بدون
     * سعر ولا نسبة لأي مورد بعينه)، لأن الكتالوج عام ومشترك بين الطرفين.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['supplier', 'pharmacy'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'form' => ['nullable', 'string', 'max:100'],
            'strength' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
