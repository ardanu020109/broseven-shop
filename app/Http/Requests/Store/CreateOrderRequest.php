<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('customer_name');
        $phone = $this->input('customer_phone');
        $notes = $this->input('notes');

        $this->merge([
            'customer_name' => is_string($name)
                ? trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '')
                : $name,
            'customer_phone' => is_string($phone)
                ? (preg_replace('/[\s\-().]/', '', $phone) ?? '')
                : $phone,
            'notes' => is_string($notes)
                ? (trim(strip_tags($notes)) ?: null)
                : $notes,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama wajib diisi.',
            'customer_name.min' => 'Nama minimal :min karakter.',
            'customer_name.max' => 'Nama maksimal :max karakter.',
            'customer_phone.required' => 'Nomor telepon wajib diisi.',
            'customer_phone.regex' => 'Nomor telepon tidak valid (8-15 digit).',
            'notes.max' => 'Catatan maksimal :max karakter.',
            'idempotency_key.required' => 'Permintaan tidak valid. Muat ulang halaman.',
            'idempotency_key.uuid' => 'Permintaan tidak valid. Muat ulang halaman.',
            'items.required' => 'Keranjang kosong.',
            'items.min' => 'Keranjang kosong.',
            'items.max' => 'Keranjang maksimal berisi 50 jenis barang.',
            'items.*.product_id.required' => 'Produk tidak valid.',
            'items.*.product_id.integer' => 'Produk tidak valid.',
            'items.*.quantity.required' => 'Jumlah wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah harus berupa bilangan bulat.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
            'items.*.quantity.max' => 'Jumlah maksimal 1000 per barang.',
        ];
    }
}
