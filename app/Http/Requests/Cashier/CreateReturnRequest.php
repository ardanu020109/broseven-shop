<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan retur wajib diisi.',
            'reason.min' => 'Alasan retur minimal :min karakter.',
            'reason.max' => 'Alasan retur maksimal :max karakter.',
            'idempotency_key.required' => 'Permintaan tidak valid. Muat ulang halaman.',
            'idempotency_key.uuid' => 'Permintaan tidak valid. Muat ulang halaman.',
            'items.required' => 'Pilih minimal satu barang yang diretur.',
            'items.min' => 'Pilih minimal satu barang yang diretur.',
            'items.max' => 'Terlalu banyak barang dalam satu retur.',
            'items.*.order_item_id.required' => 'Barang tidak valid.',
            'items.*.order_item_id.integer' => 'Barang tidak valid.',
            'items.*.quantity.required' => 'Jumlah retur wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah retur harus berupa bilangan bulat.',
            'items.*.quantity.min' => 'Jumlah retur minimal 1.',
            'items.*.quantity.max' => 'Jumlah retur terlalu besar.',
        ];
    }
}
