<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'otp'        => ['required', 'string', 'size:4'],
            'password'   => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return ['password.confirmed' => 'كلمة المرور وتأكيدها غير متطابقتين.'];
    }
}
