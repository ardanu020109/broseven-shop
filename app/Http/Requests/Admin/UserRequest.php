<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $username = $this->input('username');
        $email = $this->input('email');

        $this->merge([
            'name' => is_string($name) ? trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '') : $name,
            'username' => is_string($username) ? trim($username) : $username,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $user = $user instanceof User ? $user : null;

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->getKey()),
            ],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            'role' => ['required', Rule::in(Role::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($user === null) {
            $rules['password'] = ['required', 'string', new StrongPassword];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama minimal :min karakter.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal :min karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan strip.',
            'username.unique' => 'Username sudah digunakan (termasuk akun yang sudah dihapus).',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan (termasuk akun yang sudah dihapus).',
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
