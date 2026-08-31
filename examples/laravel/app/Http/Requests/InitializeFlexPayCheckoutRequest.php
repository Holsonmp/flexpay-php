<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitializeFlexPayCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Le middleware CheckoutOwner réalise l’autorisation métier.
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(['mobile_money', 'card'])],
            'phone_number' => ['nullable', 'string', 'max:24', 'required_if:payment_method,mobile_money'],
        ];
    }
}
