<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'first_name' => [$req, 'string', 'max:100'],
            'last_name' => [$req, 'string', 'max:100'],
            'email' => [$req, 'string', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:150'],
            'headline' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'current_title' => ['nullable', 'string', 'max:150'],
            'years_of_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'source' => ['nullable', Rule::in(['careers_portal', 'linkedin', 'referral', 'agency', 'direct_sourced', 'other'])],
            'skills' => ['nullable', 'array'],
            'experiences' => ['nullable', 'array'],
            'experiences.*.company_name' => ['required_with:experiences', 'string', 'max:150'],
            'experiences.*.job_title' => ['required_with:experiences', 'string', 'max:150'],
            'experiences.*.employment_type' => ['nullable', 'string', 'max:50'],
            'experiences.*.location' => ['nullable', 'string', 'max:150'],
            'experiences.*.start_date' => ['required_with:experiences', 'date'],
            'experiences.*.end_date' => ['nullable', 'date'],
            'experiences.*.is_current' => ['nullable', 'boolean'],
            'experiences.*.description' => ['nullable', 'string'],
            'educations' => ['nullable', 'array'],
            'educations.*.institution' => ['required_with:educations', 'string', 'max:180'],
            'educations.*.degree' => ['required_with:educations', 'string', 'max:120'],
            'educations.*.field_of_study' => ['required_with:educations', 'string', 'max:150'],
            'educations.*.start_date' => ['required_with:educations', 'date'],
            'educations.*.end_date' => ['nullable', 'date'],
            'educations.*.grade' => ['nullable', 'string', 'max:40'],
            'educations.*.description' => ['nullable', 'string'],
        ];
    }
}
