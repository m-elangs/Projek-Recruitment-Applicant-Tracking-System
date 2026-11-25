<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('requisitions.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:180'],
            'headcount' => ['required', 'integer', 'min:1', 'max:500'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'work_arrangement' => ['required', Rule::in(['onsite', 'hybrid', 'remote'])],
            'location' => ['required', 'string', 'max:150'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'currency' => ['nullable', 'string', 'size:3'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'target_start_date' => ['nullable', 'date'],
            'justification' => ['required', 'string', 'min:10'],
            'requirements' => ['nullable', 'string'],
        ];
    }
}
