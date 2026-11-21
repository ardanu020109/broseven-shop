<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WebsiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'primary' => $hex,
            'secondary' => $hex,
            'danger' => $hex,
            'background' => $hex,
            'dark' => $hex,
        ];
    }

    public function messages(): array
    {
        $message = 'Gunakan format HEX 6 digit, contoh #06D6A0.';

        return [
            'primary.required' => 'Warna Primary wajib diisi.',
            'primary.regex' => $message,
            'secondary.required' => 'Warna Secondary wajib diisi.',
            'secondary.regex' => $message,
            'danger.required' => 'Warna Danger wajib diisi.',
            'danger.regex' => $message,
            'background.required' => 'Warna Background wajib diisi.',
            'background.regex' => $message,
            'dark.required' => 'Warna Dark wajib diisi.',
            'dark.regex' => $message,
        ];
    }
}
