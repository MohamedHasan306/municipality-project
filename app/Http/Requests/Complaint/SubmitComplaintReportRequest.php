<?php

namespace App\Http\Requests\Complaint;

use App\Models\ComplaintReport;
use Illuminate\Foundation\Http\FormRequest;

class SubmitComplaintReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report instanceof ComplaintReport
            && $this->user()?->can('submit', $report);
    }

    public function rules(): array
    {
        return [];
    }
}
