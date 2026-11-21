<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (['store_name', 'address', 'phone', 'email', 'description', 'footer_text'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $clean[$field] = trim(strip_tags($value));
            }
        }

        $this->merge($clean);
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'min:2', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'footer_text' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Nama toko wajib diisi.',
            'store_name.min' => 'Nama toko minimal :min karakter.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
            'email.email' => 'Format email tidak valid.',
            'logo.file' => 'Logo gagal diunggah. Ukuran maksimum 2 MB.',
            'logo.uploaded' => 'Logo gagal diunggah. Ukuran maksimum 2 MB.',
            'logo.mimes' => 'Logo harus berformat JPG, PNG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'logo.dimensions' => 'Dimensi logo maksimal 4000 x 4000 piksel.',
            'favicon.file' => 'Favicon gagal diunggah. Ukuran maksimum 512 KB.',
            'favicon.uploaded' => 'Favicon gagal diunggah. Ukuran maksimum 512 KB.',
            'favicon.mimes' => 'Favicon harus berformat PNG, ICO, JPG, atau WEBP.',
            'favicon.max' => 'Ukuran favicon maksimal 512 KB.',
        ];
    }
}
