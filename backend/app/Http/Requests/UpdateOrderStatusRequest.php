<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Delegates to OrderPolicy::updateStatus so the "only the
     * fulfilling supplier" rule lives in exactly one place.
     */
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('updateStatus', $order) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');

        return [
            'status' => [
                'required',
                'string',
                Rule::in($this->allowedNextStatuses($order->status)),
            ],
        ];
    }

    /**
     * Enforce a sane forward-only status lifecycle:
     *   pending   -> shipped, cancelled
     *   shipped   -> delivered, cancelled
     *   delivered -> (terminal, no further transitions)
     *   cancelled -> (terminal, no further transitions)
     *
     * @return array<int, string>
     */
    private function allowedNextStatuses(string $currentStatus): array
    {
        return match ($currentStatus) {
            'pending' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
            'delivered', 'cancelled' => [],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'That status transition is not allowed from the order\'s current status.',
        ];
    }
}
