<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        $this->merge([
            'reason' => is_string($reason)
                ? trim(preg_replace('/\s+/u', ' ', strip_tags($reason)) ?? '')
                : $reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal :min karakter.',
            'reason.max' => 'Alasan pembatalan maksimal :max karakter.',
        ];
    }
}
