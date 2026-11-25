<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('jobs.manage') ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'requisition_id' => ['nullable', 'integer', 'exists:job_requisitions,id'],
            'department_id' => [$req, 'integer', 'exists:departments,id'],
            'recruiter_id' => ['nullable', 'integer', 'exists:users,id'],
            'hiring_manager_id' => [$req, 'integer', 'exists:users,id'],
            'title' => [$req, 'string', 'max:180'],
            'employment_type' => [$req, Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'work_arrangement' => [$req, Rule::in(['onsite', 'hybrid', 'remote'])],
            'location' => [$req, 'string', 'max:150'],
            'experience_level' => ['nullable', Rule::in(['entry', 'junior', 'mid', 'senior', 'lead', 'executive'])],
            'description' => [$req, 'string', 'min:20'],
            'requirements' => [$req, 'string', 'min:10'],
            'responsibilities' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'is_salary_visible' => ['nullable', 'boolean'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'closes_at' => ['nullable', 'date'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['required'],
        ];
    }
}
