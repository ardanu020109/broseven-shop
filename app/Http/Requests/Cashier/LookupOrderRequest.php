<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class LookupOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        $this->merge([
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode pesanan wajib diisi.',
            'code.min' => 'Kode terlalu pendek.',
            'code.max' => 'Kode terlalu panjang.',
        ];
    }
}
