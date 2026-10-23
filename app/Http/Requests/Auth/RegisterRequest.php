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
                    $fail('Não foi possível criar a conta com estes dados.');
                }
            }],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('A senha deve ocupar no máximo 72 bytes. Use menos caracteres especiais.');
                }
            }],
            'analytics_session' => ['nullable', 'uuid'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\d{10,11}$/'],
            'intended_product' => ['required', 'in:corretor,imobiliaria,house_flipper'],
            'professional_name' => ['required_if:intended_product,corretor', 'nullable', 'string', 'max:150'],
            'creci' => ['nullable', 'string', 'max:40'],
            'city' => ['required_if:intended_product,corretor,imobiliaria', 'nullable', 'string', 'max:120'],
            'company_name' => ['required_if:intended_product,imobiliaria', 'nullable', 'string', 'max:150'],
            'region_preference' => ['required_if:intended_product,house_flipper', 'nullable', 'string', 'max:120'],
            'usage_goal' => ['required_if:intended_product,house_flipper', 'nullable', 'string', 'max:180'],
            'consent' => ['required', 'accepted'],
            'address' => ['required', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'address.postal_code' => ['required_with:address', 'string', 'regex:/^\d{8}$/'],
            'address.street' => ['required_with:address', 'string', 'max:180'],
            'address.number' => ['required_with:address', 'string', 'max:20'],
            'address.complement' => ['required', 'string', 'max:120'],
            'address.neighborhood' => ['required_with:address', 'string', 'max:120'],
            'address.city' => ['required_with:address', 'string', 'max:120'],
            'address.state' => ['required_with:address', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
        ];
    }

    public function attributes(): array
    {
        return ['tenant_nome' => 'nome da operação', 'name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'password' => 'senha', 'intended_product' => 'produto', 'professional_name' => 'nome profissional', 'company_name' => 'nome da empresa', 'city' => 'cidade', 'region_preference' => 'região de interesse', 'usage_goal' => 'objetivo principal', 'address' => 'endereço', 'address.postal_code' => 'CEP', 'address.street' => 'rua', 'address.number' => 'número', 'address.complement' => 'complemento', 'address.neighborhood' => 'bairro', 'address.city' => 'cidade do endereço', 'address.state' => 'UF', 'consent' => 'aceite dos termos e da política de privacidade'];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'required_if' => 'O campo :attribute é obrigatório para o produto escolhido.',
            'password.confirmed' => 'As senhas não coincidem.',
            'phone.regex' => 'Informe um telefone válido com DDD.',
            'email.unique' => 'Não foi possível criar a conta com estes dados.',
            'consent.accepted' => 'É necessário aceitar a política de privacidade.',
        ];
    }
}
