<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Cart;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class CheckoutRequest extends FormRequest
{
    /**
     * Only pharmacy users with an organization may checkout, and only
     * their own organization's cart.
     */
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'pharmacy' || $this->user()->organization_id === null) {
            return false;
        }

        $cart = Cart::query()
            ->where('pharmacy_id', $this->user()->organization_id)
            ->first();

        // No cart yet is treated as "not authorized to checkout"
        // rather than a validation error, since there is nothing to
        // checkout and no resource to act on.
        return $cart !== null;
    }

    /**
     * Checkout carries no client-supplied payload — quantities and
     * prices are derived entirely from the cart and product records
     * server-side, never trusted from request input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * After the standard rules pass, confirm the cart actually has
     * items in it. An empty cart is a valid resource but an invalid
     * checkout target, so it is rejected as a validation failure
     * (422) rather than an authorization failure (403).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $cart = Cart::query()
                ->where('pharmacy_id', $this->user()->organization_id)
                ->with('items')
                ->first();

            if ($cart === null || $cart->items->isEmpty()) {
                $validator->errors()->add('cart', 'Your cart is empty.');
            }
        });
    }

    /**
     * Surface failed authorization with a clear, explicit message
     * instead of the default generic 403.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Only pharmacy users with an existing cart may checkout.',
        ], 403));
    }
}
