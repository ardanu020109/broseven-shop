<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['STOCK_IN', 'ADJUSTMENT'])],
            'quantity' => [
                'required',
                'integer',
                'not_in:0',
                'between:-1000000,1000000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->input('type') === 'STOCK_IN' && (int) $value < 0) {
                        $fail('Stok masuk harus bernilai positif. Gunakan Penyesuaian untuk mengurangi stok.');
                    }
                },
            ],
            'note' => [
                Rule::requiredIf(fn () => $this->input('type') === 'ADJUSTMENT'),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis perubahan wajib dipilih.',
            'type.in' => 'Jenis perubahan tidak valid.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.integer' => 'Jumlah harus berupa bilangan bulat.',
            'quantity.not_in' => 'Jumlah tidak boleh nol.',
            'quantity.between' => 'Jumlah di luar batas yang diizinkan.',
            'note.required' => 'Keterangan wajib diisi untuk penyesuaian stok.',
            'note.max' => 'Keterangan maksimal :max karakter.',
        ];
    }
}
