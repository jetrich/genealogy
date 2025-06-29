<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\SecureInput;
use App\Rules\GenealogySecureInput;
use Illuminate\Foundation\Http\FormRequest;

class SecurePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:100', new SecureInput(), new GenealogySecureInput()],
            'surname' => ['required', 'string', 'max:100', new SecureInput(), new GenealogySecureInput()],
            'birth_date' => ['nullable', 'date', new SecureInput()],
            'death_date' => ['nullable', 'date', new SecureInput()],
            'birth_place' => ['nullable', 'string', 'max:255', new SecureInput()],
            'death_place' => ['nullable', 'string', 'max:255', new SecureInput()],
            'description' => ['nullable', 'string', 'max:1000', new SecureInput()],
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'First name is required for genealogy records.',
            'surname.required' => 'Surname is required for genealogy records.',
            '*.max' => 'The :attribute field is too long.',
        ];
    }
}