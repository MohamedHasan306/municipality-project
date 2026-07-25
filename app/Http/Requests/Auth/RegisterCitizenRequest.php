<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterCitizenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'municipality_id' => ['required', 'exists:municipalities,id'],
            'gender' => ['required', 'in:Male,Female'],
            'birth_date' => ['required', 'date'],
            'national_id' => ['required', 'string', 'max:255', 'unique:citizen_profiles,national_id'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'needs_special_care' => ['nullable', 'boolean'],

            'front_id_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'back_id_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
