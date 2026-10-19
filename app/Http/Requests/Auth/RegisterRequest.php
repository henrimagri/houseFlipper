<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if (! $this->exists('tenant_slug') && is_string($this->input('tenant_nome'))) {
            $this->merge(['tenant_slug' => substr(Str::slug($this->input('tenant_nome')), 0, 60).'-'.strtolower(Str::random(10))]);
        }
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => preg_replace('/\D/', '', $this->input('phone'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_nome' => ['required', 'string', 'max:150'],
            'tenant_slug' => ['required', 'string', 'max:80', 'unique:tenants,slug', 'regex:/^[a-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email', function ($attribute, $value, $fail) {
                if (is_string($value) && User::whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->exists()) {
                    $fail('Este e-mail já está cadastrado.');
                }
            }],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('A senha deve ocupar no máximo 72 bytes. Use menos caracteres especiais.');
                }
            }],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'address' => ['sometimes', 'required', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'address.postal_code' => ['required_with:address', 'string', 'regex:/^\d{8}$/'],
            'address.street' => ['required_with:address', 'string', 'max:180'],
            'address.number' => ['required_with:address', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:120'],
            'address.neighborhood' => ['required_with:address', 'string', 'max:120'],
            'address.city' => ['required_with:address', 'string', 'max:120'],
            'address.state' => ['required_with:address', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
        ];
    }
}
