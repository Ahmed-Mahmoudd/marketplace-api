<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('gender')) {
            $this->merge([
                'gender' => ucfirst(strtolower($this->gender)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'gender' => [
                'nullable',
                'string',
                Rule::in(['Male', 'Female', 'Other']),
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
