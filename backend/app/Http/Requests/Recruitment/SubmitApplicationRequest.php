<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('applications.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'candidate_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'cv_document_id' => ['nullable', 'integer', 'exists:candidate_documents,id'],
            'source' => ['nullable', Rule::in(['careers_portal', 'linkedin', 'referral', 'agency', 'direct_sourced', 'other'])],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'available_from' => ['nullable', 'date'],
        ];
    }
}
