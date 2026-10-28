<?php

namespace App\Http\Requests\Complaint;

use App\Models\ComplaintReport;
use Illuminate\Foundation\Http\FormRequest;

class UploadComplaintReportImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof ComplaintReport
            && $this->user()?->can('uploadImages', $report);
    }

    public function rules(): array
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'يجب رفع صورة واحدة على الأقل.',
            'images.max' => 'لا يمكن رفع أكثر من خمس صور.',
            'images.*.image' => 'يجب أن يكون الملف صورة.',
            'images.*.mimes' => 'صيغة الصورة يجب أن تكون JPG أو JPEG أو PNG.',
            'images.*.max' => 'يجب ألا يتجاوز حجم الصورة 5 ميغابايت.',
        ];
    }
}
