<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $product = $product instanceof Product ? $product : null;
        $maxMoney = 9999999999999;

        $rules = [
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($product?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'integer', $this->activeOrCurrent('categories', $product?->category_id)],
            'product_type_id' => ['nullable', 'integer', $this->activeOrCurrent('product_types', $product?->product_type_id)],
            'brand_id' => ['nullable', 'integer', $this->activeOrCurrent('brands', $product?->brand_id)],
            'unit_id' => ['nullable', 'integer', $this->activeOrCurrent('units', $product?->unit_id)],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:'.$maxMoney],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:'.$maxMoney],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($product === null) {
            $rules['stock'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang wajib diisi.',
            'name.max' => 'Nama barang maksimal :max karakter.',
            'barcode.unique' => 'Barcode sudah digunakan produk lain.',
            'barcode.max' => 'Barcode maksimal :max karakter.',
            'image.file' => 'Foto gagal diunggah. Ukuran maksimum 2 MB.',
            'image.uploaded' => 'Foto gagal diunggah. Ukuran maksimum 2 MB.',
            'image.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 2 MB.',
            'image.dimensions' => 'Dimensi foto maksimal 4000 x 4000 piksel.',
            'category_id.exists' => 'Kategori tidak valid atau tidak aktif.',
            'product_type_id.exists' => 'Tipe barang tidak valid atau tidak aktif.',
            'brand_id.exists' => 'Merek tidak valid atau tidak aktif.',
            'unit_id.exists' => 'Satuan tidak valid atau tidak aktif.',
            'purchase_price.numeric' => 'Harga beli harus berupa angka.',
            'purchase_price.min' => 'Harga beli tidak boleh negatif.',
            'purchase_price.max' => 'Harga beli terlalu besar.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'selling_price.numeric' => 'Harga jual harus berupa angka.',
            'selling_price.min' => 'Harga jual tidak boleh negatif.',
            'selling_price.max' => 'Harga jual terlalu besar.',
            'stock.required' => 'Stok awal wajib diisi.',
            'stock.integer' => 'Stok awal harus berupa bilangan bulat.',
            'stock.min' => 'Stok tidak boleh negatif.',
            'stock.max' => 'Stok awal terlalu besar.',
            'min_stock.integer' => 'Stok minimum harus berupa bilangan bulat.',
            'min_stock.min' => 'Stok minimum tidak boleh negatif.',
            'description.max' => 'Deskripsi maksimal :max karakter.',
            'is_active.boolean' => 'Status tidak valid.',
        ];
    }

    private function activeOrCurrent(string $table, ?int $currentId)
    {
        return Rule::exists($table, 'id')->where(function ($query) use ($currentId) {
            $query->where(function ($group) use ($currentId) {
                $group->where('is_active', true);

                if ($currentId !== null) {
                    $group->orWhere('id', $currentId);
                }
            });
        });
    }
}
