<?php

namespace App\Http\Requests\Citizen;

use Illuminate\Foundation\Http\FormRequest;

class UploadIdentityPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upload identity photos') ?? false;
    }

    public function rules(): array
    {
        return [
            'front_id_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:4096',
            ],
            'back_id_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:4096',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'front_id_photo.required' => 'The front side photo of the ID is required.',
            'back_id_photo.required' => 'The back side photo of the ID is required.',
            'front_id_photo.image' => 'The file must be an image.',
            'back_id_photo.image' => 'The file must be an image.',
            'front_id_photo.mimes' => 'The image must be of type jpg, jpeg, or png.',
            'back_id_photo.mimes' => 'The image must be of type jpg, jpeg, or png.',
            'front_id_photo.max' => 'The image size must not exceed 4MB.',
            'back_id_photo.max' => 'The image size must not exceed 4MB.',
        ];
    }
}
